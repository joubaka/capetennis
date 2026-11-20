<?php

namespace App\Jobs;

use App\Models\{BulkEmailLog, CampNominee};
use App\Services\{InvitationMailSecurity, MailAccountManager};
use App\Services\Camps\{CampAdmission, CampInvitations};
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\{RateLimited, WithoutOverlapping};
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCampInvitation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 0;
    public int $maxExceptions = 1;
    public int $retryDeadline;
    public function retryUntil(): \DateTimeImmutable { return new \DateTimeImmutable('@'.$this->retryDeadline); }
    public int $timeout = 120;
    public function __construct(public int $logId, public int $eventId) { $this->retryDeadline = now()->addDay()->timestamp; $this->afterCommit(); }
    public function middleware(): array { return [(new WithoutOverlapping('camp-mail:'.$this->logId))->releaseAfter(5)->expireAfter(180), new RateLimited('outbound-mail')]; }
    public function handle(): void
    {
        $log = BulkEmailLog::where('mail_type', 'camp_invitation')->find($this->logId);
        if (! $log || $log->status !== 'queued' || $log->sent_at) return;
        if (! BulkEmailLog::whereKey($log->id)->where('status', 'queued')->whereNull('sent_at')->update(['status' => 'sending'])) return;
        if (! app(InvitationMailSecurity::class)->logMatchesSignedSnapshot($log, $this->eventId)) { $log->markAsSkipped('The camp invitation snapshot is invalid.'); return; }
        $nominee = CampNominee::with('player', 'event', 'categoryEvent')->find($log->related_id);
        $config = app(CampAdmission::class)->configuration($this->eventId);
        $contacts = $nominee ? app(TeamSelectionContactService::class)->emails($nominee->player)->sort()->values()->all() : [];
        if (! $nominee || (int) $nominee->event_id !== $this->eventId || (int) $nominee->categoryEvent?->event_id !== $this->eventId
            || $nominee->status !== 'approved' || ! $nominee->event->published || ! $nominee->event->registrationIsOpen()
            || $config?->revision !== ($log->payload['configuration_revision'] ?? null)
            || config('mail.from.address') !== ($log->payload['from_address'] ?? null)
            || config('mail.from.name') !== ($log->payload['from_name'] ?? null)
            || app(CampInvitations::class)->eventHash($nominee) !== ($log->payload['event_hash'] ?? null)
            || $contacts !== ($log->payload['contacts'] ?? [])
            || app(TeamSelectionContactService::class)->primaryEmail($nominee->player) !== ($log->payload['primary'] ?? null)
            || in_array(app(CampInvitations::class)->participation($nominee), ['paid', 'checkout_pending', 'withdrawn'], true)) {
            $log->markAsSkipped('Camp eligibility, entry state or contacts changed after review.'); return;
        }
        try { app(CampAdmission::class)->assertEligible($nominee->categoryEvent, (int) $nominee->player_id); }
        catch (\RuntimeException) { $log->markAsSkipped('Camp admission requirements changed after review.'); return; }
        $mailer = app(MailAccountManager::class)->getMailer();
        $transport = Mail::mailer($mailer);
        try {
            $sent = $transport->to($log->recipient_email)->sendNow((new \Illuminate\Mail\Mailable)
                ->subject($log->payload['rendered_subject'])->html($log->payload['rendered_html'])
                ->from($log->payload['from_address'], $log->payload['from_name'])->replyTo($log->payload['reply_to'])
                ->with('event_mail_reviewed', true)->with('outbound_mail_log_id', $log->id));
            if ($sent === null) { $log->markAsSkipped('The message was held before transport.'); return; }
        } catch (Throwable $e) { app(\App\Services\MailFailureOutcome::class)->record($log, $e, true); return; }
        try { $log->recordTransportResult($sent, $transport->getSymfonyTransport(), $mailer); }
        catch (Throwable) { $log->update(['status' => 'acceptance_unknown', 'sent_at' => now(), 'error_message' => 'Transport returned successfully but receipt storage failed. Check provider evidence before retrying.']); }
    }
}
