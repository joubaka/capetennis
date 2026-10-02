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
    public bool $manualRetryOnly = false;

    /**
     * Create a new job instance.
     */
    public function __construct(int $logId, bool $manualRetryOnly = false)
    {
        $this->logId = $logId;
        $this->manualRetryOnly = $manualRetryOnly;
        $this->tries = $manualRetryOnly ? 0 : config('mail.bulk_mail.max_tries', 3);
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
        return [new RateLimited('outbound-mail')];
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
        if (in_array($log->status, ['sent', 'skipped', 'sending', 'acceptance_unknown'])) {
            Log::info('[SendBulkEmailJob] Email already processed, skipping', [
                'log_id' => $log->id,
                'status' => $log->status,
                'recipient_ref' => $this->recipientReference($log),
            ]);
            return;
        }

        if ($this->manualRetryOnly && ! BulkEmailLog::whereKey($log->id)->where('status', 'queued')->whereNull('sent_at')->update(['status' => 'sending'])) {
            return;
        }
        if (! $this->manualRetryOnly) $log->update(['status' => 'sending']);
        $transportAccepted = false;

        Log::info('[SendBulkEmailJob] Sending bulk email', [
            'log_id' => $log->id,
            'mail_type' => $log->mail_type,
            'recipient_ref' => $this->recipientReference($log),
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
                $this->syncRankingReviewRecipient($log, 'skipped', 'The ranking review circulation is no longer available.');
                return;
            }
            $mailable->with('outbound_mail_log_id', $log->id);

            // Only stored approved previews supply this flag; message content cannot set it.
            $reviewed = !empty($log->payload['event_communication_batch_id'])
                ? \App\Models\EventCommunicationBatch::whereKey($log->payload['event_communication_batch_id'])->where('event_id', $log->payload['event_id'] ?? 0)->whereNotNull('approved_at')->exists()
                : (!empty($log->payload['preview_id']) && \App\Models\TrialMailPreview::whereKey($log->payload['preview_id'])->where('event_id', $log->payload['event_id'] ?? 0)->whereNotNull('committed_at')->exists());
            $mailable->with('event_mail_reviewed', $reviewed);
            if (! empty($log->payload['event_id'])) {
                $mailable->with('review_event', \App\Models\Event::find($log->payload['event_id']));
            }

            // Send the email
            $mailTransport = Mail::mailer($mailer);
            $sent = $mailTransport->to($log->recipient_email)->sendNow($mailable);
            $transportAccepted = $sent !== null;

            // Mark as sent
            $log->recordTransportResult($sent, $mailTransport->getSymfonyTransport(), $mailer);
            $this->syncRankingReviewRecipient($log, 'sent');

            Log::info('[SendBulkEmailJob] Email sent successfully', [
                'log_id' => $log->id,
                'recipient_ref' => $this->recipientReference($log),
                'mailer' => $mailer,
            ]);

        } catch (\Throwable $e) {
            if ($transportAccepted) {
                try {
                    $log->update(['status' => 'acceptance_unknown', 'sent_at' => now(), 'error_message' => 'Transport returned successfully, but receipt storage failed. Check the mail server before attempting another send.']);
                } catch (\Throwable) {
                    Log::critical('Email transport returned successfully but evidence could not be stored; do not resend without server verification.', ['log_id' => $log->id]);
                }
                return;
            }
            $redactedError = $this->redactedError($e, $log);
            Log::error('[SendBulkEmailJob] Failed to send email', [
                'log_id' => $log->id,
                'recipient_ref' => $this->recipientReference($log),
                'error' => $redactedError,
                'attempt' => $this->attempts(),
            ]);

            // Mark as failed if this is the last attempt
            if (! $log->sent_at) {
                $log->markAsFailed($redactedError);
                $this->syncRankingReviewRecipient($log, 'failed', $redactedError);
            }

            // Rate-limit releases remain retryable; explicit SMTP failures require admin action.
            return;
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
            case 'trial_communication':
            case 'team_email':
            case 'region_email':
            case 'team_selection_registration_clothing_reminder':
            case 'team_selection_incomplete_clothing_reminder':
                return (new \App\Mail\BulkEventMail(
                    $payload['subject'] ?? 'Event Update',
                    $payload['body'] ?? $payload['message'] ?? '',
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

            case 'masters_invitation':
                $invitation = !empty($payload['invitation_id'])
                    ? \App\Models\MastersInvitation::with(['batch.event', 'categoryEvent.category', 'player'])->find($payload['invitation_id'])
                    : null;
                return $invitation ? new \App\Mail\MastersInvitationMail($invitation, $payload['kind'] ?? 'invitation') : null;

            case 'ranking_review':
                $campaign = !empty($payload['campaign_id'])
                    ? \App\Models\RankingReviewCampaign::with('series')->find($payload['campaign_id'])
                    : null;
                if (!$campaign || in_array($campaign->status, ['superseded'], true)) {
                    return null;
                }
                if (!app(\App\Domain\Ranking\Services\RankingReviewCirculationService::class)->snapshotMatches($campaign)) {
                    $campaign->update(['status' => 'superseded']);
                    return null;
                }
                $recipient = \App\Models\RankingReviewRecipient::where('bulk_email_log_id', $log->id)->first();
                return new \App\Mail\RankingReviewMail($campaign, $recipient?->player_names ?? [], $fromAddress);

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

            // Generic email types used by EmailController
            case 'event_email':
            case 'nomination_email':
            case 'category_email':
            case 'series_email':
            case 'unregistered_team_email':
            case 'unregistered_event_email':
            case 'unregistered_region_email':
            case 'generic_bulk_email':
                // Use generic SendEmailTest mailable with custom subject/body
                return new \App\Mail\SendEmailTest([
                    'subject' => $payload['subject'] ?? 'Email from Cape Tennis',
                    'message' => $payload['message'] ?? $payload['body'] ?? '',
                    'fromName' => $payload['from_name'] ?? 'Cape Tennis Admin',
                    'fromEmail' => $fromAddress,
                    'replyTo' => $payload['reply_to'] ?? 'info@capetennis.co.za',
                ]);

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
        $log = BulkEmailLog::find($this->logId);
        $redactedError = $this->redactedError($exception, $log);
        Log::critical('[SendBulkEmailJob] Job failed permanently', [
            'log_id' => $this->logId,
            'recipient_ref' => $log ? $this->recipientReference($log) : null,
            'error' => $redactedError,
            'attempts' => $this->attempts(),
        ]);

        // Ensure log is marked as failed
        if ($log && ! $log->sent_at && ! in_array($log->status, ['failed', 'skipped', 'sending', 'acceptance_unknown'], true)) {
            $log->markAsFailed($redactedError);
            $this->syncRankingReviewRecipient($log, 'failed', $redactedError);
        }
    }

    private function recipientReference(BulkEmailLog $log): string
    {
        return substr(hash_hmac(
            'sha256',
            'bulk-email-recipient-reference|'.mb_strtolower(trim($log->recipient_email)),
            (string) config('app.key'),
        ), 0, 12);
    }

    private function redactedError(\Throwable $exception, ?BulkEmailLog $log): string
    {
        if (! $log || $log->recipient_email === '') {
            return $exception->getMessage();
        }

        return str_ireplace($log->recipient_email, '[REDACTED_RECIPIENT]', $exception->getMessage());
    }

    private function syncRankingReviewRecipient(BulkEmailLog $log, string $status, ?string $error = null): void
    {
        if ($log->mail_type !== 'ranking_review') {
            return;
        }

        \App\Models\RankingReviewRecipient::where('bulk_email_log_id', $log->id)->update([
            'status' => $status,
            'error_message' => $error,
            'updated_at' => now(),
        ]);
    }
}
