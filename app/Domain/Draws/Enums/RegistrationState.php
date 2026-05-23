<?php

namespace App\Domain\Draws\Enums;

/**
 * Canonical registration lifecycle states.
 *
 * Maps to category_event_registrations.status enum column.
 */
enum RegistrationState: string
{
    case Active    = "active";
    case Withdrawn = "withdrawn";
    case Rejected  = "rejected";

    public function canWithdraw(): bool
    {
        return $this === self::Active;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}