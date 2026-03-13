<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        // Respect env flag: only log successful logins if LOG_AUTH_SUCCESS=true
        if (!env('LOG_AUTH_SUCCESS', false)) {
            return;
        }

        activity('auth')
            ->performedOn($event->user)
            ->causedBy($event->user)
            ->withProperties([
                'ip' => request()->ip(),
                'agent' => substr(request()->userAgent() ?? '', 0, 255),
            ])
            ->log('User logged in');
    }
}
