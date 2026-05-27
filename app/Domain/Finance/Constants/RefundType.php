<?php

namespace App\Domain\Finance\Constants;

/**
 * Authoritative refund transaction type taxonomy.
 *
 * These constants are used by FinancialLedgerService for row labelling,
 * RefundExecutionService for source_type values, and all Blade/export renderers
 * to produce consistent display labels.
 */
final class RefundType
{
    // ── Refund Status ─────────────────────────────────────────────────────────
    const STATUS_PENDING    = 'pending';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_NOT_REFUNDED = 'not_refunded';

    // ── Refund Method / Transaction Subtype ───────────────────────────────────
    const METHOD_WALLET         = 'wallet';
    const METHOD_BANK           = 'bank';
    const METHOD_PAYFAST        = 'payfast';
    const METHOD_ADMIN_OVERRIDE = 'admin_override';
    const METHOD_NO_REFUND      = 'no_refund';

    // ── Source Types (used as wallet_transaction.source_type) ─────────────────
    const SOURCE_ADMIN_REFUND              = 'admin_refund';
    const SOURCE_TEAM_PLAYER_REFUND        = 'team_player_refund';
    const SOURCE_BANK_WALLET_REFUND        = 'event_registration_bank_wallet_refund';
    const SOURCE_REGISTRATION_REFUND       = 'event_registration_refund';

    // ── Display Labels ────────────────────────────────────────────────────────
    const LABELS = [
        self::METHOD_WALLET         => 'Wallet Refund',
        self::METHOD_BANK           => 'Bank Refund',
        self::METHOD_PAYFAST        => 'PayFast Refund',
        self::METHOD_ADMIN_OVERRIDE => 'Admin Override Refund',
        self::METHOD_NO_REFUND      => 'No Refund (Withdrawal)',
    ];

    // ── Display Colours ───────────────────────────────────────────────────────
    const COLOURS = [
        self::STATUS_COMPLETED    => 'success',
        self::STATUS_PENDING      => 'warning',
        self::STATUS_NOT_REFUNDED => 'secondary',
    ];

    public static function label(string $method): string
    {
        return self::LABELS[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }

    public static function colour(string $status): string
    {
        return self::COLOURS[$status] ?? 'secondary';
    }
}
