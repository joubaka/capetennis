<?php

namespace App\Http\Controllers\Frontend;

use App\Domain\Entries\Services\EntryService;
use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class RegistrationWithdrawController extends Controller
{
  public function __construct(private EntryService $entryService) {}

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

    try {
      $check = $this->entryService->withdrawEntry($registration, $user);
    } catch (\RuntimeException $e) {
      return back()->withErrors($e->getMessage());
    }

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
