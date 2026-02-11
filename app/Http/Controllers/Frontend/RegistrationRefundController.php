<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
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


    if (!empty($payment['pf_transaction_id'])) {

      return redirect()
        ->route('events.show', $registration->categoryEvent->event_id)
        ->with('success', 'Registration withdrawn (no payment to refund).');
    }

    $gross = $payment['gross'];
    $fee = round($gross * 0.10, 2);
    $net = round($gross - $fee, 2);

    return view('frontend.registrations.choose-refund', compact(
      'registration',
      'gross',
      'fee',
      'net'
    ));
  }

  /**
   * Save refund choice (wallet / bank)
   */
  public function store(Request $request, CategoryEventRegistration $registration)
  {
    $user = auth()->user();

    if (!$user || $registration->user_id !== $user->id) {
      abort(403);
    }

    $request->validate([
      'method' => 'required|in:wallet,bank',
    ]);

    if (!$registration->is_paid) {
      return back()->withErrors('Not eligible for refund.');
    }

    $payment = $registration->paymentInfo();

    $gross = $payment['gross'];
    $fee = round($gross * 0.10, 2);
    $net = round($gross - $fee, 2);

    // -------------------------
    // WALLET = IMMEDIATE
    // -------------------------
    if ($request->method === 'wallet') {

      try {
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
          ]
        );

        $registration->update([
          'refund_method' => 'wallet',
          'refund_status' => 'completed',
          'refund_gross' => $gross,
          'refund_fee' => $fee,
          'refund_net' => $net,
          'refunded_at' => now(),
        ]);

        return redirect()
          ->route('events.show', $registration->categoryEvent->event_id)
          ->with('success', 'Refund credited to your wallet.');

      } catch (DuplicateTransactionException $e) {
        return back()->with('success', 'Refund already processed.');
      }
    }

    // -------------------------
    // BANK / PAYFAST = PENDING
    // -------------------------
    $registration->update([
      'refund_method' => 'bank',
      'refund_status' => 'pending',
      'refund_gross' => $gross,
      'refund_fee' => $fee,
      'refund_net' => $net,
    ]);

    return redirect()
      ->route('events.show', $registration->categoryEvent->event_id)
      ->with('success', 'Refund request submitted.');
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
}
