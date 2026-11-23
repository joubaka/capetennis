<?php
namespace App\Jobs;
use App\Mail\PlusEventUpdateMail;
use App\Models\{Event, PlusEventUpdateDelivery, PlusEventUpdateState};
use App\Services\{MailAccountManager, PlusEventUpdateService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\{DB, Mail};

class SendPlusEventUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 0;
    public int $maxExceptions = 1;
    public function __construct(public int $deliveryId) {}
    public function retryUntil(): \DateTimeInterface { return now()->addDay(); }
    public function middleware(): array { return [new RateLimited('outbound-mail')]; }
    public function handle(PlusEventUpdateService $service): void
    {
        if (! config('plus_event_updates.delivery_enabled')) return;
        $delivery = DB::transaction(function () use ($service) {
            $candidate = PlusEventUpdateDelivery::find($this->deliveryId);
            $state = $candidate ? PlusEventUpdateState::find($candidate->state_id) : null;
            $event = $state ? Event::whereKey($state->event_id)->lockForUpdate()->first() : null;
            if (! $event) return null;
            $service->record($event);
            $delivery = PlusEventUpdateDelivery::lockForUpdate()->find($this->deliveryId);
            if (! $delivery || $delivery->status !== 'pending') return null;
            if (! $service->current($delivery)) { $delivery->update(['status' => 'superseded']); return null; }
            $delivery->update(['status' => 'sending', 'claimed_at' => now()]); return $delivery;
        }, 3);
        if (! $delivery) return;
        try {
            $sent = Mail::mailer(app(MailAccountManager::class)->getMailer())->to($delivery->recipient)->send(new PlusEventUpdateMail($delivery));
            $delivery->update(['status' => $sent === null ? 'held' : 'sent', 'sent_at' => $sent === null ? null : now()]);
        } catch (\Throwable $exception) {
            $delivery->update(['status' => 'acceptance_unknown']); throw $exception;
        }
    }
}
