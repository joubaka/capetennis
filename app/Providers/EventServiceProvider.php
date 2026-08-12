<?php

namespace App\Providers;

use App\Events\AnnouncementPost;
use App\Events\PaymentCompleted;
use App\Domain\Entries\Events\EntryCreated;

use App\Events\UserRegistered;
use App\Listeners\SendAnouncementEmail;
use App\Listeners\SendWelcomeMail;
use App\Mail\AnouncementAdded;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogFailedLogin;
use App\Listeners\SendTeamRegistrationConfirmation;
use App\Listeners\SendAdminEntryCreatedConfirmation;
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
        // Auth events
        Login::class => [
            LogSuccessfulLogin::class,
        ],
        Failed::class => [
            LogFailedLogin::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
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
