<?php

namespace App\Listeners;

use App\Support\Audit\AuditWriter;
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

        app(AuditWriter::class)->record([
            'category' => 'security',
            'action' => 'auth.login.failed',
            'outcome' => 'denied',
            'actor' => $user,
            'actor_type' => $user ? 'user' : 'anonymous',
            'metadata' => [
                'guard' => $event->guard,
                'credentials' => isset($event->credentials)
                    ? array_intersect_key($event->credentials, array_flip(['email', 'username']))
                    : null,
            ],
        ], true);

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
