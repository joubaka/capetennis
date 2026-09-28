<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Entries\Events\EntryWithdrawn;
use App\Services\InterprovincialTrials\InvitationService;

class SyncInterprovincialTrialInvitationWithdrawal
{
    public function __construct(private InvitationService $invitations)
    {
    }

    public function handle(EntryWithdrawn $event): void
    {
        $this->invitations->handlePaidWithdrawal(
            (int) $event->entry->registration_id,
            $event->actingUser,
        );
    }
}
