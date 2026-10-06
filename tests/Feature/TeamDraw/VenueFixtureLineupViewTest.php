<?php

namespace Tests\Feature\TeamDraw;

use Tests\TestCase;

class VenueFixtureLineupViewTest extends TestCase
{
    public function test_venue_lineup_shows_bracketed_region_and_individual_ranks_in_color(): void
    {
        $html = view('backend.headOffice.partials.venue-lineup', ['lineup' => [
            'region' => 'CW', 'region_name' => 'Cape Winelands', 'region_color' => '#375a8c',
            'players' => [['name' => 'First Partner', 'rank' => 2], ['name' => 'Second Partner', 'rank' => 4]],
        ]])->render();
        self::assertStringContainsString('(CW)', $html);
        self::assertStringContainsString('background-color:#375a8c', $html);
        self::assertStringContainsString('(2)', $html);
        self::assertStringContainsString('(4)', $html);
        self::assertStringContainsString('First Partner', $html);
        self::assertStringContainsString('Second Partner', $html);
        self::assertStringNotContainsString('rank unavailable', $html);
    }

    public function test_missing_rank_and_players_have_clear_fallbacks_and_names_are_escaped(): void
    {
        $lineup = ['region' => '', 'region_name' => '', 'players' => [['name' => '<script>bad</script>', 'rank' => null]]];
        $html = view('backend.headOffice.partials.venue-lineup', compact('lineup'))->render();
        self::assertStringContainsString('(TBC)', $html);
        self::assertStringContainsString('rank unavailable', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        $lineup['players'] = [];
        self::assertStringContainsString('Players to be confirmed', view('backend.headOffice.partials.venue-lineup', compact('lineup'))->render());
    }
}
