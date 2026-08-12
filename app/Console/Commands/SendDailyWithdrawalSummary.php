<?php

namespace App\Console\Commands;

use App\Mail\DailyWithdrawalSummaryMail;
use App\Models\CategoryEventRegistration;
use App\Models\User;
use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailyWithdrawalSummary extends Command
{
    protected $signature = 'withdrawals:send-daily-summary';

    protected $description = 'Email the previous day withdrawal summary to super-users and relevant event admins';

    public function handle(): int
    {
        if (! SiteSetting::emailEnabled('admin_email_on_daily_withdrawal_summary')) {
            $this->info('Daily withdrawal summary email is disabled.');
            return self::SUCCESS;
        }
        $from = now()->subDay()->startOfDay();
        $until = $from->copy()->addDay();

        $withdrawals = CategoryEventRegistration::withTrashed()
            ->with(['players', 'user', 'categoryEvent.event.admins', 'categoryEvent.category'])
            ->where('status', 'withdrawn')
            ->whereNotNull('withdrawn_at')
            ->where('withdrawn_at', '>=', $from)
            ->where('withdrawn_at', '<', $until)
            ->get()
            ->groupBy(fn (CategoryEventRegistration $registration) => $registration->categoryEvent?->event_id);

        if ($withdrawals->isEmpty()) {
            $this->info('No withdrawals to report.');

            return self::SUCCESS;
        }

        $superUserEmails = User::role('super-user')
            ->pluck('email')
            ->filter()
            ->map('strtolower')
            ->values();

        $messages = 0;

        foreach ($withdrawals as $eventWithdrawals) {
            $event = $eventWithdrawals->first()?->categoryEvent?->event;

            if (! $event) {
                continue;
            }

            $recipients = $superUserEmails
                ->merge($event->admins->pluck('email')->filter()->map('strtolower'))
                ->unique()
                ->values();

            foreach ($recipients as $email) {
                Mail::to($email)->queue(
                    new DailyWithdrawalSummaryMail($event, $eventWithdrawals, $from, $until)
                );
                $messages++;
            }
        }

        $this->info("Queued {$messages} withdrawal summary email(s).");

        return self::SUCCESS;
    }
}
