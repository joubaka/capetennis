<?php

namespace App\Jobs;

use App\Mail\MastersInvitationMail;
use App\Models\BulkEmailLog;
use App\Models\MastersInvitation;
use App\Services\MailAccountManager;
use App\Services\Masters\MastersInvitationMailEligibility;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendMastersInvitationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Rate-limit/lock releases are not sending failures. The fixed deadline
    // bounds those retries; maxExceptions separately bounds real failures.
    public int $tries = 0;
    public int $maxExceptions = 3;
    public int $timeout = 120;
    public int $retryDeadline;

    public function __construct(public int $logId, public int $eventId)
    {
        $this->retryDeadline = now()->addDay()->getTimestamp();
        $this->afterCommit();
    }

    public function retryUntil(): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$this->retryDeadline);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('masters-email:'.$this->logId))->releaseAfter(5)->expireAfter(180),
            new RateLimited('outbound-mail'),
        ];
    }

    public function handle(): void
    {
        $log = $this->emailLog();
        if (!$log || $log->sent_at || in_array($log->status, ['sent', 'skipped'], true)) {
            return;
        }

        $invitation = MastersInvitation::with(['batch.event', 'categoryEvent.category', 'player'])
            ->find($log->related_id);
        $reason = app(MastersInvitationMailEligibility::class)->reason($log, $invitation, $this->eventId);
        if ($reason) {
            $log->markAsSkipped($reason);
            return;
        }

        try {
            $mailer = app(MailAccountManager::class)->getMailer();
            $sent = Mail::mailer($mailer)->to($log->recipient_email)->sendNow(
                new MastersInvitationMail($invitation, $log->payload['kind'] ?? 'invitation')
            );
            if ($sent === null) {
                throw new RuntimeException('Masters email sending was cancelled before transport acceptance.');
            }

            $log->update([
                'status' => 'sent', 'sent_at' => now(),
                'failed_at' => null, 'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            // Retain the underlying transport error even if a later reservation
            // terminates with a generic retry-deadline exception.
            $log->update(['error_message' => $exception->getMessage()]);
            Log::error('Masters invitation email attempt failed', [
                'log_id' => $this->logId, 'event_id' => $this->eventId,
                'attempt' => $this->attempts(), 'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $log = $this->emailLog();
        // A late/duplicate failed job must never overwrite a successful send.
        if ($log && !$log->sent_at && !in_array($log->status, ['sent', 'skipped'], true)) {
            $log->markAsFailed($log->error_message ?: $exception->getMessage());
        }
    }

    private function emailLog(): ?BulkEmailLog
    {
        return BulkEmailLog::where('mail_type', 'masters_invitation')
            ->where('related_type', MastersInvitation::class)
            ->whereIn('related_id', MastersInvitation::query()->select('id')
                ->whereHas('batch', fn ($query) => $query->where('event_id', $this->eventId)))
            ->find($this->logId);
    }
}
