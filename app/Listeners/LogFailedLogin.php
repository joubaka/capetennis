<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        // Log failed attempts always
        $user = $event->user;

        activity('auth')
            ->causedBy($user)
            ->withProperties([
                'ip' => request()->ip(),
                'agent' => substr(request()->userAgent() ?? '', 0, 255),
                'credentials' => isset($event->credentials) ? array_intersect_key($event->credentials, array_flip(['email', 'username'])) : null,
            ])
            ->log('Failed login attempt');
    }
}
