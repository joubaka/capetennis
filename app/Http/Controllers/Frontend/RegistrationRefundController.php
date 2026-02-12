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

    Log::info('REFUND REQUEST START', [
      'registration_id' => $registration->id,
      'user_id' => $user?->id,
      'method' => $request->method,
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

    $request->validate([
      'method' => 'required|in:wallet,bank',
      'account_name' => 'required_if:method,bank|string|max:255',
      'bank_name' => 'required_if:method,bank|string|max:255',
      'account_number' => 'required_if:method,bank|string|max:50',
      'branch_code' => 'required_if:method,bank|string|max:20',
      'account_type' => 'required_if:method,bank|in:cheque,savings,business',
    ]);

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

    $gross = $payment['gross'];
    $fee = round($gross * 0.10, 2);
    $net = round($gross - $fee, 2);

    Log::info('REFUND CALCULATED', [
      'registration_id' => $registration->id,
      'gross' => $gross,
      'fee' => $fee,
      'net' => $net,
      'method' => $request->method,
    ]);

    // =====================================================
    // WALLET REFUND
    // =====================================================
    if ($request->method === 'wallet') {

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

        Log::info('WALLET REFUND COMPLETED', [
          'registration_id' => $registration->id,
          'amount' => $net,
        ]);

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

    Log::info('BANK REFUND REQUEST CREATED', [
      'registration_id' => $registration->id,
      'amount' => $net,
      'bank_name' => $request->bank_name,
    ]);

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

    return view('admin.refunds.bank-index', compact(
      'pendingRefunds',
      'completedRefunds'
    ));
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

    $registration->update([
      'refund_status' => 'completed',
      'refunded_at' => now(),
    ]);

    Log::info('BANK REFUND COMPLETED', [
      'registration_id' => $registration->id,
      'amount' => $registration->refund_net,
    ]);

    return back()->with('success', 'Bank refund marked as completed.');
  }


}
