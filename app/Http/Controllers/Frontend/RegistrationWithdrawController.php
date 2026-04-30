<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
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

    $check = $registration->canWithdraw($user);

    if (!$check['ok']) {
      return back()->withErrors($check['message']);
    }

    if ($registration->status === 'withdrawn') {
      return back()->withErrors('This registration is already withdrawn.');
    }

    // Resolve player & event name for logging (before transaction)
    $player = $registration->players->first();
    $eventName = optional($registration->categoryEvent?->event)->name ?? 'Event';
    $categoryName = optional($registration->categoryEvent?->category)->name ?? '';

    // -------------------------
    // WITHDRAW inside DB transaction so the state update and activity log
    // are atomic. Emails are sent afterwards (cannot be rolled back).
    // -------------------------
    DB::transaction(function () use ($registration, $user, $check, $player, $eventName, $categoryName) {
      $registration->update([
        'status' => 'withdrawn',
        'withdrawn_at' => now(),

        // Default financial state — refund path chosen separately
        'refund_status' => 'not_refunded',
        'refund_method' => null,
        'refund_gross' => 0,
        'refund_fee' => 0,
        'refund_net' => 0,
        'refunded_at' => null,
      ]);

      activity('withdrawal')
        ->performedOn($registration)
        ->causedBy($user)
        ->withProperties([
          'registration_id' => $registration->id,
          'event' => $eventName,
          'category' => $categoryName,
          'player' => $player ? trim($player->name . ' ' . $player->surname) : '',
          'refund_allowed' => $check['refund_allowed'] ?? false,
        ])
        ->log("Withdrew from {$eventName} ({$categoryName})");
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
