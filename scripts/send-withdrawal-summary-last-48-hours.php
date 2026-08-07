<?php

use App\Mail\DailyWithdrawalSummaryMail;
use App\Models\CategoryEventRegistration;
use Illuminate\Support\Facades\Mail;

/*
 | One-off catch-up for withdrawals made during the 48 hours immediately
 | before this script is executed.
 |
 | Dry run (default):
 |   php artisan tinker --execute="require 'scripts/send-withdrawal-summary-last-48-hours.php';"
 |
 | Send on production after reviewing the dry-run output:
 |   WITHDRAWAL_MAIL_SEND=true php artisan tinker --execute="require 'scripts/send-withdrawal-summary-last-48-hours.php';"
 */

$send = filter_var(env('WITHDRAWAL_MAIL_SEND', false), FILTER_VALIDATE_BOOL);
$until = now();
$from = $until->copy()->subHours(48);

$withdrawalsByEvent = CategoryEventRegistration::withTrashed()
    ->with(['players', 'user', 'categoryEvent.event.admins', 'categoryEvent.category'])
    ->where('status', 'withdrawn')
    ->whereNotNull('withdrawn_at')
    ->where('withdrawn_at', '>=', $from)
    ->where('withdrawn_at', '<', $until)
    ->get()
    ->filter(fn (CategoryEventRegistration $registration) => $registration->categoryEvent?->event !== null)
    ->groupBy(fn (CategoryEventRegistration $registration) => $registration->categoryEvent->event_id);

echo sprintf(
    "%s: %d withdrawal(s) across %d event(s) from %s to %s.\n",
    $send ? 'SEND MODE' : 'DRY RUN',
    $withdrawalsByEvent->flatten(1)->count(),
    $withdrawalsByEvent->count(),
    $from->format('Y-m-d H:i:s T'),
    $until->format('Y-m-d H:i:s T'),
);

foreach ($withdrawalsByEvent as $eventWithdrawals) {
    $event = $eventWithdrawals->first()->categoryEvent->event;
    $recipients = $event->admins
        ->pluck('email')
        ->filter()
        ->map(fn (string $email) => strtolower(trim($email)))
        ->unique()
        ->values();

    echo sprintf(
        "Event %d - %s: %d withdrawal(s); event admin recipient(s): %s\n",
        $event->id,
        $event->name,
        $eventWithdrawals->count(),
        $recipients->isEmpty() ? '[NONE]' : $recipients->implode(', '),
    );

    foreach ($eventWithdrawals as $registration) {
        $player = $registration->players->first();
        $playerName = $player
            ? trim($player->name.' '.$player->surname)
            : ($registration->user?->name ?? 'Unknown player');

        echo sprintf(
            "  Registration %d: %s, withdrawn %s\n",
            $registration->id,
            $playerName,
            $registration->withdrawn_at->format('Y-m-d H:i:s'),
        );
    }

    if (! $send || $recipients->isEmpty()) {
        continue;
    }

    foreach ($recipients as $email) {
        Mail::to($email)->queue(
            new DailyWithdrawalSummaryMail($event, $eventWithdrawals, $from, $until)
        );
    }
}

echo $send
    ? "Finished: event-admin summary email(s) queued.\n"
    : "Dry run only: no email was queued. Set WITHDRAWAL_MAIL_SEND=true to send.\n";
