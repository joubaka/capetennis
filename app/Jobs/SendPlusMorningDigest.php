<?php

namespace App\Jobs;

use App\Mail\PlusMorningDigestMail;
use App\Models\{PlusDigestDelivery, User};
use App\Services\{MailAccountManager, PlusMorningDigestService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\{DB, Mail};

class SendPlusMorningDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;
    public int $maxExceptions = 1;
    public function __construct(public int $deliveryId) {}
    public function retryUntil(): \DateTimeInterface { return now()->addDay(); }
    public function middleware(): array { return [new RateLimited('outbound-mail')]; }

    public function handle(PlusMorningDigestService $service): void
    {
        if (! config('plus_morning_digest.delivery_enabled') || now('Africa/Johannesburg')->format('H:i') < '06:00') return;
        $delivery = DB::transaction(function () use ($service) {
            $delivery = PlusDigestDelivery::lockForUpdate()->find($this->deliveryId);
            if (! $delivery || $delivery->status !== 'pending') return null;
            if ($delivery->digest_date->toDateString() !== now('Africa/Johannesburg')->toDateString()) {
                $delivery->update(['status' => 'superseded']);
                return null;
            }
            $user = User::find($delivery->user_id);
            if (! $user || ! $service->eligible($user) || mb_strtolower(trim($user->email)) !== $delivery->recipient) {
                $delivery->update(['status' => 'suppressed']);
                return null;
            }
            $items = array_values(array_filter($delivery->items, fn ($item) => $service->itemCurrent($user, $item)));
            $delivery->update(['items' => $items, 'status' => $items ? 'sending' : 'suppressed', 'claimed_at' => $items ? now() : null]);
            if (! $items) $service->consume($delivery);
            return $items ? $delivery : null;
        }, 3);
        if (! $delivery) return;
        // Claimed transport is never retried automatically after an ambiguous acceptance.
        try {
            $sent = Mail::mailer(app(MailAccountManager::class)->getMailer())->to($delivery->recipient)->send(new PlusMorningDigestMail($delivery));
            DB::transaction(function () use ($delivery, $sent, $service): void {
                $delivery->update(['status' => $sent === null ? 'held' : 'sent', 'sent_at' => $sent === null ? null : now()]);
                if ($sent !== null) $service->consume($delivery);
            }, 3);
        } catch (\Throwable $exception) {
            if ($exception instanceof \App\Exceptions\MorningDigestAuthorizationException) {
                // The strict MessageSending listener refused before the transport was invoked.
                $delivery->update(['status' => 'held']);
                return;
            }
            DB::transaction(function () use ($delivery, $service): void {
                $delivery->update(['status' => 'acceptance_unknown']); $service->consume($delivery);
            }, 3);
            throw $exception;
        }
    }
}
