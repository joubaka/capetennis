<?php

namespace App\Domain\Draws\Enums;

/**
 * Canonical draw lifecycle states.
 *
 * Transitions:
 *   draft -> configured -> generated -> locked -> published -> completed
 *
 * DB reality:
 *   draws.published  = int|null  (NULL/0 = not published, 1 = published)
 *   draws.locked     = int|null  (NULL/0 = not locked,    1 = locked)
 */
enum DrawState: string
{
    case Draft      = "draft";
    case Configured = "configured";
    case Generated  = "generated";
    case Locked     = "locked";
    case Published  = "published";
    case Completed  = "completed";

    public static function isPublished(\App\Models\Draw $draw): bool
    {
        return (int) ($draw->published ?? 0) === 1;
    }

    public static function isLocked(\App\Models\Draw $draw): bool
    {
        return (int) ($draw->locked ?? 0) === 1;
    }

    public static function fromDraw(\App\Models\Draw $draw): self
    {
        if (self::isLocked($draw))    return self::Locked;
        if (self::isPublished($draw)) return self::Published;

        $hasFixtures = $draw->drawFixtures()->exists();
        if ($hasFixtures)             return self::Generated;

        $hasSetting = $draw->settings()->exists();
        if ($hasSetting)              return self::Configured;

        return self::Draft;
    }

    public function isMutable(): bool
    {
        return match ($this) {
            self::Draft,
            self::Configured,
            self::Generated  => true,
            default          => false,
        };
    }
}