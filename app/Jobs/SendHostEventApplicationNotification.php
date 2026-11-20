<?php

namespace App\Jobs;

use App\Mail\HostEventApplicationMail;
use App\Models\{HostEventNotification, User};
use App\Services\MailAccountManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;

class SendHostEventApplicationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $maxExceptions = 1;
    public int $tries = 0;

    public function __construct(public int $notificationId) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHour();
    }

    public function middleware(): array
    {
        return [new RateLimited('outbound-mail')];
    }

    public function handle(MailAccountManager $accounts): void
    {
        // Claim before transport: ambiguous SMTP outcomes must not automatically send twice.
        if (!HostEventNotification::whereKey($this->notificationId)->where('status', 'queued')->update(['status' => 'sending'])) return;
        $notification = HostEventNotification::findOrFail($this->notificationId);
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'super-user')->where('guard_name', 'web'))->find($notification->admin_id);
        if (!$admin || !filter_var($admin->email, FILTER_VALIDATE_EMAIL)) {
            $notification->update(['status' => 'skipped']);
            return;
        }
        try {
            Mail::mailer($accounts->getMailer())->to($admin->email)->send(new HostEventApplicationMail($notification->application_id));
            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $exception) {
            $notification->update(['status' => 'needs_review']);
            throw $exception;
        }
    }
}
