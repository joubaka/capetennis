<?php

namespace App\Domain\Draws\Enums;

/**
 * Canonical refund states.
 *
 * Maps to category_event_registrations.refund_status enum column.
 */
enum RefundState: string
{
    case NotRefunded = "not_refunded";
    case Pending     = "pending";
    case Completed   = "completed";

    public function isTerminal(): bool
    {
        return $this === self::Completed;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}