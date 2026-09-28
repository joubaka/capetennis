<?php

namespace App\Jobs;

use App\Mail\InterprovincialTrialInvitationMail;
use App\Models\BulkEmailLog;
use App\Models\InterprovincialTrialInvitation;
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

class SendInterprovincialTrialInvitationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;

    public function __construct(public int $logId, public int $eventId) { $this->afterCommit(); }
    public function middleware(): array { return [(new WithoutOverlapping('interpro-trial-email:'.$this->logId))->releaseAfter(5)->expireAfter(180), new RateLimited('outbound-mail')]; }

    public function handle(): void
    {
        $log = BulkEmailLog::where('mail_type', 'interprovincial_trial_invitation')->find($this->logId);
        if (! $log || $log->sent_at || in_array($log->status, ['sent', 'skipped'], true)) return;
        $invitation = InterprovincialTrialInvitation::with(['batch.event', 'categoryEvent.category', 'player'])->find($log->related_id);
        if (! $invitation || (int) $invitation->event_id !== $this->eventId || ! in_array($invitation->status, ['queued', 'sending', 'failed'], true)) {
            $log->markAsSkipped('Invitation is no longer eligible to send.'); return;
        }
        // SMTP is at-least-once: a worker can fail after transport acceptance but
        // before this state is committed. The stable log/dispatch keys prevent
        // separate queue requests; provider-level idempotency is unavailable.
        $invitation->update(['status' => 'sending']);
        $log->update(['status' => 'sending', 'failed_at' => null, 'error_message' => null]);
        $sent = Mail::mailer(app(MailAccountManager::class)->getMailer())->to($log->recipient_email)
            ->sendNow(new InterprovincialTrialInvitationMail($invitation, $log->payload ?? []));
        if ($sent === null) throw new \RuntimeException('Invitation was not accepted by the mail transport.');
        $log->markAsSent();
        $invitation->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        $log = BulkEmailLog::find($this->logId);
        if ($log && ! $log->sent_at) {
            $log->markAsFailed($exception->getMessage());
            InterprovincialTrialInvitation::whereKey($log->related_id)->whereNull('sent_at')->update(['status' => 'failed']);
        }
    }
}
