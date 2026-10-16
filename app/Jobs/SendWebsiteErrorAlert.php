<?php

namespace App\Jobs;

use App\Mail\WebsiteErrorAlertMail;
use App\Models\User;
use App\Services\MailAccountManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;

class SendWebsiteErrorAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // A provider failure can have an ambiguous outcome: avoid automatic duplicates.
    public int $maxExceptions = 1;

    public int $tries = 0;

    public function __construct(public int $adminId, public array $diagnostic) {}

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
        // Resolve current privileges at delivery time; no saved or submitted recipients.
        $admin = User::role('super-user', 'web')->find($this->adminId);
        if ($admin && filter_var($admin->email, FILTER_VALIDATE_EMAIL)) {
            Mail::mailer($accounts->getMailer())->to($admin->email)->send(new WebsiteErrorAlertMail($this->diagnostic));
        }
    }
}
