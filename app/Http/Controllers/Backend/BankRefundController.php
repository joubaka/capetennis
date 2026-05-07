<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;
use App\Services\Wallet\WalletService;
use App\Mail\BankDetailsRequestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
   * Show details for a single bank refund.
   */
  public function show(CategoryEventRegistration $registration)
  {
    $registration->load(['categoryEvent.event', 'players', 'registration', 'user']);
    return view('backend.refunds.bank-show', compact('registration'));
  }

  /**
   * Query the PayFast status of a previously issued refund.
   */
  public function queryPayfast(CategoryEventRegistration $registration)
  {
    $payment = $registration->paymentInfo();
    $pfPaymentId = $payment['pf_payment_id'] ?? null;

    Log::info('PAYFAST REFUND QUERY — start', [
      'registration_id'  => $registration->id,
      'status'           => $registration->status,
      'refund_method'    => $registration->refund_method,
      'refund_status'    => $registration->refund_status,
      'pf_payment_id'    => $pfPaymentId,
      'paymentInfo'      => $payment,
      'app_env'          => config('app.env'),
      'passphrase_set'   => !empty(config('services.payfast.passphrase')),
      'passphrase_live_set' => !empty(config('services.payfast.passphrase_live')),
    ]);

    if (empty($pfPaymentId)) {
      Log::warning('PAYFAST REFUND QUERY — no pf_payment_id', [
        'registration_id' => $registration->id,
        'paymentInfo'     => $payment,
      ]);
      return back()->withErrors('No PayFast payment ID found for this registration.');
    }

    $payfast = new \App\Services\Payfast();
    // Use sandbox mode when PAYFAST_SANDBOX=true in .env (for testing API without live transactions)
    if (config('services.payfast.sandbox')) {
      $payfast->setMode(0);
    }
    $result = $payfast->refundQuery($pfPaymentId);

    Log::info('PAYFAST REFUND QUERY — result', [
      'registration_id' => $registration->id,
      'pf_payment_id'   => $pfPaymentId,
      'success'         => $result['success'],
      'data'            => $result['data'],
      'error'           => $result['error'],
    ]);

    if ($result['success']) {
      $status = $result['data']['status'] ?? $result['data']['refund_status'] ?? 'unknown';
      return back()->with('pf_query_result', "PayFast status for {$pfPaymentId}: {$status}");
    }

    return back()->withErrors('PayFast query failed: ' . ($result['error'] ?? 'Unknown error'));
  }

  /**
   * Send one email per user listing ALL their pending bank refunds,
   * with a single signed link to submit bank details for all of them.
   */
  public function requestBankDetails(CategoryEventRegistration $registration)
  {
    $user = $registration->user;

    if (!$user || !$user->email) {
      return back()->withErrors('No email address found for this registration.');
    }

    // Find ALL pending bank refunds for this user — send one email covering all
    $pendingRegistrations = CategoryEventRegistration::with('categoryEvent.event', 'players')
      ->where('user_id', $user->id)
      ->where('refund_method', 'bank')
      ->where('refund_status', 'pending')
      ->get();

    Mail::to($user->email)->queue(new BankDetailsRequestMail($user, $pendingRegistrations));

    Log::info('BANK DETAILS REQUEST EMAIL SENT', [
      'user_id'          => $user->id,
      'email'            => $user->email,
      'registration_ids' => $pendingRegistrations->pluck('id')->toArray(),
    ]);

    $count = $pendingRegistrations->count();
    return back()->with('success', "Bank details request sent to {$user->email} covering {$count} pending refund(s).");
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
      // Query PayFast first to determine the available refund method
      $payfast = new \App\Services\Payfast();
      if (config('services.payfast.sandbox')) {
        $payfast->setMode(0);
      }

      $query = $payfast->refundQuery($pfPaymentId);
      if (!$query['success']) {
        return back()->withErrors('PayFast query failed before refund: ' . ($query['error'] ?? 'Unknown error') . '. Please process manually.');
      }

      $queryData   = $query['data'] ?? [];
      $pfStatus    = $queryData['status'] ?? 'NOT_AVAILABLE';
      $fullMethod  = $queryData['refund_full']['method']    ?? 'NOT_AVAILABLE';
      $partMethod  = $queryData['refund_partial']['method'] ?? 'NOT_AVAILABLE';
      $refundMethod = ($fullMethod !== 'NOT_AVAILABLE') ? $fullMethod : $partMethod;

      Log::info('PAYFAST REFUND QUERY BEFORE COMPLETE', [
        'registration_id' => $registration->id,
        'pf_payment_id'   => $pfPaymentId,
        'status'          => $pfStatus,
        'full_method'     => $fullMethod,
        'partial_method'  => $partMethod,
      ]);

      if ($pfStatus === 'NOT_AVAILABLE') {
        return back()->withErrors("PayFast refund not available for this transaction (status: {$pfStatus}). Please process manually.");
      }

      if ($refundMethod === 'BANK_PAYOUT') {
        // Check if user has submitted bank details
        if (empty($registration->refund_account_name) || empty($registration->refund_bank_name) || empty($registration->refund_account_number)) {
          return back()->withErrors('PayFast requires bank account details for this refund. Use "Request Bank Details" to email the player, then try again once they have submitted their details.');
        }

        // User has provided bank details — include them in the refund body
        $bankBody = [
          'bank_account_holder' => $registration->refund_account_name,
          'bank_name'           => $registration->refund_bank_name,
          'bank_branch_code'    => (int) $registration->refund_branch_code,
          'bank_account_number' => (string) $registration->refund_account_number,
          'bank_account_type'   => $registration->refund_account_type ?? 'current',
        ];
      } else {
        $bankBody = [];
      }

      // For hybrid payments PayFast can only refund its own portion;
      // the wallet contribution is credited back to the user's wallet separately.
      $payfastGross = $payment['gross'] ?? 0;
      $walletPaid   = $payment['wallet_paid'] ?? 0;
      // Use SiteSetting-based fee; wallet portion carries no fee.
      $payfastNet   = $payment['net'] ?? round($payfastGross * 0.90, 2);
      $walletNet    = $walletPaid;

      try {
        $result = $payfast->refund($pfPaymentId, $payfastNet, 'Event withdrawal refund', $bankBody);

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

        // Notify the player that their refund has been processed
        $playerEmail = optional($registration->players->first())->email
                    ?? optional($registration->user)->email;
        if ($playerEmail) {
          Mail::to($playerEmail)->queue(new \App\Mail\BankRefundConfirmationMail($registration));
        }

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

    // Notify the player that their bank refund has been processed
    $playerEmail = optional($registration->players->first())->email
                ?? optional($registration->user)->email;
    if ($playerEmail) {
      Mail::to($playerEmail)->queue(new \App\Mail\BankRefundConfirmationMail($registration));
    }

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
        if (config('services.payfast.sandbox')) {
          $payfast->setMode(0);
        }

        // Query first to determine available refund method
        $query = $payfast->refundQuery($pfPaymentId);
        if (!$query['success']) {
          return back()->withErrors('PayFast query failed before refund: ' . ($query['error'] ?? 'Unknown error') . '. Please process manually.');
        }

        $queryData   = $query['data'] ?? [];
        $pfStatus    = $queryData['status'] ?? 'NOT_AVAILABLE';
        $fullMethod  = $queryData['refund_full']['method']    ?? 'NOT_AVAILABLE';
        $partMethod  = $queryData['refund_partial']['method'] ?? 'NOT_AVAILABLE';
        $refundMethod = ($fullMethod !== 'NOT_AVAILABLE') ? $fullMethod : $partMethod;

        Log::info('PAYFAST REFUND QUERY BEFORE COMPLETE TEAM', [
          'order_id'      => $order->id,
          'pf_payment_id' => $pfPaymentId,
          'status'        => $pfStatus,
          'full_method'   => $fullMethod,
          'partial_method'=> $partMethod,
        ]);

        if ($pfStatus === 'NOT_AVAILABLE') {
          return back()->withErrors("PayFast refund not available for this transaction (status: {$pfStatus}). Please process manually.");
        }

        if ($refundMethod === 'BANK_PAYOUT') {
          return back()->withErrors('PayFast can only refund this transaction via bank payout — bank account details are required. Please process the refund manually via the PayFast merchant dashboard.');
        }

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

  /**
   * Bulk-complete: mark a set of selected pending bank refunds as completed.
   * For each registration that has a PayFast payment ID, a PayFast refund is
   * attempted automatically. Failures are logged but do not stop other items.
   *
   * Expects POST body: registration_ids[] (array of CategoryEventRegistration IDs)
   */
  public function bulkComplete(\Illuminate\Http\Request $request)
  {
    $request->validate([
      'registration_ids'   => 'required|array|min:1',
      'registration_ids.*' => 'integer|exists:category_event_registrations,id',
    ]);

    $ids = $request->input('registration_ids');

    $registrations = CategoryEventRegistration::with(['players', 'user'])
      ->whereIn('id', $ids)
      ->where('refund_method', 'bank')
      ->where('refund_status', 'pending')
      ->get();

    $count = 0;
    foreach ($registrations as $registration) {
      $payment      = $registration->paymentInfo();
      $pfPaymentId  = $payment['pf_payment_id'] ?? null;
      $payfastGross = $payment['gross'] ?? 0;
      $walletPaid   = $payment['wallet_paid'] ?? 0;
      $payfastNet   = $payment['net'] ?? round($payfastGross * 0.90, 2);
      $walletNet    = $walletPaid;

      $refundedViaPayfast = false;

      if (!empty($pfPaymentId) && $payfastGross > 0) {
        try {
          $payfast = new \App\Services\Payfast();
          $result  = $payfast->refund($pfPaymentId, $payfastNet, 'Event withdrawal refund (bulk)');

          Log::info('BULK PAYFAST REFUND ATTEMPT', [
            'registration_id' => $registration->id,
            'pf_payment_id'   => $pfPaymentId,
            'amount'          => $payfastNet,
            'result'          => $result,
          ]);

          if ($result['success']) {
            $refundedViaPayfast = true;

            // For hybrid payments, credit the wallet portion back
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
                      'gross'           => $walletPaid,
                      'fee'             => 0,
                      'method'          => 'hybrid_bank',
                      'initiated_by'    => 'admin_bulk',
                    ]
                  );
                } catch (\Throwable $walletEx) {
                  Log::warning('BULK: hybrid wallet credit failed — manual follow-up required', [
                    'registration_id' => $registration->id,
                    'wallet_net'      => $walletNet,
                    'error'           => $walletEx->getMessage(),
                  ]);
                }
              }
            }
          } else {
            Log::warning('BULK PAYFAST REFUND FAILED — marking completed for manual processing', [
              'registration_id' => $registration->id,
              'error'           => $result['error'] ?? 'unknown',
            ]);
          }
        } catch (\Throwable $e) {
          Log::error('BULK PAYFAST REFUND EXCEPTION', [
            'registration_id' => $registration->id,
            'error'           => $e->getMessage(),
          ]);
        }
      }

      $registration->update([
        'refund_status' => 'completed',
        'refunded_at'   => now(),
      ]);

      activity('refund')
        ->performedOn($registration)
        ->causedBy(auth()->user())
        ->withProperties([
          'registration_id'    => $registration->id,
          'method'             => $refundedViaPayfast ? 'payfast' : 'bank',
          'pf_payment_id'      => $pfPaymentId,
          'initiated_by'       => 'admin',
          'bulk'               => true,
          'payfast_attempted'  => !empty($pfPaymentId) && $payfastGross > 0,
          'payfast_succeeded'  => $refundedViaPayfast,
        ])
        ->log('Bank refund marked completed (bulk)' . ($refundedViaPayfast ? ' — PayFast auto-refund processed' : ''));

      // Notify the player
      $playerEmail = optional($registration->players->first())->email
                  ?? optional($registration->user)->email;
      if ($playerEmail) {
        Mail::to($playerEmail)->queue(new \App\Mail\BankRefundConfirmationMail($registration));
      }

      $count++;
    }

    Log::info('BULK BANK REFUND COMPLETE', [
      'requested_ids' => $ids,
      'completed'     => $count,
    ]);

    return back()->with('success', "{$count} bank refund(s) marked as completed.");
  }

  /**
   * Superadmin manually saves bank details on behalf of the user.
   */
  public function saveBankDetails(Request $request, CategoryEventRegistration $registration)
  {
    $data = $request->validate([
      'refund_account_name'   => ['required', 'string', 'max:100'],
      'refund_bank_name'      => ['required', 'string', 'max:100'],
      'refund_account_number' => ['required', 'string', 'max:30'],
      'refund_branch_code'    => ['required', 'string', 'max:20'],
      'refund_account_type'   => ['required', 'in:current,savings'],
    ]);

    $registration->update($data);

    Log::info('BANK DETAILS SAVED BY SUPERADMIN', [
      'registration_id' => $registration->id,
      'saved_by'        => auth()->id(),
      'data'            => $data,
    ]);

    return back()->with('success', 'Bank details saved successfully.');
  }
}
