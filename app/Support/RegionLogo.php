<?php

namespace App\Support;

final class RegionLogo
{
    public static function path(?string $regionName): ?string
    {
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $regionName ?? '')));

        return match ($name) {
            'overberg' => 'assets/img/logos/overberg.png',
            'cape winelands', 'kaapse wynland' => 'assets/img/logos/capeWinelandsLogo.jpeg',
            'west coast', 'weskus' => 'assets/img/logos/weskusLogo.jpg',
            default => null,
        };
    }
}
