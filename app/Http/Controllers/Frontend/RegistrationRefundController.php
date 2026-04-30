<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefundMethodRequest;
use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;

class RegistrationRefundController extends Controller
{
  /**
   * Show refund choice screen
   */
  public function choose(CategoryEventRegistration $registration)
  {
    $user = auth()->user();

    if (!$user || $registration->user_id !== $user->id) {
      abort(403);
    }

    // Must be withdrawn first
    if ($registration->status !== 'withdrawn') {
      return back()->withErrors('Registration must be withdrawn first.');
    }

    $payment = $registration->paymentInfo();

    // No payment found - nothing to refund
    if (empty($payment) || !isset($payment['gross'])) {
      return redirect()
        ->route('events.show', $registration->categoryEvent->event_id)
        ->with('success', 'Registration withdrawn (no payment to refund).');
    }

    // Include wallet portion in total paid
    $walletPaid = $payment['wallet_paid'] ?? 0;
    $payfastGross = $payment['gross'];
    $gross = round($payfastGross + $walletPaid, 2);
    $fee = round($gross * 0.10, 2);
    $net = round($gross - $fee, 2);

    return view('frontend.registrations.choose-refund', compact(
      'registration',
      'gross',
      'fee',
      'net',
      'walletPaid',
      'payfastGross'
    ));
  }

  /**
   * Save refund choice (wallet / bank)
   */

