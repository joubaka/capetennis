<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Exceptions\RefundAlreadyProcessedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminRegistrationRefundController extends Controller
{
  /**
   * Show the admin refund method chooser for a withdrawn (paid) registration.
   * Only super-users may access this page.
   */
  public function chooseRefund(Event $event, CategoryEventRegistration $registration)
  {
    $user = auth()->user();
    if (! ($user->can('super-user') || (method_exists($user, 'hasRole') && $user->hasRole('super-user')))) {
      abort(403, 'Only super-users can issue refunds.');
    }

    if ($registration->status !== 'withdrawn') {
      return back()->withErrors('Registration must be withdrawn before issuing a refund.');
    }

    $payment = $registration->paymentInfo();

    if (empty($payment)) {
      return redirect()
        ->route('admin.events.entries.new', $event)
        ->with('success', 'No payment information found — no refund required.');
    }

    $walletPaid   = $payment['wallet_paid'] ?? 0;
    $payfastGross = $payment['gross'] ?? 0;
    $gross        = round($payfastGross + $walletPaid, 2);
    $pfPaymentId  = $payment['pf_payment_id'] ?? null;

    $players  = $registration->players;
    $category = optional($registration->categoryEvent?->category)->name ?? '—';

    // Determine who will receive the wallet credit (payer = order owner / parent)
    $payer = optional($registration->payfastTransaction?->order)->user
             ?? $registration->user;

    return view('backend.event.admin-refund', compact(
      'event',
      'registration',
      'gross',
      'walletPaid',
      'payfastGross',
      'pfPaymentId',
      'players',
      'category',
      'payer'
    ));
  }

  /**
   * Process the admin-chosen refund method for a withdrawn registration.
   * Only super-users may access this endpoint.
   */
  public function storeRefund(Request $request, Event $event, CategoryEventRegistration $registration)
  {
    $user = auth()->user();
    if (! ($user->can('super-user') || (method_exists($user, 'hasRole') && $user->hasRole('super-user')))) {
      abort(403, 'Only super-users can issue refunds.');
    }

    // Idempotency: pessimistic lock before any state read or external call
    try {
      DB::transaction(function () use ($registration) {
        $locked = CategoryEventRegistration::lockForUpdate()->findOrFail($registration->id);
        if ($locked->refund_status === CategoryEventRegistration::REFUND_COMPLETED) {
          throw new \App\Exceptions\RefundAlreadyProcessedException('This registration has already been refunded.');
        }
      });
    } catch (\App\Exceptions\RefundAlreadyProcessedException $e) {
      return back()->withErrors($e->getMessage());
    }

    $request->validate([
      'method' => 'required|in:wallet,payfast,none',
      'reason' => 'nullable|string|max:255',
    ]);

    $payment = $registration->paymentInfo();
    $walletPaid   = $payment['wallet_paid'] ?? 0;
    $payfastGross = $payment['gross'] ?? 0;
    $gross        = round($payfastGross + $walletPaid, 2);
    $fee          = SiteSetting::calculateWithdrawalFee($gross); // fixed 10% of gross
    $net          = round($gross - $fee, 2);
    $pfPaymentId  = $payment['pf_payment_id'] ?? null;
    $method       = $request->input('method');

    // Over-refund guard
    if ($method !== 'none' && $registration->maxRefundableAmount() <= 0 && $gross > 0) {
      return back()->withErrors('No refundable amount remaining for this registration.');
    }

    // ── No Refund ──────────────────────────────────────────────────────────
    if ($method === 'none') {
      DB::transaction(function () use ($registration, $request) {
        CategoryEventRegistration::lockForUpdate()->findOrFail($registration->id);
        $registration->update([
          'refund_method' => null,
          'refund_status' => 'not_refunded',
        ]);
      });

      activity('refund')
        ->performedOn($registration)
        ->causedBy(auth()->user())
        ->withProperties([
          'registration_id' => $registration->id,
          'method'          => 'none',
          'reason'          => $request->input('reason') ?? '',
          'initiated_by'    => 'admin',
        ])
        ->log('Admin marked no refund for registration' . ($request->filled('reason') ? ': ' . $request->input('reason') : ''));

      return redirect()
        ->route('admin.events.entries.new', $event)
        ->with('success', 'Withdrawal recorded — no refund issued.');
    }

    if ($gross <= 0) {
      return back()->withErrors('No refundable amount found.');
    }

    // ── Wallet Refund ──────────────────────────────────────────────────────
    if ($method === 'wallet') {
      // Credit the wallet of the person who paid (order owner / parent),
      // not the player — players are children who don't have wallets.
      $payer = optional($registration->payfastTransaction?->order)->user
               ?? $registration->user;

      if (!$payer) {
        return back()->withErrors('Payer not found for this registration.');
      }

      if (!($payer instanceof User)) {
        return back()->withErrors('Wallet refunds are only supported for registered users, not players.');
      }

      // Auto-create wallet if this user doesn't have one yet.
      $wallet = $payer->wallet
                ?? Wallet::create([
                  'payable_type' => User::class,
                  'payable_id'   => $payer->id,
                ]);

      $user = $payer; // used in success message below

      try {
        app(RefundExecutionService::class)->executeWalletRefund(
          $registration,
          $wallet,
          $net,
          'admin_refund',
          $registration->id,
          [
            'registration_id' => $registration->id,
            'event_id'        => $event->id,
            'gross'           => $gross,
            'fee'             => $fee,
            'net'             => $net,
            'method'          => 'wallet',
            'reference'       => $event->name,
            'initiated_by'    => 'admin',
          ],
          [
            'refund_method' => 'wallet',
            'refund_gross'  => $gross,
            'refund_fee'    => $fee,
            'refund_net'    => $net,
          ]
        );

        activity('refund')
          ->performedOn($registration)
          ->causedBy(auth()->user())
          ->withProperties([
            'registration_id' => $registration->id,
            'method'          => 'wallet',
            'gross'           => $gross,
            'fee'             => $fee,
            'net'             => $net,
            'event'           => $event->name,
            'initiated_by'    => 'admin',
          ])
          ->log("Admin wallet refund R{$net} processed (10% fee R{$fee} retained)");

        // Notify the player/payer of the wallet credit
        $playerEmail = optional($registration->players->first())->email
                    ?? optional($registration->user)->email;
        if ($playerEmail && SiteSetting::emailEnabled('player_email_on_wallet_refund')) {
          \Illuminate\Support\Facades\Mail::to($playerEmail)
            ->queue(new \App\Mail\WalletRefundConfirmationMail($registration));
        }

        return redirect()
          ->route('admin.events.entries.new', $event)
          ->with('success', 'Wallet refund of R' . number_format($net, 2) . " credited to {$user->name}'s wallet (10% fee R" . number_format($fee, 2) . ' retained).');

      } catch (RefundAlreadyProcessedException $e) {
        return redirect()
          ->route('admin.events.entries.new', $event)
          ->with('success', 'Refund already processed (wallet).');

      } catch (\Throwable $e) {
        Log::error('ADMIN WALLET REFUND FAILED', [
          'registration_id' => $registration->id,
          'error'           => $e->getMessage(),
        ]);

        return back()->withErrors('Wallet refund failed: ' . $e->getMessage());
      }
    }

    // ── PayFast Refund ─────────────────────────────────────────────────────
    if ($method === 'payfast') {
      if (empty($pfPaymentId)) {
        return back()->withErrors('No PayFast payment ID found — cannot issue PayFast refund.');
      }

      if ($payfastGross <= 0) {
        return back()->withErrors('No PayFast amount to refund — payment was funded entirely by wallet.');
      }

      // HOTFIX 1: Apply 10% withdrawal fee — refund 90% of the PayFast portion only.
      // Cape Tennis retains the 10% fee; PayFast cannot refund wallet contributions.
      $payfastNet = round($payfastGross * 0.90, 2);

      try {
        $payfast = new \App\Services\Payfast();
        $result  = $payfast->refund($pfPaymentId, $payfastNet, 'Admin withdrawal refund');

        Log::info('ADMIN PAYFAST REFUND ATTEMPT', [
          'registration_id' => $registration->id,
          'pf_payment_id'   => $pfPaymentId,
          'payfast_gross'   => $payfastGross,
          'payfast_net'     => $payfastNet,
          'fee'             => $fee,
          'result'          => $result,
        ]);

        if (!$result['success']) {
          Log::error('ADMIN PAYFAST REFUND FAILED', [
            'registration_id' => $registration->id,
            'error'           => $result['error'],
          ]);

          return back()->withErrors('PayFast refund failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        app(RefundExecutionService::class)->executeBankRefund($registration, [
          'refund_method' => 'payfast',
          'refund_gross'  => $gross,
          'refund_fee'    => $fee,
          'refund_net'    => $net,
        ]);

        activity('refund')
          ->performedOn($registration)
          ->causedBy(auth()->user())
          ->withProperties([
            'registration_id' => $registration->id,
            'method'          => 'payfast',
            'pf_payment_id'   => $pfPaymentId,
            'payfast_gross'   => $payfastGross,
            'payfast_net'     => $payfastNet,
            'fee'             => $fee,
            'wallet_paid'     => $walletPaid,
            'gross'           => $gross,
            'event'           => $event->name,
            'initiated_by'    => 'admin',
          ])
          ->log("Admin PayFast refund R{$payfastNet} processed (10% fee R{$fee} retained)");

        // Notify the player that their PayFast refund has been processed
        $playerEmail = optional($registration->players->first())->email
                    ?? optional($registration->user)->email;
        if ($playerEmail && SiteSetting::emailEnabled('player_email_on_payfast_refund')) {
          Mail::to($playerEmail)->queue(new \App\Mail\PayFastRefundConfirmationMail($registration));
        }

        $successMsg = 'PayFast refund of R' . number_format($payfastNet, 2) . ' processed successfully (10% fee R' . number_format($fee, 2) . ' retained).';
        if ($walletPaid > 0) {
          $successMsg .= ' Note: wallet contribution of R' . number_format($walletPaid, 2) . ' was not refunded via PayFast — issue a wallet refund separately if required.';
        }

        return redirect()
          ->route('admin.events.entries.new', $event)
          ->with('success', $successMsg);

      } catch (\Throwable $e) {
        Log::error('ADMIN PAYFAST REFUND EXCEPTION', [
          'registration_id' => $registration->id,
          'error'           => $e->getMessage(),
        ]);

        return back()->withErrors('PayFast refund failed: ' . $e->getMessage());
      }
    }

    return back()->withErrors('Invalid refund method selected.');
  }

  /**
   * Cancel a pending withdrawal: revert the registration status back to active.
   * Only super-users may do this (they are the only ones redirected to the chooser).
   */
  public function cancelWithdraw(Event $event, CategoryEventRegistration $registration)
  {
    $user = auth()->user();
    if (! ($user->can('super-user') || (method_exists($user, 'hasRole') && $user->hasRole('super-user')))) {
      abort(403, 'Only super-users can cancel a withdrawal.');
    }

    if ($registration->status !== 'withdrawn') {
      return redirect()
        ->route('admin.events.entries.new', $event)
        ->with('info', 'Registration is not in a withdrawn state — nothing to revert.');
    }

    $registration->update([
      'status'        => 'active',
      'withdrawn_at'  => null,
      'refund_status' => null,
      'refund_method' => null,
      'refund_gross'  => 0,
      'refund_fee'    => 0,
      'refund_net'    => 0,
      'refunded_at'   => null,
    ]);

    activity('withdrawal')
      ->performedOn($registration)
      ->causedBy($user)
      ->withProperties([
        'registration_id' => $registration->id,
        'event'           => $event->name,
        'initiated_by'    => 'admin',
      ])
      ->log('Admin cancelled withdrawal — registration reverted to active');

    // Notify the player that their registration has been reinstated
    $playerEmail = optional($registration->players->first())->email
                ?? optional($registration->user)->email;
    if ($playerEmail && SiteSetting::emailEnabled('player_email_on_reinstatement')) {
      Mail::to($playerEmail)->queue(new \App\Mail\RegistrationReinstatedMail($registration));
    }

    return redirect()
      ->route('admin.events.entries.new', $event)
      ->with('success', 'Withdrawal cancelled — registration restored.');
  }
}
