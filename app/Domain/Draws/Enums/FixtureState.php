<?php

namespace App\Domain\Draws\Enums;

/**
 * Canonical fixture lifecycle states.
 *
 * Maps to the legacy integer match_status column:
 *   0 = pending / not played
 *   1 = completed (manual score)
 *   2 = in-progress / partially scored
 *   3 = BYE / walkover (auto-advanced)
 *   5 = double-BYE
 */
enum FixtureState: string
{
    case Pending    = 'pending';
    case Scheduled  = 'scheduled';
    case Live       = 'live';
    case Completed  = 'completed';
    case Bye        = 'bye';
    case Verified   = 'verified';

    // ── Legacy integer constants ──────────────────────────────────────────
    public const STATUS_PENDING    = 0;
    public const STATUS_COMPLETED  = 1;
    public const STATUS_PARTIAL    = 2;
    public const STATUS_BYE        = 3;
    public const STATUS_DOUBLE_BYE = 5;

    /**
     * Resolve from a Fixture model's existing integer match_status field.
     */
    public static function fromFixture(\App\Models\Fixture $fixture): self
    {
        $scheduled = ! is_null($fixture->start_time ?? null);

        return match ((int) ($fixture->match_status ?? 0)) {
            self::STATUS_COMPLETED              => self::Completed,
            self::STATUS_BYE, self::STATUS_DOUBLE_BYE => self::Bye,
            self::STATUS_PARTIAL                => self::Live,
            default                             => $scheduled ? self::Scheduled : self::Pending,
        };
    }

    /** Resolve directly from an integer status value. */
    public static function fromInt(int $status): self
    {
        return match ($status) {
            self::STATUS_COMPLETED              => self::Completed,
            self::STATUS_BYE, self::STATUS_DOUBLE_BYE => self::Bye,
            self::STATUS_PARTIAL                => self::Live,
            default                             => self::Pending,
        };
    }

    /** Integer value to persist into match_status. */
    public function toInt(): int
    {
        return match ($this) {
            self::Pending, self::Scheduled => self::STATUS_PENDING,
            self::Live                     => self::STATUS_PARTIAL,
            self::Completed, self::Verified => self::STATUS_COMPLETED,
            self::Bye                      => self::STATUS_BYE,
        };
    }

    /** May a score be written to a fixture in this state? */
    public function isScoreable(): bool
    {
        return match ($this) {
            self::Pending,
            self::Scheduled,
            self::Live  => true,
            default     => false,
        };
    }
}
