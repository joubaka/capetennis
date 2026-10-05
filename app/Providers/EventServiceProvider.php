<?php

namespace App\Providers;

use App\Events\AnnouncementPost;
use App\Events\PaymentCompleted;
use App\Domain\Entries\Events\EntryCreated;
use App\Domain\Entries\Events\EntryWithdrawn;

use App\Events\UserRegistered;
use App\Listeners\SendAnouncementEmail;
use App\Listeners\SendWelcomeMail;
use App\Mail\AnouncementAdded;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogFailedLogin;
use App\Listeners\LogLogoutAudit;
use App\Listeners\SendTeamRegistrationConfirmation;
use App\Listeners\SendAdminEntryCreatedConfirmation;
use App\Listeners\SyncMastersInvitationWithdrawal;
use App\Listeners\SyncInterprovincialTrialInvitationWithdrawal;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \Illuminate\Mail\Events\MessageSending::class => [
            \App\Listeners\RequireEventEmailReview::class,
            [\App\Services\OutboundMailHistory::class, 'sending'],
        ],
        \Illuminate\Mail\Events\MessageSent::class => [
            [\App\Services\OutboundMailHistory::class, 'sent'],
        ],
        \Illuminate\Queue\Events\JobProcessing::class => [
            [\App\Services\OutboundMailHistory::class, 'processing'],
        ],
        \Illuminate\Queue\Events\JobExceptionOccurred::class => [
            [\App\Services\OutboundMailHistory::class, 'interruptedAttempt'],
        ],
        \Illuminate\Queue\Events\JobQueued::class => [[\App\Services\OutboundMailHistory::class, 'queued']],
        \Illuminate\Queue\Events\JobProcessed::class => [[\App\Services\OutboundMailHistory::class, 'processed']],
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        UserRegistered::class => [
           // SendWelcomeMail::class,
        ],
        AnnouncementPost::class => [
             SendAnouncementEmail::class,
        ],
        PaymentCompleted::class => [
            SendTeamRegistrationConfirmation::class,
        ],
        EntryCreated::class => [
            SendAdminEntryCreatedConfirmation::class,
        ],
        EntryWithdrawn::class => [
            SyncMastersInvitationWithdrawal::class,
            SyncInterprovincialTrialInvitationWithdrawal::class,
        ],
        // Auth events
        Login::class => [
            LogSuccessfulLogin::class,
        ],
        Failed::class => [
            LogFailedLogin::class,
        ],
        Logout::class => [
            LogLogoutAudit::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        \Illuminate\Support\Facades\Queue::createPayloadUsing(fn ($connection, $queue, $payload) => app(\App\Services\OutboundMailHistory::class)->queuePayload($connection, $queue, $payload));
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
