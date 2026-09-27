<?php

namespace App\Jobs;

use App\Mail\RecoveryPaymentMail;
use App\Models\RegistrationPaymentRecovery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Domain\Payments\Services\RegistrationPaymentRecoveryService;
use App\Models\User;
use Illuminate\Support\Str;

class SendRecoveryPaymentMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $recoveryId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('registration-recovery-mail:'.$this->recoveryId))->expireAfter(300), new RateLimited('outbound-mail')];
    }

    public function handle(): void
    {
        $recovery = DB::transaction(function (): ?RegistrationPaymentRecovery {
            $recovery = RegistrationPaymentRecovery::query()->with('user', 'order.items.category_event.event')->lockForUpdate()->findOrFail($this->recoveryId);
            if ($recovery->mail_queued_at || (int) $recovery->order?->pay_status === 1) return null;
            abort_unless($recovery->mail_authorized_at && $recovery->mail_queued_by && $recovery->mail_confirmation_hash && hash_equals(app(RegistrationPaymentRecoveryService::class)->mailAuthorizationHash($recovery), $recovery->mail_confirmation_hash), 409, 'Recovery mail is not authorized.');
            $actor = User::query()->find($recovery->mail_queued_by);
            abort_unless($actor && $actor->hasRole('super-user'), 403, 'Recovery mail authorizer is no longer a super-user.');
            if ($recovery->mail_attempt_token || $recovery->mail_sending_at) return null;
            app(RegistrationPaymentRecoveryService::class)->assertMailSnapshotCurrent($recovery);
            app(RegistrationPaymentRecoveryService::class)->validatePrepared($recovery);
            $recovery->update(['mail_attempt_token' => (string) Str::uuid(), 'mail_sending_at' => now(), 'status' => 'sending']);
            return $recovery->fresh(['user', 'order.items.category_event.event']);
        });
        if (! $recovery) return;
        Mail::to($recovery->user->email)->sendNow(new RecoveryPaymentMail($recovery));
        DB::transaction(function () use ($recovery): void {
            RegistrationPaymentRecovery::query()->whereKey($recovery->id)->where('mail_attempt_token', $recovery->mail_attempt_token)->whereNull('mail_sent_at')->update(['mail_queued_at' => now(), 'mail_sent_at' => now(), 'status' => 'notified']);
        });
    }
}
