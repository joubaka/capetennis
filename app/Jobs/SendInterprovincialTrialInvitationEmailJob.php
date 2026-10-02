<?php

namespace App\Jobs;

use App\Models\BulkEmailLog;
use App\Models\InterprovincialTrialInvitation;
use App\Services\MailAccountManager;
use App\Services\InvitationMailSecurity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInterprovincialTrialInvitationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 0;
    public int $maxExceptions = 1;

    public function __construct(public int $logId, public int $eventId) { $this->afterCommit(); }
    public function middleware(): array { return [(new WithoutOverlapping('interpro-trial-email:'.$this->logId))->releaseAfter(5)->expireAfter(180), new RateLimited('outbound-mail')]; }

    public function handle(): void
    {
        $log = BulkEmailLog::where('mail_type', 'interprovincial_trial_invitation')->find($this->logId);
        if (! $log || $log->sent_at || in_array($log->status, ['sent', 'skipped', 'failed', 'sending', 'acceptance_unknown'], true)) return;
        if (! app(InvitationMailSecurity::class)->logMatchesSignedSnapshot($log, $this->eventId)) {
            $log->markAsSkipped('Invitation email snapshot integrity check failed.'); return;
        }
        if (empty($log->payload['rendered_html']) || empty($log->payload['rendered_subject'])) {
            $log->markAsSkipped('This invitation needs a fresh exact content preview before it can be sent.');
            return;
        }
        $invitation = InterprovincialTrialInvitation::with(['batch.event', 'categoryEvent.category', 'player'])->find($log->related_id);
        $kind = (string) data_get($log->payload, 'kind', 'initial');
        $eligibleStates = $kind === 'initial'
            ? ['queued', 'sending', 'failed']
            : ['sent', InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT];
        if (! $invitation || (int) $invitation->event_id !== $this->eventId || ! in_array($invitation->status, $eligibleStates, true)) {
            $log->markAsSkipped('Invitation is no longer eligible to send.'); return;
        }
        // SMTP is at-least-once: a worker can fail after transport acceptance but
        // before this state is committed. The stable log/dispatch keys prevent
        // separate queue requests; provider-level idempotency is unavailable.
        if ($kind === 'initial') {
            $claimed = InterprovincialTrialInvitation::whereKey($invitation->id)->whereIn('status', $eligibleStates)->update(['status' => 'sending']);
            if (! $claimed) {
                $log->markAsSkipped('Invitation lifecycle changed before sending.');
                return;
            }
        }
        $log->update(['status' => 'sending', 'failed_at' => null, 'error_message' => null]);
        $mailer = app(MailAccountManager::class)->getMailer();
        $mailTransport = Mail::mailer($mailer);
        try {
            // The signed snapshot check above is the provenance of manual approval.
            $sent = $mailTransport->to($log->recipient_email)
                ->sendNow((new \Illuminate\Mail\Mailable)
                    ->subject($log->payload['rendered_subject'])
                    ->html($log->payload['rendered_html'])
                    ->from($log->payload['from_address'], $log->payload['from_name'])
                    ->replyTo($log->payload['reply_to'])
                    ->with('event_mail_reviewed', true)->with('outbound_mail_log_id', $log->id));
            if ($sent === null) throw new \RuntimeException('Invitation was not accepted by the mail transport.');
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed']);
            $this->failed($exception);
            return;
        }
        try {
            $log->recordTransportResult($sent, $mailTransport->getSymfonyTransport(), $mailer);
            if ($kind === 'initial') InterprovincialTrialInvitation::whereKey($invitation->id)->where('status', 'sending')->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable) {
            // Once transport returned successfully, a receipt-write failure must not authorise another send.
            try {
                $log->update(['status' => 'acceptance_unknown', 'sent_at' => now(), 'error_message' => 'Transport returned successfully, but receipt storage failed. Check the mail server before attempting another send.']);
                if ($kind === 'initial') InterprovincialTrialInvitation::whereKey($invitation->id)->where('status', 'sending')->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (Throwable) {
                \Illuminate\Support\Facades\Log::critical('Trials invitation transport returned successfully but evidence could not be stored; do not resend without server verification.', ['log_id' => $log->id]);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $log = BulkEmailLog::find($this->logId);
        if ($log && ! $log->sent_at && ! in_array($log->status, ['sending', 'acceptance_unknown'], true)) {
            $log->markAsFailed(str_ireplace($log->recipient_email, '[REDACTED_RECIPIENT]', $exception->getMessage()));
            if ((string) data_get($log->payload, 'kind', 'initial') === 'initial') {
                InterprovincialTrialInvitation::whereKey($log->related_id)->where('event_id', $this->eventId)->whereIn('status', ['queued', 'sending', 'failed'])->whereNull('sent_at')->update(['status' => 'failed']);
            }
        }
    }
}
