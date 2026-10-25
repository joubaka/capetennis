<?php

namespace App\Listeners;

use App\Domain\Entries\Events\{EntryCreated, EntryWithdrawn};
use App\Services\PlayerRegistrationUpdateService;

class SendPlayerEntryUpdate
{
    public function handle(EntryCreated|EntryWithdrawn $event): void
    {
        app(PlayerRegistrationUpdateService::class)->record($event->entry, $event instanceof EntryWithdrawn ? 'withdrawal' : 'registration');
    }
}
