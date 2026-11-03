<?php

namespace App\Jobs;

use App\Mail\PlusPublicationMail;
use App\Models\{Event, PlusPublicationDelivery, PlusPublicationState};
use App\Services\{MailAccountManager, PlusPublicationNotificationService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\{DB, Mail};

class SendPlusPublicationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;
    public int $maxExceptions = 1;

    public function __construct(public int $deliveryId) {}
    public function retryUntil(): \DateTimeInterface { return now()->addDay(); }
    public function middleware(): array { return [new RateLimited('outbound-mail')]; }

    public function handle(PlusPublicationNotificationService $service): void
    {
        // A local/default-disabled scheduler must never reach a real mail transport.
        if (! config('publication_notifications.delivery_enabled')) return;
        $delivery = DB::transaction(function () use ($service) {
            $candidate = PlusPublicationDelivery::find($this->deliveryId);
            $state = $candidate ? PlusPublicationState::find($candidate->state_id) : null;
            if (! $state) return null;
            Event::whereKey($state->event_id)->lockForUpdate()->first();
            $draw = \App\Models\Draw::find($state->draw_id);
            if ($draw) $service->record($draw, trackedOnly: true);
            $delivery = PlusPublicationDelivery::lockForUpdate()->find($this->deliveryId);
            if (! $delivery || $delivery->status !== 'pending') return null;
            if (! $service->current($delivery)) {
                $delivery->update(['status' => 'superseded']);
                return null;
            }
            $delivery->update(['status' => 'sending', 'claimed_at' => now()]);
            return $delivery;
        }, 3);
        if (! $delivery) return;
        // A transport exception may follow acceptance. Never automatically resend a claimed email.
        try {
            $sent = Mail::mailer(app(MailAccountManager::class)->getMailer())->to($delivery->recipient)->send(new PlusPublicationMail($delivery));
            $delivery->update(['status' => $sent === null ? 'held' : 'sent', 'sent_at' => $sent === null ? null : now()]);
        } catch (\Throwable $exception) {
            $delivery->update(['status' => 'acceptance_unknown']);
            throw $exception;
        }
    }
}