  public function store(RefundMethodRequest $request, CategoryEventRegistration $registration)
  {
    $user = auth()->user();

    Log::info('REFUND REQUEST START', [
      'registration_id' => $registration->id,
      'user_id' => $user?->id,
      'method' => $request->input('method'),
    ]);

    if (!$user || (int) $registration->user_id !== (int) $user->id) {
      Log::warning('REFUND BLOCKED: Ownership mismatch', [
        'registration_user_id' => $registration->user_id,
        'auth_user_id' => $user?->id,
      ]);
      abort(403);
    }

    // Duplicate protection
    if ($registration->isRefundCompleted()) {
      Log::info('REFUND BLOCKED: Already completed', [
        'registration_id' => $registration->id,
      ]);
      return back()->with('success', 'Refund already processed.');
    }

    if ($registration->isRefundPending()) {
      Log::info('REFUND BLOCKED: Already pending', [
        'registration_id' => $registration->id,
      ]);
      return back()->with('success', 'Refund already requested.');
    }

    if (!$registration->is_paid) {
      Log::warning('REFUND BLOCKED: Not paid', [
        'registration_id' => $registration->id,
      ]);
      return back()->withErrors('Not eligible for refund.');
    }

    $payment = $registration->paymentInfo();

    if (empty($payment)) {
      Log::error('REFUND FAILED: Payment info missing', [
        'registration_id' => $registration->id,
      ]);
      return back()->withErrors('Payment information not found.');
    }

    // Include wallet portion in total paid
    $walletPaid = $payment['wallet_paid'] ?? 0;
    $payfastGross = $payment['gross'];
    $gross = round($payfastGross + $walletPaid, 2);
    $fee = round($gross * 0.10, 2);
    $net = round($gross - $fee, 2);

    Log::info('REFUND CALCULATED', [
      'registration_id' => $registration->id,
      'payfast_gross' => $payfastGross,
      'wallet_paid' => $walletPaid,
      'gross' => $gross,
      'fee' => $fee,
      'net' => $net,
      'method' => $request->input('method'),
    ]);

    // =====================================================
    // WALLET REFUND
    // =====================================================
    if ($request->input('method') === 'wallet') {

      try {

        \DB::transaction(function () use ($user, $registration, $gross, $fee, $net) {

          app(WalletService::class)->credit(
            $user->wallet,
            (float) $net,
            'event_registration_refund',
            $registration->id,
            [
              'registration_id' => $registration->id,
              'event_id' => $registration->categoryEvent->event_id,
              'gross' => $gross,
              'fee' => $fee,
              'method' => 'wallet',
              'reference' => optional($registration->categoryEvent?->event)->name ?? 'Event Refund',
            ]
          );

          $registration->update([
            'refund_method' => 'wallet',
            'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
            'refund_gross' => $gross,
            'refund_fee' => $fee,
            'refund_net' => $net,
            'refunded_at' => now(),
          ]);
        });

        $refEventName = optional($registration->categoryEvent?->event)->name ?? 'Event Refund';

        activity('wallet')
          ->performedOn($registration)
          ->causedBy($user)
          ->withProperties([
            'type' => 'credit',
            'amount' => $net,
            'gross' => $gross,
            'fee' => $fee,
            'reference' => $refEventName,
            'registration_id' => $registration->id,
          ])
          ->log("Wallet credited R{$net} for refund – {$refEventName}");

        Log::info('WALLET REFUND COMPLETED', [
          'registration_id' => $registration->id,
          'amount' => $net,
        ]);

        activity('refund')
          ->performedOn($registration)
          ->causedBy($user)
          ->withProperties([
            'registration_id' => $registration->id,
            'method' => 'wallet',
            'gross' => $gross,
            'fee' => $fee,
            'net' => $net,
            'event' => optional($registration->categoryEvent?->event)->name ?? '',
          ])
          ->log("Wallet refund R{$net} processed");

        return redirect()
          ->route('events.show', $registration->categoryEvent->event_id)
          ->with('success', 'Refund credited to your wallet.');

      } catch (DuplicateTransactionException $e) {

        Log::warning('WALLET REFUND DUPLICATE — syncing registration', [
          'registration_id' => $registration->id,
        ]);

        // 🔧 Sync model state with wallet reality
        $registration->update([
          'refund_method' => 'wallet',
          'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
          'refunded_at' => now(),
        ]);

        return redirect()
          ->route('events.show', $registration->categoryEvent->event_id)
          ->with('success', 'Refund already processed.');
      } catch (\Throwable $e) {

        Log::error('WALLET REFUND FAILED', [
          'registration_id' => $registration->id,
          'error' => $e->getMessage(),
        ]);

        return back()->withErrors('Refund failed. Please contact support.');
      }
    }

    // =====================================================
    // BANK REFUND
    // =====================================================

    $registration->update([
      'refund_method' => 'bank',
      'refund_status' => CategoryEventRegistration::REFUND_PENDING,
      'refund_gross' => $gross,
      'refund_fee' => $fee,
      'refund_net' => $net,
      'refund_account_name' => $request->account_name,
      'refund_bank_name' => $request->bank_name,
      'refund_account_number' => $request->account_number,
      'refund_branch_code' => $request->branch_code,
      'refund_account_type' => $request->account_type,
    ]);

    // ── Auto-refund via PayFast if original payment was PayFast ──
    $pfPaymentId = $payment['pf_payment_id'] ?? null;

    // For hybrid payments PayFast can only refund its own portion;
    // the wallet contribution must be credited back to the wallet separately.
    $payfastNet = round($payfastGross * 0.90, 2);
    $walletNet  = round($walletPaid  * 0.90, 2);

    if (!empty($pfPaymentId) && $payfastGross > 0) {
      try {
        $payfast = new \App\Services\Payfast();
        $result = $payfast->refund($pfPaymentId, $payfastNet, 'Event withdrawal refund');

        Log::info('PAYFAST AUTO REFUND ATTEMPT', [
          'registration_id' => $registration->id,
          'pf_payment_id' => $pfPaymentId,
          'amount' => $payfastNet,
          'result' => $result,
        ]);

        if ($result['success']) {
          // For hybrid payments, credit the wallet portion back to the user's wallet
          // since it cannot be returned via the PayFast API.
          if ($walletNet > 0) {
            try {
              app(WalletService::class)->credit(
                $user->wallet,
                $walletNet,
                'event_registration_bank_wallet_refund',
                $registration->id,
                [
                  'registration_id' => $registration->id,
                  'event_id' => $registration->categoryEvent->event_id,
                  'gross' => $walletPaid,
                  'fee' => round($walletPaid * 0.10, 2),
                  'method' => 'hybrid_bank',
                  'reference' => optional($registration->categoryEvent?->event)->name ?? 'Event Refund',
                ]
              );

              Log::info('HYBRID BANK REFUND: wallet portion credited', [
                'registration_id' => $registration->id,
                'wallet_net' => $walletNet,
              ]);
            } catch (\Throwable $walletEx) {
              Log::warning('HYBRID BANK REFUND: wallet credit failed — manual follow-up required', [
                'registration_id' => $registration->id,
                'wallet_net' => $walletNet,
                'error' => $walletEx->getMessage(),
              ]);
            }
          }

          $registration->update([
            'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
            'refunded_at' => now(),
          ]);

          activity('refund')
            ->performedOn($registration)
            ->causedBy($user)
            ->withProperties([
              'registration_id' => $registration->id,
              'method' => 'payfast',
              'pf_payment_id' => $pfPaymentId,
              'gross' => $gross,
              'fee' => $fee,
              'net' => $net,
              'payfast_net' => $payfastNet,
              'wallet_net' => $walletNet,
              'event' => optional($registration->categoryEvent?->event)->name ?? '',
            ])
            ->log("PayFast auto refund R{$payfastNet} processed" . ($walletNet > 0 ? ", wallet credited R{$walletNet}" : ''));

          $successMsg = 'Refund of R' . number_format($payfastNet, 2) . ' processed via PayFast. It may take 3–5 business days to reflect.';
          if ($walletNet > 0) {
            $successMsg .= ' R' . number_format($walletNet, 2) . ' has been credited to your wallet.';
          }

          return redirect()
            ->route('events.show', $registration->categoryEvent->event_id)
            ->with('success', $successMsg);
        }

        // PayFast returned error — leave pending for admin
        Log::warning('PAYFAST AUTO REFUND FAILED — falling back to manual', [
          'registration_id' => $registration->id,
          'error' => $result['error'],
        ]);

      } catch (\Throwable $e) {
        Log::error('PAYFAST AUTO REFUND EXCEPTION — falling back to manual', [
          'registration_id' => $registration->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    Log::info('BANK REFUND REQUEST CREATED', [
      'registration_id' => $registration->id,
      'amount' => $net,
      'bank_name' => $request->bank_name,
    ]);

    activity('refund')
      ->performedOn($registration)
      ->causedBy($user)
      ->withProperties([
        'registration_id' => $registration->id,
        'method' => 'bank',
        'gross' => $gross,
        'fee' => $fee,
        'net' => $net,
        'bank' => $request->bank_name,
        'event' => optional($registration->categoryEvent?->event)->name ?? '',
      ])
      ->log("Bank refund R{$net} requested");

    return redirect()
      ->route('events.show', $registration->categoryEvent->event_id)
      ->with('success', 'Bank refund request submitted. It will be processed manually.');
  }

  /**
   * Process wallet refund
   */
  public function process(CategoryEventRegistration $registration)
  {
    $user = auth()->user();

    if (!$user || $registration->user_id !== $user->id) {
      abort(403);
    }

    if ($registration->refund_status !== 'pending') {
      return back()->withErrors('Refund already processed or not eligible.');
    }

    if ($registration->refund_method !== 'wallet') {
      return back()->withErrors('Invalid refund method.');
    }

    $payment = $registration->paymentInfo();

    if (!$registration->is_paid) {
      return back()->withErrors('This registration was not paid online.');
    }

    $wallet = $user->wallet;

    if (!$wallet) {
      return back()->withErrors('Wallet not found.');
    }

    try {
      app(WalletService::class)->credit(
        $wallet,
        (float) $registration->refund_net,
        'event_registration_refund',
        $registration->id,
        [
          'registration_id' => $registration->id,
          'event_id' => $registration->categoryEvent->event_id,
          'gross' => $registration->refund_gross,
          'fee' => $registration->refund_fee,
          'method' => 'wallet',
        ]
      );

      $registration->update([
        'refund_status' => 'completed',
        'refunded_at' => now(),
      ]);

      Log::info('WALLET REFUND COMPLETED', [
        'registration_id' => $registration->id,
        'user_id' => $user->id,
        'amount' => $registration->refund_net,
      ]);

      return back()->with('success', 'Refund credited to your wallet.');

    } catch (DuplicateTransactionException $e) {
      return back()->with('success', 'Refund already processed.');
    } catch (\Throwable $e) {
      Log::error('WALLET REFUND FAILED', [
        'registration_id' => $registration->id,
        'error' => $e->getMessage(),
      ]);

      return back()->withErrors('Refund failed. Please contact support.');
    }
  }
  /**
   * Admin: List pending bank refunds
   */
  public function bankIndex()
  {
    $pendingRefunds = CategoryEventRegistration::where('refund_method', 'bank')
      ->where('refund_status', 'pending')
      ->with(['user', 'categoryEvent.event'])
      ->latest()
      ->get();

    $completedRefunds = CategoryEventRegistration::where('refund_method', 'bank')
      ->where('refund_status', 'completed')
      ->with(['user', 'categoryEvent.event'])
      ->latest()
      ->get();

    // Team refunds (bank) — show alongside registration refunds for admins
    $pendingTeamRefunds = TeamPaymentOrder::where('refund_method', 'bank')
      ->where('refund_status', 'pending')
      ->with(['user', 'team', 'player', 'event'])
      ->latest()
      ->get();

    $completedTeamRefunds = TeamPaymentOrder::where('refund_method', 'bank')
      ->where('refund_status', 'completed')
      ->with(['user', 'team', 'player', 'event'])
      ->latest()
      ->get();

    Log::debug('BANK INDEX: pending counts', [
      'pending_registration_refunds' => $pendingRefunds->count(),
      'pending_team_refunds' => $pendingTeamRefunds->count(),
    ]);

    // Detailed debug dump (IDs + key fields) to help trace missing rows in view
    try {
      Log::debug('BANK INDEX: pending team refunds data', [
        'team_refunds' => $pendingTeamRefunds->map(function ($r) {
          return [
            'id' => $r->id,
            'team_id' => $r->team_id,
            'player_id' => $r->player_id,
            'event_id' => $r->event_id,
            'refund_status' => $r->refund_status,
            'refund_net' => $r->refund_net,
            'refund_account_name' => $r->refund_account_name,
            'refund_bank_name' => $r->refund_bank_name,
            'updated_at' => optional($r->updated_at)->toDateTimeString(),
          ];
        })->toArray()
      ]);
    } catch (\Throwable $e) {
      Log::error('BANK INDEX debug dump failed', ['error' => $e->getMessage()]);
    }

    return view('admin.refunds.bank-index', compact(
      'pendingRefunds',
      'completedRefunds',
      'pendingTeamRefunds',
      'completedTeamRefunds'
    ));
  }

  // Admin: mark a team refund complete (proxy to Backend controller logic)
  public function bankCompleteTeam(\App\Models\TeamPaymentOrder $order)
  {
    if ($order->refund_method !== 'bank') {
      return back()->withErrors('Invalid refund type.');
    }

    if ($order->refund_status !== 'pending') {
      return back()->withErrors('Refund already processed.');
    }

    // If originally paid via PayFast, attempt automatic refund
    $pfPaymentId = $order->payfast_pf_payment_id ?? null;

    if (!empty($pfPaymentId)) {
      try {
        $payfast = new \App\Services\Payfast();
        $amount = $order->refund_net ?? $order->refund_gross ?? 0;

        $result = $payfast->refund($pfPaymentId, $amount, 'Team withdrawal refund');

        Log::info('PAYFAST REFUND ATTEMPT (team)', [
          'order_id' => $order->id,
          'pf_payment_id' => $pfPaymentId,
          'amount' => $amount,
          'result' => $result,
        ]);

        if (!$result['success']) {
          Log::error('PAYFAST REFUND FAILED (team)', [
            'order_id' => $order->id,
            'error' => $result['error'],
          ]);
          return back()->withErrors('PayFast refund failed: ' . ($result['error'] ?? 'Unknown error') . '. Please process manually.');
        }

        $order->update([
          'refund_status' => 'completed',
          'refunded_at' => now(),
        ]);

        activity('refund')
          ->performedOn($order)
          ->causedBy(auth()->user())
          ->withProperties([
            'order_id' => $order->id,
            'method' => 'payfast',
            'pf_payment_id' => $pfPaymentId,
            'amount' => $amount,
          ])
          ->log("Team PayFast refund R{$amount} processed");

        return back()->with('success', 'Team refund processed via PayFast.');

      } catch (\Throwable $e) {
        Log::error('PAYFAST REFUND EXCEPTION (team)', [
          'order_id' => $order->id,
          'error' => $e->getMessage(),
        ]);
        return back()->withErrors('PayFast refund failed. Please process manually.');
      }
    }

    // No PayFast transaction — mark as completed (manual)
    $order->update([
      'refund_status' => 'completed',
      'refunded_at' => now(),
    ]);

    return back()->with('success', 'Team bank refund marked as completed.');
  }


  /**
   * Admin: Show bank details for a refund
   */
  public function bankShow(CategoryEventRegistration $registration)
  {
    if ($registration->refund_method !== 'bank') {
      abort(404);
    }

    return view('admin.refunds.bank-show', compact('registration'));
  }

  /**
   * Admin: Mark bank refund as completed
   */
  public function bankComplete(CategoryEventRegistration $registration)
  {
    if ($registration->refund_status !== 'pending') {
      return back()->withErrors('Refund already processed.');
    }

    if ($registration->refund_method !== 'bank') {
      return back()->withErrors('Invalid refund method.');
    }

    // If this registration was paid via PayFast, attempt an automatic refund
    $payment = $registration->paymentInfo();
    $pfPaymentId = $payment['pf_payment_id'] ?? null;

    if (!empty($pfPaymentId)) {
      // For hybrid payments PayFast can only refund its own portion;
      // the wallet contribution is credited back to the user's wallet separately.
      $payfastGross = $payment['gross'] ?? 0;
      $walletPaid   = $payment['wallet_paid'] ?? 0;
      $payfastNet   = round($payfastGross * 0.90, 2);
      $walletNet    = round($walletPaid  * 0.90, 2);

      try {
        $payfast = new \App\Services\Payfast();

        $result = $payfast->refund($pfPaymentId, $payfastNet, 'Event withdrawal refund');

        Log::info('PAYFAST REFUND ATTEMPT (registration)', [
          'registration_id' => $registration->id,
          'pf_payment_id' => $pfPaymentId,
          'amount' => $payfastNet,
          'result' => $result,
        ]);

        if (!$result['success']) {
          Log::error('PAYFAST REFUND FAILED (registration)', [
            'registration_id' => $registration->id,
            'error' => $result['error'],
          ]);
          return back()->withErrors('PayFast refund failed: ' . ($result['error'] ?? 'Unknown error') . '. Please process manually.');
        }

        // For hybrid payments, credit the wallet portion back to the user's wallet.
        if ($walletNet > 0) {
          $refundUser = $registration->user;
          if ($refundUser && $refundUser->wallet) {
            try {
              app(WalletService::class)->credit(
                $refundUser->wallet,
                $walletNet,
                'event_registration_bank_wallet_refund',
                $registration->id,
                [
                  'registration_id' => $registration->id,
                  'gross' => $walletPaid,
                  'fee' => round($walletPaid * 0.10, 2),
                  'method' => 'hybrid_bank',
                  'initiated_by' => 'admin',
                ]
              );
            } catch (\Throwable $walletEx) {
              Log::warning('HYBRID BANK REFUND: wallet credit failed — manual follow-up required', [
                'registration_id' => $registration->id,
                'wallet_net' => $walletNet,
                'error' => $walletEx->getMessage(),
              ]);
            }
          }
        }

        $registration->update([
          'refund_status' => 'completed',
          'refunded_at' => now(),
        ]);

        activity('refund')
          ->performedOn($registration)
          ->causedBy(auth()->user())
          ->withProperties([
            'registration_id' => $registration->id,
            'method' => 'payfast',
            'pf_payment_id' => $pfPaymentId,
            'payfast_net' => $payfastNet,
            'wallet_net' => $walletNet,
          ])
          ->log("PayFast refund R{$payfastNet} processed" . ($walletNet > 0 ? ", wallet credited R{$walletNet}" : ''));

        return back()->with('success', 'Refund processed via PayFast.' . ($walletNet > 0 ? ' Wallet portion of R' . number_format($walletNet, 2) . ' credited.' : ''));

      } catch (\Throwable $e) {
        Log::error('PAYFAST REFUND EXCEPTION (registration)', [
          'registration_id' => $registration->id,
          'error' => $e->getMessage(),
        ]);
        return back()->withErrors('PayFast refund failed. Please process manually.');
      }
    }

    // No PayFast transaction — mark as completed (manual bank refund processed)
    $registration->update([
      'refund_status' => 'completed',
      'refunded_at' => now(),
    ]);

    Log::info('BANK REFUND COMPLETED (manual)', [
      'registration_id' => $registration->id,
      'amount' => $registration->refund_net,
    ]);

    return back()->with('success', 'Bank refund marked as completed.');
  }


}
