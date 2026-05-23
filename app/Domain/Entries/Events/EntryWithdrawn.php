<?php

declare(strict_types=1);

namespace App\Domain\Entries\Events;

use App\Models\CategoryEventRegistration;
use App\Models\User;

/**
 * Fired after an entry has been withdrawn (after DB commit).
 */
class EntryWithdrawn
{
    public function __construct(
        public readonly CategoryEventRegistration $entry,
        public readonly User $actingUser,
        /** 'admin' | 'player' */
        public readonly string $initiatedBy,
    ) {}
}
