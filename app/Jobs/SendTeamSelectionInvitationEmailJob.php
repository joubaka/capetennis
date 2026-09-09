<?php

namespace App\Jobs;

use DateTimeImmutable;
use App\Mail\TeamSelectionInvitationMail;
use App\Models\BulkEmailLog;
use App\Models\TeamSelectionInvitation;
use App\Services\MailAccountManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTeamSelectionInvitationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        return [(new WithoutOverlapping('team-selection-email:'.$this->logId))->releaseAfter(5)->expireAfter(180), new RateLimited('outbound-mail')];
    }

    public function handle(): void
    {
        $log = BulkEmailLog::where('mail_type', 'team_selection_invitation')->find($this->logId);
        if (! $log || $log->sent_at || in_array($log->status, ['sent', 'skipped'], true)) return;
        $invitation = TeamSelectionInvitation::with(['selectionImport.event', 'region', 'team', 'player'])->find($log->related_id);
        if (! $invitation || (int) $invitation->event_id !== $this->eventId
            || $invitation->status !== TeamSelectionInvitation::INVITED
            || ($invitation->selectionImport?->response_deadline && now()->gt($invitation->selectionImport->response_deadline))) {
            $log->markAsSkipped('Invitation is no longer eligible to send.');
            return;
        }
        $sent = Mail::mailer(app(MailAccountManager::class)->getMailer())->to($log->recipient_email)
            ->sendNow(new TeamSelectionInvitationMail($invitation, $log->payload['kind'] ?? 'invitation'));
        if ($sent === null) throw new \RuntimeException('Team invitation was not accepted by the mail transport.');
        $log->update(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null, 'error_message' => null]);
    }

    public function failed(Throwable $exception): void
    {
        $log = BulkEmailLog::find($this->logId);
        if ($log && ! $log->sent_at) $log->markAsFailed($exception->getMessage());
    }
}
