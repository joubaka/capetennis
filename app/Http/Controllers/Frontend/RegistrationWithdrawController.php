<?php

namespace App\Http\Controllers\Frontend;

use App\Domain\Entries\Services\EntryService;
use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class RegistrationWithdrawController extends Controller
{
  /**
   * Withdraw a registration.
   *
   * Routes through EntryService::withdrawEntry() so that:
   *   - draw_group_registrations rows are removed atomically
   *   - audit log is written inside the transaction
   *   - EntryWithdrawn domain event fires after commit
   *
   * Refund selection is handled separately by RegistrationRefundController.
   */
  public function withdraw(Request $request, CategoryEventRegistration $registration)
  {
    $user = auth()->user();

    if (!$user) {
      return redirect()->route('login');
    }

    // Global withdrawal switch is also checked inside EntryService,
    // but we check early here to give a friendlier response.
    if (SiteSetting::get('withdrawal_allowed', '1') !== '1') {
      return back()->withErrors('Withdrawals are currently disabled. Please contact support@capetennis.co.za for assistance.');
    }

    try {
      /** @var array{ok: bool, refund_allowed: bool, message: string} $check */
      $check = app(EntryService::class)->withdrawEntry($registration, $user);
    } catch (\RuntimeException $e) {
      return back()->withErrors($e->getMessage());
    }

    // Send notification emails outside the transaction
    // (queued, so a mail failure will not affect withdrawal state)
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

    return back()->with(
      'success',
      ($check['refund_allowed'] ?? false)
      ? 'Registration withdrawn.'
      : 'Registration withdrawn (no refund – deadline passed).'
    );
  }
}
