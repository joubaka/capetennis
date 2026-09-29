<?php

namespace App\Jobs;

use DateTimeImmutable;
use App\Mail\TeamSelectionInvitationMail;
use App\Models\BulkEmailLog;
use App\Models\TeamSelectionInvitation;
use App\Services\MailAccountManager;
use App\Services\TeamSelection\TeamSelectionContactService;
use App\Services\TeamSelection\TeamSelectionInvitationService;
use App\Domain\Teams\Services\ExternalTeamRosterService;
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
        $kind = $log->payload['kind'] ?? 'invitation';
        $isCustomEmail = str_starts_with($kind, 'custom_');
        $eligibleStatuses = match ($kind) {
            'custom_payment_update' => [TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT],
            'custom_status_update' => [TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT],
            'custom_paid_update' => [TeamSelectionInvitation::PAID_CONFIRMED],
            default => [TeamSelectionInvitation::INVITED],
        };
        $customRegistrationEmail = $kind === 'custom_invitation';
        $deadline = $kind === 'custom_payment_update'
            ? $invitation?->effectivePaymentDeadline()
            : $invitation?->effectiveResponseDeadline();
        $currentContactEmail = $invitation
            ? app(TeamSelectionContactService::class)->primaryEmail($invitation->player)
            : null;
        $customContextIsStale = $isCustomEmail && (
            ! $invitation?->selectionImport
            || $invitation->selectionImport->status !== 'sent'
            || (int) $invitation->selectionImport->event_id !== (int) $invitation->event_id
            || (int) $invitation->import_id !== (int) $invitation->selectionImport->id
            || $invitation->roster_rank === null
            || ! is_string($currentContactEmail)
            || ! hash_equals((string) $log->recipient_email, $currentContactEmail)
            || app(TeamSelectionInvitationService::class)->customEmailKind($invitation) !== $kind
        );
        if (! $invitation || (int) $invitation->event_id !== $this->eventId
            || ! in_array($invitation->status, $eligibleStatuses, true)
            || $customContextIsStale
            || ($customRegistrationEmail && ! app(ExternalTeamRosterService::class)->registrationIsOpen($invitation->selectionImport->event))
            || ($kind === 'custom_payment_update' && $deadline && now()->gt($deadline))
            || (! str_starts_with($kind, 'custom_') && $deadline && now()->gt($deadline))) {
            $log->markAsSkipped('Invitation is no longer eligible to send.');
            return;
        }
        $sent = Mail::mailer(app(MailAccountManager::class)->getMailer())->to($log->recipient_email)
            ->sendNow(new TeamSelectionInvitationMail(
                $invitation,
                $log->payload['kind'] ?? 'invitation',
                $log->payload['campaign'] ?? [],
            ));
        if ($sent === null) throw new \RuntimeException('Team invitation was not accepted by the mail transport.');
        $log->update(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null, 'error_message' => null]);
    }

    public function failed(Throwable $exception): void
    {
        $log = BulkEmailLog::find($this->logId);
        if ($log && ! $log->sent_at) $log->markAsFailed($exception->getMessage());
    }
}
