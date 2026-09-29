<?php

namespace App\Jobs;

use App\Domain\Payments\Services\RegistrationPaymentRecoveryService;
use App\Mail\RecoveryPaymentMail;
use App\Models\RegistrationPaymentRecovery;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendRecoveryPaymentMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const STALE_CLAIM_MINUTES = 10;

    public function __construct(public int $recoveryId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('registration-recovery-mail:'.$this->recoveryId))->expireAfter(300), new RateLimited('outbound-mail')];
    }

    public function handle(): void
    {
        $recovery = DB::transaction(function (): ?RegistrationPaymentRecovery {
            $recovery = RegistrationPaymentRecovery::query()->with('user', 'order.items.category_event.event')->lockForUpdate()->findOrFail($this->recoveryId);
            if ($recovery->mail_queued_at || $recovery->paid_at || (int) $recovery->order?->pay_status === 1) return null;
            abort_unless($recovery->mail_authorized_at && $recovery->mail_queued_by && $recovery->mail_confirmation_hash && hash_equals(app(RegistrationPaymentRecoveryService::class)->mailAuthorizationHash($recovery), $recovery->mail_confirmation_hash), 409, 'Recovery mail is not authorized.');
            $actor = User::query()->find($recovery->mail_queued_by);
            abort_unless($actor && $actor->hasRole('super-user'), 403, 'Recovery mail authorizer is no longer a super-user.');
            if ($recovery->mail_attempt_token || $recovery->mail_sending_at) {
                $stale = $recovery->mail_attempt_token
                    && $recovery->mail_sending_at?->lte(now()->subMinutes(self::STALE_CLAIM_MINUTES))
                    && $recovery->status === 'sending';
                if (! $stale) return null;

                // Claim fields are model-immutable. This tightly scoped compare-and-swap is
                // the only recovery path for a worker that died after taking the lease.
                RegistrationPaymentRecovery::query()
                    ->whereKey($recovery->id)
                    ->where('mail_attempt_token', $recovery->mail_attempt_token)
                    ->where('mail_sending_at', $recovery->mail_sending_at)
                    ->whereNull('mail_sent_at')
                    ->update([
                        'mail_attempt_token' => null,
                        'mail_sending_at' => null,
                        'status' => 'mail_failed',
                    ]);
                $recovery->refresh();
                if ($recovery->mail_attempt_token || $recovery->mail_sending_at) return null;
            }
            app(RegistrationPaymentRecoveryService::class)->assertMailSnapshotCurrent($recovery);
            app(RegistrationPaymentRecoveryService::class)->validatePrepared($recovery);
            app(RegistrationPaymentRecoveryService::class)->assertMailLinkDispatchable($recovery);
            $recovery->update(['mail_attempt_token' => (string) Str::uuid(), 'mail_sending_at' => now(), 'status' => 'sending']);
            return $recovery->fresh(['user', 'order.items.category_event.event']);
        });
        if (! $recovery) return;
        try {
            Mail::to($recovery->user->email)->sendNow(new RecoveryPaymentMail($recovery));
        } catch (Throwable $exception) {
            $this->finishAttempt($recovery, false);
            throw $exception;
        }
        $this->finishAttempt($recovery, true);
    }

    private function finishAttempt(RegistrationPaymentRecovery $attempt, bool $sent): void
    {
        DB::transaction(function () use ($attempt, $sent): void {
            $locked = RegistrationPaymentRecovery::query()
                ->with('order')
                ->lockForUpdate()
                ->findOrFail($attempt->id);
            if (! hash_equals((string) $locked->mail_attempt_token, (string) $attempt->mail_attempt_token)
                || $locked->mail_sent_at) {
                return;
            }

            $paid = $locked->paid_at !== null
                || (int) $locked->order?->pay_status === 1
                || (bool) $locked->order?->payfast_paid;
            $updates = $sent
                ? ['mail_queued_at' => now(), 'mail_sent_at' => now()]
                : ['mail_attempt_token' => null, 'mail_sending_at' => null];
            $updates['status'] = $paid ? 'paid' : ($sent ? 'notified' : 'mail_failed');

            // Token matching and the row lock ensure a stale worker cannot complete or
            // release a newer attempt. Paid state always wins over mail state.
            RegistrationPaymentRecovery::query()
                ->whereKey($locked->id)
                ->where('mail_attempt_token', $attempt->mail_attempt_token)
                ->whereNull('mail_sent_at')
                ->update($updates);
        });
    }
}
