<?php

namespace App\Console\Commands;

use App\Mail\BankRefundReminderMail;
use App\Models\CategoryEventRegistration;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BulkMailDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBankRefundReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refunds:send-bank-reminders
                            {--days=30 : Days a bank refund must be pending before a reminder is sent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails for bank refunds that have been pending longer than the specified number of days.';

    public function handle(): int
    {
        if (! SiteSetting::emailEnabled('player_email_on_bank_refund_reminder')) {
            $this->info('Bank refund reminder email is disabled.');
            return self::SUCCESS;
        }

        $days = (int) $this->option('days');

        $stale = CategoryEventRegistration::where('refund_method', 'bank')
            ->where('refund_status', 'pending')
            ->where('updated_at', '<=', now()->subDays($days))
            ->with(['players', 'user', 'categoryEvent.event', 'categoryEvent.category'])
            ->get();

        if ($stale->isEmpty()) {
            $this->info("No bank refunds pending for more than {$days} days.");
            return self::SUCCESS;
        }

        $this->info("Found {$stale->count()} stale bank refund(s) — sending reminders.");

        // Super-user emails for admin awareness
        $superUserEmails = User::role('super-user')
            ->pluck('email')
            ->filter()
            ->map('strtolower')
            ->unique()
            ->values();

        $dispatcher = app(BulkMailDispatcher::class);
        $totalDispatched = 0;

        foreach ($stale as $registration) {
            try {
                // Collect recipients for this registration
                $recipients = collect();

                // Notify the player / payer
                $playerEmail = optional($registration->players->first())->email
                            ?? optional($registration->user)->email;

                if ($playerEmail) {
                    $recipients->push($playerEmail);
                }

                // Also notify super-users
                foreach ($superUserEmails as $email) {
                    $recipients->push($email);
                }

                // Dispatch via BulkMailDispatcher with throttling
                $stats = $dispatcher->dispatch(
                    mailType: 'bank_refund_reminder',
                    related: $registration,
                    recipients: $recipients,
                    payload: [
                        'registration_id' => $registration->id,
                    ],
                    allowDuplicates: false
                );

                $totalDispatched += $stats['queued'];

                Log::info('BANK REFUND REMINDER DISPATCHED', [
                    'registration_id' => $registration->id,
                    'player_email'    => $playerEmail,
                    'stats'           => $stats,
                ]);

                $this->line("  → Reminder queued for registration #{$registration->id} ({$stats['queued']} emails)");
            } catch (\Throwable $e) {
                Log::error('BANK REFUND REMINDER FAILED', [
                    'registration_id' => $registration->id,
                    'error'           => $e->getMessage(),
                ]);
                $this->error("  ✗ Failed for registration #{$registration->id}: " . $e->getMessage());
            }
        }

        $this->info("Done. {$totalDispatched} reminder emails queued with throttling.");

        return self::SUCCESS;
    }
}
