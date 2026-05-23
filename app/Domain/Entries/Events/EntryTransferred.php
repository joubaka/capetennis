<?php

declare(strict_types=1);

namespace App\Domain\Entries\Events;

use App\Models\CategoryEventRegistration;
use App\Models\User;

/**
 * Fired after an entry has been transferred to a new category (after DB commit).
 */
class EntryTransferred
{
    public function __construct(
        public readonly CategoryEventRegistration $entry,
        public readonly int $fromCategoryEventId,
        public readonly int $toCategoryEventId,
        public readonly User $actingUser,
    ) {}
}
