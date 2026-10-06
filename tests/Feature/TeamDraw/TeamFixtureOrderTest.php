<?php

namespace Tests\Feature\TeamDraw;

use App\Models\Draw;
use App\Models\TeamFixture;
use App\Services\Scheduling\TeamFixtureOrder;
use App\Services\Scheduling\VenueMatchOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamFixtureOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_collection_and_scoring_order_agree_for_times_numeric_ties_and_legacy_rows(): void
    {
        $draw = Draw::factory()->create();
        $rows = collect([
            ['round_nr' => '10', 'tie_nr' => '1', 'rubber_sequence' => 1, 'match_nr' => '1', 'scheduled_at' => '2026-10-09 09:00:00'],
            ['round_nr' => '2', 'tie_nr' => '10', 'rubber_sequence' => 1, 'match_nr' => '10', 'scheduled_at' => '2026-10-09 09:00:00'],
            ['round_nr' => '2', 'tie_nr' => '2', 'rubber_sequence' => 2, 'match_nr' => '2', 'scheduled_at' => '2026-10-09 09:00:00'],
            ['round_nr' => '2', 'tie_nr' => '2', 'rubber_sequence' => 1, 'match_nr' => '9', 'scheduled_at' => '2026-10-09 09:00:00'],
            ['round_nr' => '1', 'tie_nr' => '1', 'rubber_sequence' => null, 'match_nr' => '2', 'scheduled_at' => null],
            ['round_nr' => '1', 'tie_nr' => '1', 'rubber_sequence' => null, 'match_nr' => '10', 'scheduled_at' => null],
            ['round_nr' => '10', 'tie_nr' => '10', 'rubber_sequence' => 10, 'match_nr' => '10', 'scheduled_at' => '2026-10-09 08:00:00'],
        ])->map(fn ($row) => TeamFixture::create($row + ['draw_id' => $draw->id, 'fixture_type' => 1, 'numSets' => 3]));
        $expected = [$rows[6]->id, $rows[3]->id, $rows[2]->id, $rows[1]->id, $rows[0]->id, $rows[4]->id, $rows[5]->id];
        $this->assertSame($expected, TeamFixture::where('draw_id', $draw->id)->inPlayOrder()->get()->modelKeys());
        $this->assertSame($expected, app(TeamFixtureOrder::class)->sort($rows->reverse())->pluck('id')->all());
        $order = app(VenueMatchOrder::class);
        $this->assertSame($expected, $rows->reverse()->sort(fn ($a, $b) => $order->compare($a, $b))->pluck('id')->all());
        $this->assertSame(array_slice($expected, 0, 3), TeamFixture::where('draw_id', $draw->id)->inPlayOrder()->paginate(3)->getCollection()->modelKeys());
    }
}
