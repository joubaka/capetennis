<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\Registration;
use App\Services\Wallet\WalletService;

class RegistrationPaymentController extends Controller
{
  /**
   * Hybrid Wallet + PayFast
   */
  public function hybridPay(Request $request)
  {
    $type = $request->type ?? 'registration';

    // orderId must be read from the request before any use
    $orderId = (int) $request->custom_int5;
    $walletApplied = round((float) $request->wallet_applied, 2);
    $remaining = round((float) $request->remaining_amount, 2);

    if ($type === 'team') {
      $order = \App\Models\TeamPaymentOrder::findOrFail($orderId);
    } else {
      $order = RegistrationOrder::with('items')->findOrFail($orderId);
    }
    
    $user = auth()->user();
    $wallet = $user?->wallet;

    if (!$user || !$wallet) {
      return back()->withErrors('Wallet not found.');
    }

    if ($walletApplied < 0 || $remaining < 0) {
      return back()->withErrors('Invalid payment amounts.');
    }

    // 🔒 Ownership protection (cast both to int to avoid type mismatch)
    if ((int) $order->user_id !== (int) $user->id) {
      abort(403, 'Unauthorized order access.');
    }

    // 🛑 Already fully paid
    if ($order->pay_status == 1 || $order->payfast_paid) {
      return redirect()
        ->route('frontend.registration.success', ['order' => $orderId])
        ->with('success', 'Order already paid.');
    }

    // 🔁 Prevent double reserve (refresh-safe)
    if ($order->wallet_reserved > 0 && !$order->wallet_debited) {

      Log::info('HYBRID PAY: Already reserved', [
        'order_id' => $order->id,
        'wallet_reserved' => $order->wallet_reserved,
      ]);

      $remaining = $order->payfast_amount_due;

    } else {

      if ($walletApplied > (float) $wallet->balance) {
        return back()->withErrors('Insufficient wallet balance.');
      }

      DB::transaction(function () use ($order, $walletApplied, $remaining) {

        $order->wallet_reserved = $walletApplied;
        $order->payfast_amount_due = $remaining;
        $order->wallet_debited = false;
        $order->save();
      });

      Log::info('HYBRID RESERVED', [
        'order_id' => $order->id,
        'user_id' => $user->id,
        'wallet_reserved' => $walletApplied,
        'payfast_due' => $remaining,
      ]);
    }

    // 💰 Wallet-only payment
    if ($remaining <= 0) {
      return redirect()->route('registration.hybrid.complete', ['orderId' => $orderId]);
    }

    // 🔁 Send to PayFast
    $payfast = new \App\Classes\Payfast();
    $payfast->setMode($user->id == 584 ? 0 : 1);

    return view('frontend.payfast.pay_now', [
      'payfast' => $payfast,
      'amount' => $remaining,
      'orderId' => $orderId,
      'custom_wallet_reserved' => $order->wallet_reserved,
      'return_url' => route('frontend.registration.success', ['order' => $orderId]),
      // route parameter name must be match route definition (/registration/hybrid/cancel/{orderId})
      'cancel_url' => route('registration.hybrid.cancel', ['orderId' => $orderId]),
      'notify_url' => route('notify'),
    ]);
  }

  /**
   * Apply wallet balance to an order (AJAX from checkout page).
   * 
   * PRODUCTION FIX: Added fallback to verify user ownership from order
   * in case session expires between page load and AJAX request.
   */
  public function applyWallet(Request $request)
  {
    // Support both parameter names: order_id or custom_int5
    $orderId = (int) ($request->order_id ?? $request->custom_int5 ?? 0);

    if (!$orderId) {
      return response()->json(['error' => 'No order ID provided.'], 400);
    }

    try {
      $order = RegistrationOrder::findOrFail($orderId);
    } catch (\Exception $e) {
      Log::error('WALLET APPLY: Order not found', ['order_id' => $orderId]);
      return response()->json(['error' => 'Order not found.'], 404);
    }

    $user = auth()->user();

    // Enhanced debugging for session issues
    if (!$user) {
      Log::warning('WALLET APPLY: User not authenticated', [
        'order_id' => $orderId,
        'session_id' => session()->getId(),
        'ip' => $request->ip(),
        'user_agent' => substr($request->userAgent(), 0, 100),
      ]);
      return response()->json(['error' => 'Unauthorized. Please login again.'], 403);
    }

    // Cast both to int to avoid type mismatch (string vs int)
    if ((int) $order->user_id !== (int) $user->id) {
      Log::warning('WALLET APPLY: Order ownership mismatch', [
        'order_id' => $orderId,
        'order_user_id' => (int) $order->user_id,
        'auth_user_id' => (int) $user->id,
      ]);
      return response()->json(['error' => 'Unauthorized.'], 403);
    }

    if ($order->wallet_debited || $order->payfast_paid) {
      return response()->json(['error' => 'Order already paid.'], 400);
    }

    $wallet = $user->wallet;
    $walletBalance = $wallet?->balance ?? 0;

    if ($walletBalance <= 0) {
      return response()->json(['error' => 'No wallet balance available.'], 400);
    }

    $total = (float) $order->items->sum('item_price');
    $walletApplied = min($walletBalance, $total);
    $remaining = round($total - $walletApplied, 2);

    try {
      $order->wallet_reserved = $walletApplied;
      $order->payfast_amount_due = $remaining;
      $order->save();

      Log::info('WALLET APPLIED TO ORDER', [
        'order_id' => $order->id,
        'user_id' => $user->id,
        'wallet_applied' => $walletApplied,
        'payfast_due' => $remaining,
      ]);

      return response()->json([
        'success' => true,
        'wallet_applied' => $walletApplied,
        'payfast_due' => $remaining,
        'wallet_covers_all' => $remaining <= 0,
      ]);
    } catch (\Exception $e) {
      Log::error('WALLET APPLY: Error saving order', [
        'order_id' => $orderId,
        'error' => $e->getMessage(),
      ]);
      return response()->json(['error' => 'Failed to apply wallet. Please try again.'], 500);
    }
  }

