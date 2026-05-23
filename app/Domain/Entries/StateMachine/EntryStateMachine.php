<?php

declare(strict_types=1);

namespace App\Domain\Entries\StateMachine;

use RuntimeException;

/**
 * Registration entry state machine for Cape Tennis.
 *
 * States
 * ------
 *   draft             – registration row created, not yet paid
 *   reserved          – order created, wallet reserved, awaiting PayFast
 *   paid              – payment confirmed (PayFast or wallet-only or admin entry)
 *   withdrawn         – player or admin withdrew
 *   refund_requested  – withdrawn + refund method submitted, awaiting processing
 *   refunded          – refund completed (wallet credit or bank transfer)
 *   cancelled         – entry voided without a refund (admin action)
 *
 * Transitions
 * -----------
 *   draft         → reserved, paid, cancelled
 *   reserved      → paid, cancelled
 *   paid          → withdrawn, cancelled
 *   withdrawn     → refund_requested, refunded, cancelled
 *   refund_requested → refunded, cancelled
 *   refunded      – terminal
 *   cancelled     – terminal
 *
 * Guards
 * ------
 *   paid      → withdrawn   : requires canWithdraw() passing; draw not locked for non-admins
 *   paid      → cancelled   : requires admin role
 *   withdrawn → refund_requested : requires is_paid && refund_allowed
 *   any       → cancelled   : requires admin role
 */
class EntryStateMachine
{
    // -----------------------------------------------------------------------
    // State constants
    // -----------------------------------------------------------------------

    public const STATE_DRAFT            = 'draft';
    public const STATE_RESERVED         = 'reserved';
    public const STATE_PAID             = 'paid';
    public const STATE_WITHDRAWN        = 'withdrawn';
    public const STATE_REFUND_REQUESTED = 'refund_requested';
    public const STATE_REFUNDED         = 'refunded';
    public const STATE_CANCELLED        = 'cancelled';

    public const ALL_STATES = [
        self::STATE_DRAFT,
        self::STATE_RESERVED,
        self::STATE_PAID,
        self::STATE_WITHDRAWN,
        self::STATE_REFUND_REQUESTED,
        self::STATE_REFUNDED,
        self::STATE_CANCELLED,
    ];

    // -----------------------------------------------------------------------
    // Allowed transitions  (from → [to, ...])
    // -----------------------------------------------------------------------

    private const TRANSITIONS = [
        self::STATE_DRAFT            => [self::STATE_RESERVED, self::STATE_PAID, self::STATE_CANCELLED],
        self::STATE_RESERVED         => [self::STATE_PAID, self::STATE_CANCELLED],
        self::STATE_PAID             => [self::STATE_WITHDRAWN, self::STATE_CANCELLED],
        self::STATE_WITHDRAWN        => [self::STATE_REFUND_REQUESTED, self::STATE_REFUNDED, self::STATE_CANCELLED],
        self::STATE_REFUND_REQUESTED => [self::STATE_REFUNDED, self::STATE_CANCELLED],
        self::STATE_REFUNDED         => [],   // terminal
        self::STATE_CANCELLED        => [],   // terminal
    ];

    // -----------------------------------------------------------------------
    // Role permissions per target state
    // -----------------------------------------------------------------------

    /**
     * States that require an admin role to transition into.
     */
    private const ADMIN_ONLY_TARGETS = [
        self::STATE_CANCELLED,
    ];

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Assert that a transition from $current → $target is allowed.
     *
     * @param  string  $current     Current state of the entry
     * @param  string  $target      Desired next state
     * @param  bool    $isAdmin     Whether the acting user holds an admin role
     *
     * @throws RuntimeException  if the transition is not permitted
     */
    public function assertTransition(string $current, string $target, bool $isAdmin = false): void
    {
        $allowed = self::TRANSITIONS[$current] ?? [];

        if (! in_array($target, $allowed, true)) {
            throw new RuntimeException(
                "Invalid state transition: [{$current}] → [{$target}]."
            );
        }

        if (in_array($target, self::ADMIN_ONLY_TARGETS, true) && ! $isAdmin) {
            throw new RuntimeException(
                "Transition to [{$target}] requires admin privileges."
            );
        }
    }

    /**
     * Check (without throwing) whether a transition is allowed.
     */
    public function canTransition(string $current, string $target, bool $isAdmin = false): bool
    {
        try {
            $this->assertTransition($current, $target, $isAdmin);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Return all valid next states from $current.
     *
     * @return string[]
     */
    public function availableTransitions(string $current, bool $isAdmin = false): array
    {
        return array_filter(
            self::TRANSITIONS[$current] ?? [],
            fn (string $t) => $isAdmin || ! in_array($t, self::ADMIN_ONLY_TARGETS, true)
        );
    }

    /**
     * Map a legacy status/withdrawn_at combination to a canonical state.
     *
     * Legacy rows use:
     *   status = 'active'    + withdrawn_at = null   → paid
     *   status = 'withdrawn' + withdrawn_at = <date> → withdrawn
     *   payment_status_id = 0                        → draft (or reserved)
     */
    public function resolveFromLegacy(
        string $status,
        ?string $withdrawnAt,
        int $paymentStatusId
    ): string {
        if ($status === 'withdrawn' || $withdrawnAt !== null) {
            return self::STATE_WITHDRAWN;
        }

        if ($paymentStatusId === 1) {
            return self::STATE_PAID;
        }

        return self::STATE_DRAFT;
    }
}
