<?php

namespace App\Domain\Ranking\Enums;

enum RankingStatus: string
{
    /** Computed in-memory or stored as a dry-run; not visible to users. */
    case Draft = 'draft';

    /** Persisted to the database; visible to admins only. */
    case Calculated = 'calculated';

    /** Admin has reviewed the output and flagged it as ready. */
    case Reviewed = 'reviewed';

    /** Published to the public-facing ranking view. */
    case Published = 'published';

    /** Superseded by a newer publication; kept for historical audit. */
    case Archived = 'archived';

    // ------------------------------------------------------------------

    public function label(): string
    {
        return match ($this) {
            self::Draft      => 'Draft',
            self::Calculated => 'Calculated',
            self::Reviewed   => 'Reviewed',
            self::Published  => 'Published',
            self::Archived   => 'Archived',
        };
    }

    public function isMutable(): bool
    {
        return in_array($this, [self::Draft, self::Calculated, self::Reviewed]);
    }

    public function isVisible(): bool
    {
        return $this === self::Published;
    }
}
