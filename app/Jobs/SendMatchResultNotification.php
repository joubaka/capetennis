<?php

namespace App\Jobs;

use App\Mail\MatchResultMail;
use App\Models\MatchResultNotification;
use App\Services\{MailAccountManager, MatchResultNotificationService};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\{DB, Mail};

class SendMatchResultNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 0;
    public int $maxExceptions = 1;
    public function retryUntil(): \DateTimeInterface { return now()->addDay(); }
    public function __construct(public int $notificationId) {}
    public function middleware(): array { return [new RateLimited('outbound-mail')]; }

    public function handle(MatchResultNotificationService $service): void
    {
        $notification = DB::transaction(function () use ($service) {
            $notification = MatchResultNotification::lockForUpdate()->find($this->notificationId);
            if (!$notification || $notification->status !== 'pending') return null;
            if (!$service->current($notification)) { $notification->update(['status' => 'superseded']); return null; }
            $notification->update(['status' => 'sending', 'claimed_at' => now()]);
            return $notification;
        });
        if (!$notification) return;
        // A transport failure can be ambiguous. Preserve the claim for manual reconciliation,
        // rather than retrying and risking a duplicate delivered message.
        Mail::mailer(app(MailAccountManager::class)->getMailer())->to($notification->recipient)->send(new MatchResultMail($notification));
        $notification->update(['status' => 'sent', 'sent_at' => now()]);
    }
}