  /**
   * Wallet-only completion
   */
  public function hybridComplete(int $orderId)
  {
    Log::info('HYBRID COMPLETE START', [
      'order_id' => $orderId,
      'user_id' => auth()->id(),
    ]);

    $user = auth()->user();
    if (!$user) {
      return redirect()->route('events.index')
        ->withErrors('User session expired.');
    }

    $order = RegistrationOrder::find($orderId);
    if (!$order) {
      Log::error('HYBRID COMPLETE FAILED: Order not found', [
        'order_id' => $orderId
      ]);
      return redirect()->route('events.index')
        ->withErrors('Order not found.');
    }

    if ((int) $order->user_id !== (int) $user->id) {
      Log::warning('HYBRID COMPLETE: Unauthorized order access', [
        'order_id' => $orderId,
        'order_user_id' => (int) $order->user_id,
        'auth_user_id' => (int) $user->id,
      ]);
      abort(403);
    }

    if ($order->wallet_debited) {
      // Redirect back to the event page
      $eventId = optional($order->items->first()?->category_event?->event)->id;
      if ($eventId) {
        return redirect()->route('events.show', $eventId)
          ->with('success', 'Registration already completed.');
      }
      return redirect()
        ->route('frontend.registration.success', $orderId);
    }

    DB::transaction(function () use ($user, $order) {

      if ($order->wallet_reserved > 0) {

        Log::info('HYBRID COMPLETE DEBIT', [
          'order_id' => $order->id,
          'amount' => $order->wallet_reserved,
        ]);

        $eventName = optional($order->items->first()?->category_event?->event)->name ?? 'Event Registration';

        app(WalletService::class)->debit(
          $user->wallet,
          $order->wallet_reserved,
          'event_registration_wallet_payment',
          $order->id,
          [
            'order_id' => $order->id,
            'reference' => $eventName,
          ]
        );

        activity('wallet')
          ->performedOn($order)
          ->causedBy($user)
          ->withProperties([
            'type' => 'debit',
            'amount' => $order->wallet_reserved,
            'reference' => $eventName,
            'order_id' => $order->id,
          ])
          ->log("Wallet debited R{$order->wallet_reserved} for {$eventName}");
      }

      $order->wallet_debited = true;
      $order->payfast_paid = true;
      $order->pay_status = 1;
      $order->save();

      $this->markOrderPaid($order->id, 'WALLET');
    });

    $walletEventName = optional($order->items->first()?->category_event?->event)->name ?? 'Event';
    $walletPlayer = optional($order->items->first())->player_id
      ? \App\Models\Player::find($order->items->first()->player_id)
      : null;

    activity('registration')
      ->performedOn($order)
      ->causedBy($user)
      ->withProperties([
        'order_id' => $order->id,
        'event' => $walletEventName,
        'player' => $walletPlayer ? trim($walletPlayer->name . ' ' . $walletPlayer->surname) : '',
        'method' => 'wallet',
        'amount' => $order->wallet_reserved,
      ])
      ->log("Registration paid via wallet for {$walletEventName}");

    Log::info('HYBRID COMPLETE SUCCESS', [
      'order_id' => $orderId
    ]);

    // Redirect back to the event page
    $eventId = optional($order->items->first()?->category_event)->event_id;
    if ($eventId) {
      return redirect()->route('events.show', $eventId)
        ->with('success', 'Registration paid successfully using wallet.');
    }

    return redirect()
      ->route('frontend.registration.success', $orderId)
      ->with('success', 'Registration paid successfully using wallet.');
  }

