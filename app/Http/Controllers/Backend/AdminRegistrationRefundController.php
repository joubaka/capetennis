<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    $request->validate([
      'method' => 'required|in:wallet,payfast,none',
      'reason' => 'nullable|string|max:255',
    ]);

    if ($registration->refund_status === CategoryEventRegistration::REFUND_COMPLETED) {
      return back()->withErrors('This registration has already been refunded.');
    }

    $payment = $registration->paymentInfo();
    $walletPaid   = $payment['wallet_paid'] ?? 0;
    $payfastGross = $payment['gross'] ?? 0;
    $gross        = round($payfastGross + $walletPaid, 2);
    $pfPaymentId  = $payment['pf_payment_id'] ?? null;
    $method       = $request->input('method');

    // ── No Refund ──────────────────────────────────────────────────────────
    if ($method === 'none') {
      $registration->update([
        'refund_method' => null,
        'refund_status' => 'not_refunded',
      ]);

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
        DB::transaction(function () use ($registration, $wallet, $gross, $event) {
          app(WalletService::class)->credit(
            $wallet,
            $gross,
            'admin_refund',
            $registration->id,
            [
              'registration_id' => $registration->id,
              'event_id'        => $event->id,
              'gross'           => $gross,
              'fee'             => 0,
              'method'          => 'wallet',
              'reference'       => $event->name,
              'initiated_by'    => 'admin',
            ]
          );

          $registration->update([
            'refund_method' => 'wallet',
            'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
            'refund_gross'  => $gross,
            'refund_fee'    => 0,
            'refund_net'    => $gross,
            'refunded_at'   => now(),
          ]);
        });

        activity('refund')
          ->performedOn($registration)
          ->causedBy(auth()->user())
          ->withProperties([
            'registration_id' => $registration->id,
            'method'          => 'wallet',
            'gross'           => $gross,
            'net'             => $gross,
            'event'           => $event->name,
            'initiated_by'    => 'admin',
          ])
          ->log("Admin wallet refund R{$gross} processed");

        // Notify the player/payer of the wallet credit
        $playerEmail = optional($registration->players->first())->email
                    ?? optional($registration->user)->email;
        if ($playerEmail) {
          \Illuminate\Support\Facades\Mail::to($playerEmail)
            ->queue(new \App\Mail\WalletRefundConfirmationMail($registration));
        }

        return redirect()
          ->route('admin.events.entries.new', $event)
          ->with('success', 'Wallet refund of R' . number_format($gross, 2) . " credited to {$user->name}'s wallet.");

      } catch (DuplicateTransactionException $e) {
        $registration->update([
          'refund_method' => 'wallet',
          'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
          'refunded_at'   => now(),
        ]);

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

      try {
        $payfast = new \App\Services\Payfast();
        // Only refund the PayFast portion — PayFast cannot refund wallet contributions.
        $result  = $payfast->refund($pfPaymentId, $payfastGross, 'Admin withdrawal refund');

        Log::info('ADMIN PAYFAST REFUND ATTEMPT', [
          'registration_id' => $registration->id,
          'pf_payment_id'   => $pfPaymentId,
          'amount'          => $payfastGross,
          'result'          => $result,
        ]);

        if (!$result['success']) {
          Log::error('ADMIN PAYFAST REFUND FAILED', [
            'registration_id' => $registration->id,
            'error'           => $result['error'],
          ]);

          return back()->withErrors('PayFast refund failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        $registration->update([
          'refund_method' => 'payfast',
          'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
          'refund_gross'  => $gross,
          'refund_fee'    => 0,
          'refund_net'    => $gross,
          'refunded_at'   => now(),
        ]);

        activity('refund')
          ->performedOn($registration)
          ->causedBy(auth()->user())
          ->withProperties([
            'registration_id' => $registration->id,
            'method'          => 'payfast',
            'pf_payment_id'   => $pfPaymentId,
            'payfast_gross'   => $payfastGross,
            'wallet_paid'     => $walletPaid,
            'gross'           => $gross,
            'event'           => $event->name,
            'initiated_by'    => 'admin',
          ])
          ->log("Admin PayFast refund R{$payfastGross} processed");

        $successMsg = 'PayFast refund of R' . number_format($payfastGross, 2) . ' processed successfully.';
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

    return redirect()
      ->route('admin.events.entries.new', $event)
      ->with('success', 'Withdrawal cancelled — registration restored.');
  }
}
