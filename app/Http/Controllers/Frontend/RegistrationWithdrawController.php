<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    // Resolve player & event name for logging
    $player = $registration->players->first();
    $eventName = optional($registration->categoryEvent?->event)->name ?? 'Event';
    $categoryName = optional($registration->categoryEvent?->category)->name ?? '';

    // -------------------------
    // WITHDRAW (ALWAYS)
    // -------------------------
    $registration->update([
      'status' => 'withdrawn',
      'withdrawn_at' => now(),

      // 🔒 DEFAULT FINANCIAL STATE
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

    // ── Email player confirmation ──
    if ($player && !empty($player->email)) {
      try {
        app(\App\Http\Controllers\Backend\EmailController::class)->sendToOwner([
          'subject' => "Withdrawal confirmed: {$eventName}",
          'body' => "This is to confirm that " . trim($player->name . ' ' . $player->surname) . " has been withdrawn from {$eventName} ({$categoryName}).",
          'replyTo' => null,
        ], 'smtp');

        // Notify the player directly
        $mailer = app(\App\Services\MailAccountManager::class)->getMailer();
        app(\App\Http\Controllers\Backend\EmailController::class)->sendToIndividual([
          'email' => $player->email,
          'subject' => "Withdrawal confirmed: {$eventName}",
          'body' => "Hi " . trim($player->name) . ",\n\nYour withdrawal from {$eventName} ({$categoryName}) has been confirmed.\n\n" .
            (($check['refund_allowed'] ?? false)
              ? "A refund will be processed. Please follow the link you have been given to choose your refund method."
              : "No refund is applicable as the withdrawal deadline has passed.") .
            "\n\nFor any queries please contact support@capetennis.co.za.",
          'fromName' => 'Cape Tennis',
          'replyTo' => 'support@capetennis.co.za',
        ], $mailer);
      } catch (\Throwable $e) {
        Log::warning('WITHDRAWAL EMAIL FAILED', [
          'registration_id' => $registration->id,
          'player_email' => $player->email,
          'error' => $e->getMessage(),
        ]);
      }
    }

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
