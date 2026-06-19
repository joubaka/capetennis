<?php

namespace App\Jobs;

use App\Models\BulkEmailLog;
use App\Services\MailAccountManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBulkEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The bulk email log ID.
     */
    public int $logId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $logId)
    {
        $this->logId = $logId;
        $this->tries = config('mail.bulk_mail.max_tries', 3);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return config('mail.bulk_mail.backoff', [60, 300, 900]);
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new RateLimited('bulk-email')];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Load the log record fresh from database
        $log = BulkEmailLog::find($this->logId);

        if (!$log) {
            Log::error('[SendBulkEmailJob] Log record not found', [
                'log_id' => $this->logId,
            ]);
            return;
        }

        // Skip if already sent or skipped
        if (in_array($log->status, ['sent', 'skipped'])) {
            Log::info('[SendBulkEmailJob] Email already processed, skipping', [
                'log_id' => $log->id,
                'status' => $log->status,
                'recipient' => $log->recipient_email,
            ]);
            return;
        }

        Log::info('[SendBulkEmailJob] Sending bulk email', [
            'log_id' => $log->id,
            'mail_type' => $log->mail_type,
            'recipient' => $log->recipient_email,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Determine which mailer to use
            $mailer = app(MailAccountManager::class)->getMailer();

            // Get from address based on mailer
            $fromAddress = match ($mailer) {
                'noreply1' => 'noreply1@capetennis.co.za',
                'noreply2' => 'noreply2@capetennis.co.za',
                default => 'noreply@capetennis.co.za',
            };

            // Build the mailable based on mail_type
            $mailable = $this->buildMailable($log, $fromAddress);

            if (!$mailable) {
                $log->markAsSkipped('Unknown mail type: ' . $log->mail_type);
                return;
            }

            // Send the email
            Mail::mailer($mailer)->to($log->recipient_email)->send($mailable);

            // Mark as sent
            $log->markAsSent();

            Log::info('[SendBulkEmailJob] Email sent successfully', [
                'log_id' => $log->id,
                'recipient' => $log->recipient_email,
                'mailer' => $mailer,
            ]);

        } catch (\Throwable $e) {
            Log::error('[SendBulkEmailJob] Failed to send email', [
                'log_id' => $log->id,
                'recipient' => $log->recipient_email,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Mark as failed if this is the last attempt
            if ($this->attempts() >= $this->tries) {
                $log->markAsFailed($e->getMessage());
            }

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Build the appropriate mailable based on mail_type.
     */
    protected function buildMailable(BulkEmailLog $log, string $fromAddress)
    {
        $payload = $log->payload ?? [];

        switch ($log->mail_type) {
            case 'tournament_announcement':
            case 'event_announcement':
                return (new \App\Mail\AnnouncementMail([
                    'event' => $payload['event_name'] ?? 'Event',
                    'title' => $payload['title'] ?? '',
                    'message' => $payload['message'] ?? '',
                    'email' => $log->recipient_email,
                ]))
                    ->from($fromAddress, 'Cape Tennis')
                    ->replyTo('info@capetennis.co.za', 'Cape Tennis');

            case 'bulk_event_mail':
                return (new \App\Mail\BulkEventMail(
                    $payload['subject'] ?? 'Event Update',
                    $payload['body'] ?? '',
                    $payload['from_name'] ?? 'Cape Tennis',
                    $payload['reply_to'] ?? 'info@capetennis.co.za'
                ));

            case 'bank_refund_reminder':
                // Load the registration fresh
                if (!empty($payload['registration_id'])) {
                    $registration = \App\Models\CategoryEventRegistration::with([
                        'categoryEvent.event',
                        'categoryEvent.category',
                        'players',
                        'user'
                    ])->find($payload['registration_id']);

                    if ($registration) {
                        return new \App\Mail\BankRefundReminderMail($registration);
                    }
                }
                return null;

            case 'violation_notification':
                // Load fresh data
                if (!empty($payload['player_id']) && !empty($payload['violation_id'])) {
                    $player = \App\Models\Player::find($payload['player_id']);
                    $violation = \App\Models\PlayerViolation::find($payload['violation_id']);
                    $recorder = !empty($payload['recorder_id'])
                        ? \App\Models\User::find($payload['recorder_id'])
                        : null;

                    if ($player && $violation) {
                        return new \App\Mail\ViolationNotificationMail($player, $violation, $recorder);
                    }
                }
                return null;

            case 'suspension_alert':
                // Load fresh data
                if (!empty($payload['player_id']) && !empty($payload['suspension_id'])) {
                    $player = \App\Models\Player::find($payload['player_id']);
                    $suspension = \App\Models\PlayerSuspension::find($payload['suspension_id']);

                    if ($player && $suspension) {
                        return new \App\Mail\SuspensionAlertMail($player, $suspension);
                    }
                }
                return null;

            default:
                Log::warning('[SendBulkEmailJob] Unknown mail type', [
                    'mail_type' => $log->mail_type,
                    'log_id' => $log->id,
                ]);
                return null;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('[SendBulkEmailJob] Job failed permanently', [
            'log_id' => $this->logId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Ensure log is marked as failed
        $log = BulkEmailLog::find($this->logId);
        if ($log && $log->status !== 'failed') {
            $log->markAsFailed($exception->getMessage());
        }
    }
}