  /**
   * PayFast ITN
   */
  public function handlePayfastSuccess(array $payfastData)
  {
    Log::info('PAYFAST ITN RECEIVED', [
      'raw_data' => $payfastData
    ]);

    $orderId = (int) ($payfastData['custom_int5'] ?? 0);
    $paymentStatus = $payfastData['payment_status'] ?? null;
    $amountGross = (float) ($payfastData['amount_gross'] ?? 0);

    if (!$orderId) {
      Log::error('PAYFAST ERROR: No order ID received');
      return;
    }

    if ($paymentStatus !== 'COMPLETE') {
      Log::warning('PAYFAST NOT COMPLETE - IGNORED', [
        'order_id' => $orderId,
        'status' => $paymentStatus
      ]);
      return;
    }

    $order = RegistrationOrder::with('user.wallet', 'items')->find($orderId);

    if (!$order) {
      Log::error('PAYFAST ERROR: Order not found', [
        'order_id' => $orderId
      ]);
      return;
    }

    // 🔐 Idempotency protection
    if ($order->pay_status == 1) {
      Log::info('PAYFAST SKIPPED: Already fully processed', [
        'order_id' => $orderId
      ]);
      return;
    }

    // 🔎 Validate amount
    $expected = round((float) $order->payfast_amount_due, 2);
    if ($expected > 0 && round($amountGross, 2) != $expected) {
      Log::error('PAYFAST AMOUNT MISMATCH', [
        'order_id' => $orderId,
        'expected' => $expected,
        'received' => $amountGross
      ]);
      return;
    }

    Log::info('PAYFAST ORDER STATE BEFORE', [
      'order_id' => $order->id,
      'wallet_reserved' => $order->wallet_reserved,
      'wallet_debited' => $order->wallet_debited,
      'payfast_paid' => $order->payfast_paid,
      'pay_status' => $order->pay_status,
    ]);

    try {

      DB::transaction(function () use ($order, $payfastData) {

        // 💰 Debit reserved wallet portion once
        if ($order->wallet_reserved > 0 && !$order->wallet_debited) {

          Log::info('PAYFAST DEBITING WALLET', [
            'order_id' => $order->id,
            'amount' => $order->wallet_reserved,
            'wallet_balance_before' => $order->user->wallet->balance
          ]);

          app(WalletService::class)->debit(
            $order->user->wallet,
            $order->wallet_reserved,
            'event_registration_wallet_payment',
            $order->id,
            ['order_id' => $order->id]
          );

          $order->wallet_debited = true;
        }

        // ✅ Mark order fully paid
        $order->payfast_paid = true;
        $order->pay_status = 1;
        $order->payfast_pf_payment_id = $payfastData['pf_payment_id'] ?? null;
        $order->save();

        Log::info('PAYFAST ORDER MARKED PAID', [
          'order_id' => $order->id,
          'pf_payment_id' => $order->payfast_pf_payment_id
        ]);

        // 🔗 Attach registrations
        $this->markOrderPaid($order->id, 'PAYFAST');
      });

    } catch (\Throwable $e) {

      Log::error('PAYFAST ITN FAILED', [
        'order_id' => $orderId,
        'message' => $e->getMessage(),
      ]);

      return;
    }

    Log::info('HYBRID PAYMENT COMPLETED SUCCESSFULLY', [
      'order_id' => $orderId
    ]);

    $pfEventName = optional($order->items->first()?->category_event?->event)->name ?? 'Event';

    activity('registration')
      ->performedOn($order)
      ->causedBy($order->user)
      ->withProperties([
        'order_id' => $order->id,
        'event' => $pfEventName,
        'method' => 'payfast',
        'pf_payment_id' => $payfastData['pf_payment_id'] ?? null,
        'amount_gross' => $payfastData['amount_gross'] ?? '',
      ])
      ->log("Registration paid via PayFast for {$pfEventName}");
  }

  /**
   * Cancel
   */
  public function hybridCancel(int $orderId)
  {
    $order = RegistrationOrder::find($orderId);

    if ($order) {
      $order->wallet_reserved = 0;
      $order->payfast_amount_due = 0;
      $order->save();
    }

    Log::info('HYBRID PAYMENT CANCELLED', [
      'order_id' => $orderId,
    ]);

    return redirect()
      ->route('events.index')
      ->withErrors('Payment cancelled. No wallet funds were deducted.');
  }

  /**
   * Attach registrations
   */
  private function markOrderPaid(int $orderId, string $method)
  {
    $items = RegistrationOrderItems::where('order_id', $orderId)->get();

    foreach ($items as $item) {

      $registration = Registration::find($item->registration_id);
      if (!$registration)
        continue;

      $registration->players()->syncWithoutDetaching([$item->player_id]);

      $registration->categoryEvents()->syncWithoutDetaching([
        $item->category_event_id => [
          'payment_status_id' => 1,
          'user_id' => $item->user_id,
          'pf_transaction_id' => $method . '-' . now()->timestamp,
        ],
      ]);
    }
  }
}
