<?php

namespace App\Http\Controllers\Frontend;

use App\Domain\Payments\Services\PaymentOrchestrator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\Registration;
use App\Services\Wallet\WalletService;
use App\Services\PaymentFailureReporter;

class RegistrationPaymentController extends Controller
{
  /**
   * Render an existing order without replaying the registration POST.
   */
  public function checkout(RegistrationOrder $order)
  {
    abort_unless((int) $order->user_id === (int) auth()->id(), 403);

    if ((int) $order->pay_status === 1 || $order->payfast_paid) {
      return redirect()->route('frontend.registration.success', ['order' => $order->id]);
    }

    if (isset($order->status) && $order->status === 'cancelled') {
      return redirect()->back()->withErrors('This order has been cancelled and cannot be paid.');
    }

    try {
      app(\App\Services\PlayerEligibilityService::class)->assertOrderEligible($order);
    } catch (\RuntimeException $exception) {
      return redirect()->back()->withErrors($exception->getMessage());
    }

    $order->load('items.category_event.event', 'items.category_event.category', 'items.player', 'user.wallet');
    abort_if($order->items->isEmpty(), 404);

    $payfast = new \App\Services\Payfast();
    $payfast->setMode(config('services.payfast.sandbox') ? 0 : 1);

    return view('frontend.payfast.check_out', compact('order', 'payfast'));
  }

