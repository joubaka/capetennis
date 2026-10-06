<?php

namespace App\Support;

use App\Models\TeamRegion;

final class RegionBadge
{
    // Every default supports white text at WCAG AA contrast.
    private const COLORS = ['#1e40af', '#047857', '#b45309', '#7e22ce', '#be123c', '#0e7490',
        '#4338ca', '#166534', '#9a3412', '#86198f', '#9f1239', '#334155'];

    public static function color(?TeamRegion $region): string
    {
        if (! $region) return '#475569';
        $name = mb_strtolower(trim(preg_replace('/\s+\d{4}$/u', '', trim((string) $region->region_name))));
        $known = match ($name) {
            'overberg' => 0,
            'witzenberg' => 1,
            'cape winelands', 'kaapse wynland' => 2,
            'west coast', 'weskus' => 3,
            'drakenstein' => 4,
            'eden' => 5,
            'cape town', 'city of cape town' => 6,
            'boland' => 7,
            'western cape' => 8,
            'namakwa' => 9,
            'central karoo' => 10,
            'overstrand' => 11,
            default => null,
        };
        if ($known !== null) return self::COLORS[$known];
        $identity = $region->getKey() ?? sprintf('%u', crc32(mb_strtolower(trim((string) $region->region_name))));
        return self::COLORS[(int) $identity % count(self::COLORS)];
    }
}
