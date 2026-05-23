<?php

declare(strict_types=1);

namespace App\Domain\Entries\Events;

use App\Models\CategoryEventRegistration;
use App\Models\User;

/**
 * Fired after an entry has been created (after DB commit).
 */
class EntryCreated
{
    public function __construct(
        public readonly CategoryEventRegistration $entry,
        public readonly User $actingUser,
        /** 'admin' | 'player' | 'payfast' */
        public readonly string $source,
    ) {}
}
