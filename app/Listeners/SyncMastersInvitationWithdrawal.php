<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Entries\Events\EntryWithdrawn;
use App\Services\Masters\MastersInvitationService;

class SyncMastersInvitationWithdrawal
{
    public function __construct(private MastersInvitationService $mastersInvitations)
    {
    }

    public function handle(EntryWithdrawn $event): void
    {
        $this->mastersInvitations->handlePaidWithdrawal(
            (int) $event->entry->registration_id,
            $event->actingUser
        );
    }
}
