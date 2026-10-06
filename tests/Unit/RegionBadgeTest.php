<?php

namespace Tests\Unit;

use App\Models\TeamRegion;
use App\Support\RegionBadge;
use Tests\TestCase;

class RegionBadgeTest extends TestCase
{
    public function test_defaults_are_stable_distinct_and_have_accessible_white_text(): void
    {
        $colors = [];
        foreach (range(1, 12) as $id) {
            $region = new TeamRegion(['region_name' => 'Region '.$id]);
            $region->id = $id;
            $color = RegionBadge::color($region);
            $this->assertSame($color, RegionBadge::color($region));
            $region->region_name = 'Renamed region';
            $this->assertSame($color, RegionBadge::color($region));
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color);
            $channels = array_map(function ($offset) use ($color) {
                $channel = hexdec(substr($color, $offset, 2)) / 255;
                return $channel <= .04045 ? $channel / 12.92 : (($channel + .055) / 1.055) ** 2.4;
            }, [1, 3, 5]);
            $luminance = .2126 * $channels[0] + .7152 * $channels[1] + .0722 * $channels[2];
            $this->assertGreaterThanOrEqual(4.5, 1.05 / ($luminance + .05));
            $colors[] = $color;
        }
        $this->assertCount(12, array_unique($colors));
        $this->assertSame('#475569', RegionBadge::color(null));
    }

    public function test_badge_escapes_short_name_and_full_name(): void
    {
        $html = view('backend.team-fixtures.partials.region-badge', ['lineup' => [
            'region' => '<script>alert(1)</script>', 'region_name' => '" onmouseover="alert(1)', 'region_color' => '#1e40af',
        ]])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&quot; onmouseover=&quot;', $html);
        $this->assertStringContainsString('background-color:#1e40af', $html);
    }

    public function test_named_region_colors_survive_event_specific_records_and_do_not_collide(): void
    {
        $overberg = new TeamRegion(['region_name' => 'Overberg', 'short_name' => 'OVER']);
        $overberg->id = 1;
        $witzenberg = new TeamRegion(['region_name' => 'Witzenberg', 'short_name' => 'WITZ']);
        $witzenberg->id = 13;
        $this->assertNotSame(RegionBadge::color($overberg), RegionBadge::color($witzenberg));
        $copy = new TeamRegion(['region_name' => 'Overberg 2026']);
        $copy->id = 25;
        $this->assertSame(RegionBadge::color($overberg), RegionBadge::color($copy));
    }
}
