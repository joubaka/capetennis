<?php

namespace Tests\Unit;

use App\Support\RegionLogo;
use PHPUnit\Framework\TestCase;

class RegionLogoTest extends TestCase
{
    public function test_known_region_names_resolve_existing_assets_without_guessing_from_short_codes(): void
    {
        foreach (['Overberg', ' Cape  Winelands ', 'West Coast', 'Weskus', 'Kaapse Wynland'] as $name) {
            $path = RegionLogo::path($name);
            $this->assertNotNull($path);
            $this->assertFileExists(dirname(__DIR__, 2).'/public/'.$path);
        }

        foreach ([null, '', 'OVER', 'WITZ', 'Drakenstein', 'West Coast Invitational'] as $name) {
            $this->assertNull(RegionLogo::path($name));
        }
    }
}
