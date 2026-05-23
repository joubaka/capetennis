<?php

declare(strict_types=1);

namespace App\Domain\Entries\Events;

use App\Models\CategoryEvent;
use App\Models\User;

/**
 * Fired after a category draw has been unlocked (after DB commit).
 */
class EntryUnlocked
{
    public function __construct(
        public readonly CategoryEvent $categoryEvent,
        public readonly User $actingUser,
    ) {}
}
