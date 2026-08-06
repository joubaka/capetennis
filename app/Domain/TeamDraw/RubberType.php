<?php

namespace App\Domain\TeamDraw;

final class RubberType
{
    public const SINGLES = 'singles';
    public const REVERSE_SINGLES = 'reverse_singles';
    public const DOUBLES = 'doubles';
    public const REVERSE_DOUBLES = 'reverse_doubles';
    public const MIXED_DOUBLES = 'mixed_doubles';
    public const REVERSE_MIXED_DOUBLES = 'reverse_mixed_doubles';

    public const ALL = [
        self::SINGLES,
        self::REVERSE_SINGLES,
        self::DOUBLES,
        self::REVERSE_DOUBLES,
        self::MIXED_DOUBLES,
        self::REVERSE_MIXED_DOUBLES,
    ];

    private const LEGACY_FIXTURE_TYPE_MAP = [
        1 => self::SINGLES,
        2 => self::DOUBLES,
        3 => self::MIXED_DOUBLES,
        4 => self::REVERSE_SINGLES,
    ];

    public static function expectedPlayerCountPerTeam(string $rubberType): int
    {
        return in_array($rubberType, [self::DOUBLES, self::REVERSE_DOUBLES, self::MIXED_DOUBLES, self::REVERSE_MIXED_DOUBLES], true) ? 2 : 1;
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
     *
     * @throws \InvalidArgumentException when the canonical value is null/unknown.
     */
    public static function toLegacyFixtureType(?string $rubberType): int
    {
        if ($rubberType === null) {
            throw new \InvalidArgumentException('Rubber code is required to map legacy fixture_type.');
        }

        return match ($rubberType) {
            self::SINGLES => 1,
            self::DOUBLES, self::REVERSE_DOUBLES => 2,
            self::MIXED_DOUBLES, self::REVERSE_MIXED_DOUBLES => 3,
            self::REVERSE_SINGLES => 4,
            default => throw new \InvalidArgumentException("Unsupported rubber code [{$rubberType}] for legacy fixture_type mapping."),
        };
    }
}
