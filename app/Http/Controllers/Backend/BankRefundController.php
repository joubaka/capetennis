<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BankRefundController extends Controller
{
  /**
   * List pending bank refunds
   */
  public function index()
  {
    $refunds = CategoryEventRegistration::with([
      'categoryEvent.event',
      'players',
      'registration',
      'user',
    ])
      ->where('status', 'withdrawn')
      ->where('refund_method', 'bank')
      ->where('refund_status', 'pending')
      ->orderBy('updated_at')
      ->get();

    $completedRefunds = CategoryEventRegistration::with([
      'categoryEvent.event',
      'players',
      'registration',
      'user',
    ])
      ->where('refund_method', 'bank')
      ->where('refund_status', 'completed')
      ->orderBy('updated_at')
      ->get();

    // Team refunds
    $pendingTeamRefunds = TeamPaymentOrder::with(['team', 'player', 'user', 'event'])
      ->where('refund_method', 'bank')
      ->where('refund_status', 'pending')
      ->orderBy('updated_at')
      ->get();

    $completedTeamRefunds = TeamPaymentOrder::with(['team', 'player', 'user', 'event'])
      ->where('refund_method', 'bank')
      ->where('refund_status', 'completed')
      ->orderBy('updated_at')
      ->get();

    \Log::debug('BACKEND BANK INDEX counts', [
      'pending_registration_refunds' => $refunds->count(),
      'pending_team_refunds' => $pendingTeamRefunds->count(),
    ]);

    try {
      \Log::debug('BACKEND BANK INDEX team refunds data', [
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
      \Log::error('BACKEND BANK INDEX debug dump failed', ['error' => $e->getMessage()]);
    }

    return view('backend.refunds.bank', compact('refunds', 'completedRefunds', 'pendingTeamRefunds', 'completedTeamRefunds'));
  }

  /**
   * Query the PayFast status of a previously issued refund.
   */
  public function queryPayfast(CategoryEventRegistration $registration)
  {
    $payment = $registration->paymentInfo();
    $pfPaymentId = $payment['pf_payment_id'] ?? null;

    if (empty($pfPaymentId)) {
      return back()->withErrors('No PayFast payment ID found for this registration.');
    }

    $payfast = new \App\Services\Payfast();
    $result = $payfast->refundQuery($pfPaymentId);

    Log::info('PAYFAST REFUND QUERY (backend registration)', [
      'registration_id' => $registration->id,
      'pf_payment_id'   => $pfPaymentId,
      'result'          => $result,
    ]);

    if ($result['success']) {
      $status = $result['data']['status'] ?? $result['data']['refund_status'] ?? 'unknown';
      return back()->with('pf_query_result', "PayFast status for {$pfPaymentId}: {$status}");
    }

    return back()->withErrors('PayFast query failed: ' . ($result['error'] ?? 'Unknown error'));
  }


  public function complete(CategoryEventRegistration $registration)
  {
    if ($registration->refund_method !== 'bank') {
      return back()->withErrors('Invalid refund type.');
    }

    if ($registration->refund_status !== 'pending') {
      return back()->withErrors('Refund already processed.');
    }

    // If originally paid via PayFast, attempt automatic refund
    $payment = $registration->paymentInfo();
    $pfPaymentId = $payment['pf_payment_id'] ?? null;

    if (!empty($pfPaymentId)) {
      // For hybrid payments PayFast can only refund its own portion;
      // the wallet contribution is credited back to the user's wallet separately.
      $payfastGross = $payment['gross'] ?? 0;
      $walletPaid   = $payment['wallet_paid'] ?? 0;
      // Use SiteSetting-based fee; wallet portion carries no fee.
      $payfastNet   = $payment['net'] ?? round($payfastGross * 0.90, 2);
      $walletNet    = $walletPaid;

      try {
        $payfast = new \App\Services\Payfast();

        $result = $payfast->refund($pfPaymentId, $payfastNet, 'Event withdrawal refund');

        Log::info('PAYFAST REFUND ATTEMPT (backend registration)', [
          'registration_id' => $registration->id,
          'pf_payment_id' => $pfPaymentId,
          'amount' => $payfastNet,
          'result' => $result,
        ]);

        if (!$result['success']) {
          Log::error('PAYFAST REFUND FAILED (backend registration)', [
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
                  'fee' => 0,   // wallet portion carries no fee
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
        Log::error('PAYFAST REFUND EXCEPTION (backend registration)', [
          'registration_id' => $registration->id,
          'error' => $e->getMessage(),
        ]);
        return back()->withErrors('PayFast refund failed. Please process manually.');
      }
    }

    // No PayFast transaction — mark as completed (manual)
    $registration->update([
      'refund_status' => 'completed',
      'refunded_at' => now(),
    ]);

    return back()->with('success', 'Bank refund marked as completed.');
  }

  /**
   * Mark a team bank refund as completed (auto-refunds via PayFast when applicable)
   */
  public function completeTeam(TeamPaymentOrder $order)
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

        Log::info('PAYFAST REFUND ATTEMPT (backend team)', [
          'order_id' => $order->id,
          'pf_payment_id' => $pfPaymentId,
          'amount' => $amount,
          'result' => $result,
        ]);

        if (!$result['success']) {
          Log::error('PAYFAST REFUND FAILED (backend team)', [
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
        Log::error('PAYFAST REFUND EXCEPTION (backend team)', [
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
}
