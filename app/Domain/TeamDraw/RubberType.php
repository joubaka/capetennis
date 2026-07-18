<?php

namespace App\Domain\TeamDraw;

final class RubberType
{
    public const SINGLES = 'singles';
    public const REVERSE_SINGLES = 'reverse_singles';
    public const DOUBLES = 'doubles';
    public const MIXED_DOUBLES = 'mixed_doubles';

    public const ALL = [
        self::SINGLES,
        self::REVERSE_SINGLES,
        self::DOUBLES,
        self::MIXED_DOUBLES,
    ];

    private const LEGACY_FIXTURE_TYPE_MAP = [
        1 => self::SINGLES,
        2 => self::DOUBLES,
        3 => self::MIXED_DOUBLES,
        4 => self::REVERSE_SINGLES,
    ];

    public static function expectedPlayerCountPerTeam(string $rubberType): int
    {
        return in_array($rubberType, [self::DOUBLES, self::MIXED_DOUBLES], true) ? 2 : 1;
    }

    public static function fromLegacyFixtureType(?int $legacyFixtureType): ?string
    {
        if ($legacyFixtureType === null) {
            return null;
        }

        return self::LEGACY_FIXTURE_TYPE_MAP[$legacyFixtureType] ?? null;
    }

    /**
     * Convert canonical rubber type to legacy numeric fixture_type.
     * Returns null for unsupported or null input.
     *
     * @param  string|null  $rubberType
     * @return int|null
     */
    public static function toLegacyFixtureType(?string $rubberType): ?int
    {
        if ($rubberType === null) {
            return null;
        }

        $reverse = array_flip(self::LEGACY_FIXTURE_TYPE_MAP);
        return $reverse[$rubberType] ?? null;
    }
}
