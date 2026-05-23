<?php

namespace App\Domain\Draws\Enums;

/**
 * Canonical payment states.
 *
 * registration_orders.pay_status  : tinyint(1)  0=unpaid, 1=paid
 * registration_orders.payfast_paid: tinyint(1)  0=not paid via PF, 1=paid via PF
 * registration_orders.wallet_debited: tinyint(1)
 * category_event_registrations.payment_status_id: int 0/1/NULL (legacy, unmapped table)
 *
 * Wallet transaction types map to WalletTransactionType.
 */
enum PaymentState: int
{
    case Unpaid  = 0;
    case Paid    = 1;

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public static function fromInt(?int $v): self
    {
        return match ((int) ($v ?? 0)) {
            1       => self::Paid,
            default => self::Unpaid,
        };
    }
}