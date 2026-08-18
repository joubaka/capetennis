<?php

namespace App\Listeners;

use App\Support\Audit\AuditWriter;
use Illuminate\Auth\Events\Logout;

class LogLogoutAudit
{
    public function handle(Logout $event): void
    {
        app(AuditWriter::class)->record([
            'category' => 'security',
            'action' => 'auth.logout',
            'actor' => $event->user,
            'subject' => $event->user,
            'metadata' => ['guard' => $event->guard],
        ], true);
    }
}
