<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;
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

    return view('backend.refunds.bank', compact('refunds', 'completedRefunds', 'pendingTeamRefunds', 'completedTeamRefunds'));
  }

  /**
   * Mark bank refund as completed (auto-refunds via PayFast when applicable)
   */
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
      try {
        $payfast = new \App\Classes\Payfast();
        $amount = $registration->refund_net ?? $registration->refund_gross ?? 0;

        $result = $payfast->refund($pfPaymentId, $amount, 'Event withdrawal refund');

        Log::info('PAYFAST REFUND ATTEMPT (backend registration)', [
          'registration_id' => $registration->id,
          'pf_payment_id' => $pfPaymentId,
          'amount' => $amount,
          'result' => $result,
        ]);

        if (!$result['success']) {
          Log::error('PAYFAST REFUND FAILED (backend registration)', [
            'registration_id' => $registration->id,
            'error' => $result['error'],
          ]);
          return back()->withErrors('PayFast refund failed: ' . ($result['error'] ?? 'Unknown error') . '. Please process manually.');
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
            'amount' => $amount,
          ])
          ->log("PayFast refund R{$amount} processed");

        return back()->with('success', 'Refund processed via PayFast.');

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
        $payfast = new \App\Classes\Payfast();
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
