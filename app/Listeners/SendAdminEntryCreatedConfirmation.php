<?php

namespace App\Listeners;

use App\Domain\Entries\Events\EntryCreated;
use App\Mail\AdminEntryCreatedMail;
use Illuminate\Support\Facades\Mail;

class SendAdminEntryCreatedConfirmation
{
    public function handle(EntryCreated $event): void
    {
        if ($event->source !== 'admin') {
            return;
        }

        $event->entry->loadMissing(['players', 'categoryEvent.event', 'categoryEvent.category']);
        $email = $event->entry->players->first()?->email;

        if ($email) {
            Mail::to($email)->queue(new AdminEntryCreatedMail($event->entry));
        }
    }
}
