<?php

namespace App\Listeners;

use App\Support\Audit\AuditWriter;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        // A fresh login gets a fresh reminder dismissal scope, independent of authentication tokens.
        if (request()->hasSession()) {
            request()->session()->put('match_reminder_login', (string) \Illuminate\Support\Str::uuid());
            request()->session()->put('match_reminder_pending', true);
        }

        app(AuditWriter::class)->record([
            'category' => 'security',
            'action' => 'auth.login.succeeded',
            'actor' => $event->user,
            'subject' => $event->user,
            'metadata' => ['guard' => $event->guard, 'remember' => $event->remember],
        ], true);

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
