<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use Illuminate\Http\Request;

class RegistrationWithdrawController extends Controller
{
  /**
   * Withdraw a registration.
   * Refund selection handled separately.
   */
  public function withdraw(Request $request, CategoryEventRegistration $registration)
  {
    $user = auth()->user();

    if (!$user) {
      return redirect()->route('login');
    }

    $check = $registration->canWithdraw($user);

    if (!$check['ok']) {
      return back()->withErrors($check['message']);
    }

    if ($registration->status === 'withdrawn') {
      return back()->withErrors('This registration is already withdrawn.');
    }

    // -------------------------
    // WITHDRAW
    // -------------------------
    $registration->update([
      'status' => 'withdrawn',
      'withdrawn_at' => now(),
    ]);

    // -------------------------
    // REFUND DECISION
    // -------------------------
    if (
      $registration->is_paid &&
      ($check['refund_allowed'] ?? false)
    ) {
      return redirect()
        ->route('registrations.refund.choose', $registration)
        ->with('success', 'Registration withdrawn. Please choose a refund method.');
    }

    // Late or unpaid withdrawal
    return back()->with(
      'success',
      ($check['refund_allowed'] ?? false)
      ? 'Registration withdrawn.'
      : 'Registration withdrawn (no refund – deadline passed).'
    );
  }
}
