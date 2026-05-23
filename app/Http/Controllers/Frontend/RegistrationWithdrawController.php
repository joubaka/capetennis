<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // Respect the global withdrawal switch (admins bypass via CategoryEventController)
    if (SiteSetting::get('withdrawal_allowed', '1') !== '1') {
      return back()->withErrors('Withdrawals are currently disabled. Please contact support@capetennis.co.za for assistance.');
    }

    $check = $registration->canWithdraw($user);

    if (!$check['ok']) {
      return back()->withErrors($check['message']);
    }

    if ($registration->status === 'withdrawn') {
      return back()->withErrors('This registration is already withdrawn.');
    }

    // -------------------------
    // WITHDRAW inside DB transaction so the state update and activity log
    // are atomic. Emails are sent afterwards (cannot be rolled back).
    // -------------------------
    DB::transaction(function () use ($registration, $user, $check) {
      $registration->markWithdrawn($user, 'self');
    });

    // Send notification emails outside the transaction
    // (queued, so a mail failure won't roll back the withdrawal)
    $registration->sendWithdrawalEmails('self');

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