  /**
   * Hybrid Wallet + PayFast
   */
  public function hybridPay(Request $request)
  {
    $type = $request->type ?? 'registration';

    // Only trust the order ID from the request; all financial values are calculated server-side.
    $orderId = (int) $request->custom_int5;

    if ($type === 'team') {
      $order = \App\Models\TeamPaymentOrder::with('items')->findOrFail($orderId);
    } else {
      $order = RegistrationOrder::with('items')->findOrFail($orderId);
    }

    $user = auth()->user();
    $wallet = $user?->wallet;

    if (!$user || !$wallet) {
      return back()->withErrors('Wallet not found.');
    }

    // Ownership check
    if ((int) $order->user_id !== (int) $user->id) {
      abort(403, 'Unauthorized order access.');
    }

    // Already paid
    if ($order->pay_status == 1 || $order->payfast_paid) {
      return redirect()
        ->route('frontend.registration.success', ['order' => $orderId])
        ->with('success', 'Order already paid.');
    }

    // Cancelled orders cannot be paid
    if (isset($order->status) && $order->status === 'cancelled') {
      return back()->withErrors('This order has been cancelled and cannot be paid.');
    }

    try {
      app(\App\Services\PlayerEligibilityService::class)->assertOrderEligible($order);
    } catch (\RuntimeException $exception) {
      return back()->withErrors($exception->getMessage());
    }

    // Must have at least one item
    if (!$order->items || $order->items->isEmpty()) {
      return back()->withErrors('This order has no items and cannot be paid.');
    }

    // --- Server-side financial calculations (never trust client input) ---
    $orderTotal    = round((float) $order->items->sum('item_price'), 2);
    $walletBalance = round((float) ($wallet->balance ?? 0), 2);
    $walletReserved  = round(min($walletBalance, $orderTotal), 2);
    $payfastDue      = round($orderTotal - $walletReserved, 2);

    if ($orderTotal > 0 && round($walletReserved + $payfastDue, 2) !== $orderTotal) {
      Log::error('HYBRID PAY: Amount mismatch', [
        'order_id'        => $orderId,
        'order_total'     => $orderTotal,
        'wallet_reserved' => $walletReserved,
        'payfast_due'     => $payfastDue,
      ]);
      abort(500, 'Payment amount calculation error. Please contact support@capetennis.co.za.');
    }

    // Refresh-safe: if already reserved with matching amounts, skip re-reservation
    if ($order->wallet_reserved > 0 && !$order->wallet_debited &&
        round((float) $order->wallet_reserved, 2) === $walletReserved &&
        round((float) $order->payfast_amount_due, 2) === $payfastDue) {

      Log::info('HYBRID PAY: Already reserved', [
        'order_id'        => $order->id,
        'wallet_reserved' => $walletReserved,
      ]);

    } else {

      $order = app(PaymentOrchestrator::class)->initiatePayment(
        $order,
        $walletReserved,
        $payfastDue
      );

      Log::info('HYBRID RESERVED', [
        'order_id'        => $order->id,
        'user_id'         => $user->id,
        'wallet_reserved' => $walletReserved,
        'payfast_due'     => $payfastDue,
      ]);
    }

    // Wallet-only payment
    if ($payfastDue <= 0) {
      return $this->hybridComplete($orderId);
    }

    $remaining = $payfastDue;

    // 🔁 Send to PayFast
    $payfast = new \App\Services\Payfast();
    $payfast->setMode(config('services.payfast.sandbox') ? 0 : 1);

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
      $order = app(PaymentOrchestrator::class)->initiatePayment(
        $order,
        $walletApplied,
        $remaining
      );

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
      app(PaymentFailureReporter::class)->report('registration.wallet_apply', ['order_id' => $orderId], $e);
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

    try {
      app(\App\Services\PlayerEligibilityService::class)->assertOrderEligible($order);
    } catch (\RuntimeException $exception) {
      return redirect()->route('events.index')->withErrors($exception->getMessage());
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

      $walletTx = null;

      if ($order->wallet_reserved > 0) {

        Log::info('HYBRID COMPLETE DEBIT', [
          'order_id' => $order->id,
          'amount' => $order->wallet_reserved,
        ]);

        $eventName = optional($order->items->first()?->category_event?->event)->name ?? 'Event Registration';

        $walletTx = app(WalletService::class)->debit(
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

      $order->wallet_debited     = true;
      $order->payfast_paid       = true;
      $order->pay_status         = 1;
      $order->payment_method     = 'wallet';
      $order->wallet_transaction_id = $walletTx?->id;
      $order->save();

      $this->markOrderPaid($order->id, 'wallet', null, $walletTx);
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
      app(PaymentFailureReporter::class)->report('registration.payfast_itn', ['reason' => 'No order ID received', 'payfast_status' => $paymentStatus, 'amount_gross' => $amountGross]);
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
      app(PaymentFailureReporter::class)->report('registration.payfast_itn', ['reason' => 'Order not found', 'order_id' => $orderId, 'payfast_status' => $paymentStatus, 'amount_gross' => $amountGross]);
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
      app(PaymentFailureReporter::class)->report('registration.payfast_itn', ['reason' => 'Amount mismatch', 'order_id' => $orderId, 'expected_amount' => $expected, 'received_amount' => $amountGross, 'payfast_status' => $paymentStatus]);
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

      $method = (float) $order->wallet_reserved > 0 ? 'hybrid' : 'payfast';
      $order = app(PaymentOrchestrator::class)->finalizePayment($order, [
        'pf_payment_id' => $payfastData['pf_payment_id'] ?? null,
        'payfast_amount_due' => $amountGross,
        'payment_method' => $method,
        'wallet_source_type' => 'event_registration_wallet_payment',
        'wallet_meta' => ['order_id' => $order->id],
      ]);

      $walletTx = $order->wallet_transaction_id
        ? \App\Models\WalletTransaction::find($order->wallet_transaction_id)
        : null;
      $this->markOrderPaid(
        $order->id,
        $method,
        $payfastData['pf_payment_id'] ?? null,
        $walletTx
      );
      app(\App\Services\Masters\MastersInvitationService::class)->confirmPaidOrder($order->fresh());

    } catch (\Throwable $e) {

      Log::error('PAYFAST ITN FAILED', [
        'order_id' => $orderId,
        'message' => $e->getMessage(),
      ]);
      app(PaymentFailureReporter::class)->report('registration.payfast_itn_finalize', ['order_id' => $orderId, 'payfast_payment_id' => $payfastData['pf_payment_id'] ?? null], $e);

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
    $order = RegistrationOrder::with('items.category_event')->find($orderId);

    if (!$order) {
      return redirect()->route('events.index')->withErrors('Order not found.');
    }

    if ((int) $order->user_id !== (int) auth()->id()) {
      abort(403, 'Unauthorized order access.');
    }

    if ($order->pay_status == 1 || $order->payfast_paid) {
      return redirect()
        ->route('frontend.registration.success', ['order' => $orderId])
        ->with('info', 'This order has already been paid.');
    }

    app(PaymentOrchestrator::class)->cancelPayment($order);
    app(\App\Services\Masters\MastersInvitationService::class)->resetCancelledPayment($order, auth()->user());

    Log::info('HYBRID PAYMENT CANCELLED', [
      'order_id' => $orderId,
      'user_id'  => auth()->id(),
    ]);

    $eventId = optional($order->items->first()?->category_event)->event_id;

    return redirect()
      ->route($eventId ? 'events.show' : 'home', $eventId ? ['event' => $eventId] : [])
      ->withErrors('Payment cancelled. No wallet funds were deducted.');
  }

  public function teamHybridPay(Request $request)
  {
    $orderId = (int) $request->custom_int5;

    $user = auth()->user();
    $wallet = $user?->wallet;

    if (!$user || !$wallet) {
      return back()->withErrors('Wallet not found.');
    }

    $order = \App\Models\TeamPaymentOrder::with('event')->findOrFail($orderId);

    if ((int) $order->user_id !== (int) $user->id) {
      abort(403, 'Unauthorized order access.');
    }

    if ($order->pay_status == 1 || $order->payfast_paid) {
      return redirect()->route('event.success', ['id' => $order->event_id])
        ->with('success', 'Order already paid.');
    }

    $orderTotal = round((float) ($order->total_amount ?? 0), 2);
    $walletBalance = round((float) ($wallet->balance ?? 0), 2);
    $walletReserved = round(min($walletBalance, $orderTotal), 2);
    $payfastDue = round($orderTotal - $walletReserved, 2);

    $order = app(\App\Domain\Payments\Services\TeamPaymentService::class)
      ->reservePayment($order, $walletReserved, $payfastDue);

    if ($payfastDue <= 0) {
      return redirect()->route('team.payment.payfast', [
        'team' => $order->team_id,
        'player' => $order->player_id,
        'event' => $order->event_id,
      ]);
    }

    return redirect()->route('team.payment.payfast', [
      'team' => $order->team_id,
      'player' => $order->player_id,
      'event' => $order->event_id,
    ]);
  }

  public function teamHybridComplete(int $orderId)
  {
    $user = auth()->user();

    if (!$user) {
      return redirect()->route('events.index')->withErrors('Session expired.');
    }

    $order = \App\Models\TeamPaymentOrder::lockForUpdate()
      ->with('event', 'user.wallet')
      ->find($orderId);

    if (!$order) {
      return redirect()->route('events.index')->withErrors('Order not found.');
    }

    if ((int) $order->user_id !== (int) $user->id) {
      abort(403, 'Unauthorized order access.');
    }

    if ((int) $order->pay_status === 1) {
      return redirect()->route('event.success', ['id' => $order->event_id])
        ->with('info', 'This order has already been paid.');
    }

    app(\App\Domain\Payments\Services\TeamPaymentService::class)
      ->finalizePayment($order, [
        'payment_method' => 'WALLET',
        'wallet_source_type' => 'team_registration_wallet_payment',
        'wallet_meta' => [
          'order_id' => $order->id,
          'reference' => optional($order->event)->name ?? 'Team Registration',
        ],
        'pf_payment_id' => 'wallet-team-order-' . $order->id,
        'payfast_amount_due' => 0,
      ]);

    return redirect()->route('event.success', ['id' => $order->event_id])
      ->with('success', 'Team payment completed using wallet.');
  }

  public function teamHybridCancel(int $orderId)
  {
    $order = \App\Models\TeamPaymentOrder::find($orderId);

    if (!$order) {
      return redirect()->route('events.index')->withErrors('Order not found.');
    }

    if ((int) $order->user_id !== (int) auth()->id()) {
      abort(403, 'Unauthorized order access.');
    }

    if ($order->pay_status == 1 || $order->payfast_paid) {
      return redirect()->route('event.success', ['id' => $order->event_id])
        ->with('info', 'This order has already been paid.');
    }

    app(\App\Domain\Payments\Services\TeamPaymentService::class)->reservePayment(
      $order,
      0,
      round((float) ($order->total_amount ?? 0), 2)
    );

    return redirect()->route('team.payment.payfast', [
      'team' => $order->team_id,
      'player' => $order->player_id,
      'event' => $order->event_id,
    ])
      ->withErrors('Payment cancelled. No wallet funds were deducted.');
  }

  /**
   * Attach registrations and write a durable payment record on every CER row.
   *
   * @param  int                                    $orderId
   * @param  'wallet'|'payfast'|'hybrid'            $method
   * @param  string|null                            $pfPaymentId   PayFast pf_payment_id (null for wallet-only)
   * @param  \App\Models\WalletTransaction|null     $walletTx      WalletTransaction row (null for payfast-only)
   */
  private function markOrderPaid(
    int $orderId,
    string $method,
    ?string $pfPaymentId = null,
    ?\App\Models\WalletTransaction $walletTx = null
  ): void {
    $items = RegistrationOrderItems::where('order_id', $orderId)->get();

    foreach ($items as $item) {

      $registration = Registration::find($item->registration_id);
      if (!$registration) {
        continue;
      }

      $registration->players()->syncWithoutDetaching([$item->player_id]);

      $registration->categoryEvents()->syncWithoutDetaching([
        $item->category_event_id => [
          'payment_status_id'    => 1,
          'user_id'              => $item->user_id,
          // pf_transaction_id stores the real PayFast pf_payment_id, or null for wallet-only
          'pf_transaction_id'    => $pfPaymentId,
          'payment_method'       => $method,
          'wallet_transaction_id' => $walletTx?->id,
        ],
      ]);
    }
  }
}
