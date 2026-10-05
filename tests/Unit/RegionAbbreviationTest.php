<?php

namespace Tests\Unit;

use App\Support\RegionAbbreviation;
use PHPUnit\Framework\TestCase;

class RegionAbbreviationTest extends TestCase
{
    public function test_configured_codes_and_read_only_name_fallbacks(): void
    {
        foreach ([['Overberg', '', 'Over'], ['Cape Winelands', null, 'CW'], ['West-Coast', '', 'WC'],
            ['Western Cape', 'WP', 'WP'], ['  eden  ', ' ', 'Eden'], ['', '', '']] as [$name, $configured, $expected]) {
            $region = (object) ['region_name' => $name, 'short_name' => $configured];
            $this->assertSame($expected, RegionAbbreviation::label($region));
            $this->assertSame($configured, $region->short_name);
        }
        $this->assertSame('', RegionAbbreviation::label(null));
    }
}
