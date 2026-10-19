<?php

namespace Tests\Feature\TeamDraw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\TeamFixture;
use App\Models\User;
use App\Services\Scheduling\TeamFixtureOrder;
use App\Services\Scheduling\VenueMatchOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamFixtureOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_list_defaults_to_numeric_round_tie_then_rank_without_splitting_ties(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $rows = collect([
            ['round_nr' => '10', 'tie_nr' => '1', 'home_rank_nr' => 1, 'scheduled_at' => '2026-10-09 08:00:00'],
            ['round_nr' => '2', 'tie_nr' => '10', 'home_rank_nr' => 1, 'scheduled_at' => '2026-10-09 09:00:00'],
            ['round_nr' => '2', 'tie_nr' => '2', 'home_rank_nr' => 10, 'scheduled_at' => '2026-10-09 07:00:00'],
            ['round_nr' => '1', 'tie_nr' => '1', 'home_rank_nr' => 2, 'scheduled_at' => '2026-10-09 08:00:00'],
            ['round_nr' => '2', 'tie_nr' => '2', 'home_rank_nr' => 0, 'rank_nr' => 2, 'scheduled_at' => '2026-10-09 12:00:00'],
            ['round_nr' => '2', 'tie_nr' => '2', 'away_rank_nr' => 1, 'scheduled_at' => '2026-10-09 13:00:00'],
            ['round_nr' => '1', 'tie_nr' => '1', 'home_rank_nr' => 1, 'scheduled_at' => '2026-10-09 09:00:00'],
            ['round_nr' => '1', 'tie_nr' => '2', 'match_nr' => '10', 'scheduled_at' => null],
            ['round_nr' => '1', 'tie_nr' => '2', 'match_nr' => '2', 'scheduled_at' => null],
            ['round_nr' => '2', 'tie_nr' => '2', 'rubber_sequence' => 3, 'scheduled_at' => '2026-10-09 06:00:00'],
        ])->map(fn ($row) => TeamFixture::create($row + ['draw_id' => $draw->id, 'fixture_type' => 1, 'numSets' => 3, 'match_nr' => '1']));
        TeamFixture::create(['draw_id' => Draw::factory()->create()->id, 'fixture_type' => 1, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => '1', 'numSets' => 3]);
        $expected = collect([6, 3, 8, 7, 5, 4, 9, 2, 1, 0])->map(fn ($index) => $rows[$index]->id)->all();
        $url = route('backend.team-fixtures.index', ['draw_id' => $draw->id]);
        $response = $this->get($url)->assertOk()->assertViewHas('sort', 'round_tie');
        $this->assertSame($expected, $response->viewData('fixtures')->getCollection()->modelKeys());
        $response->assertSeeInOrder(array_map(fn ($id) => 'id="row-'.$id.'"', $expected), false);
        $this->assertSame(5, substr_count($response->getContent(), 'class="fixture-group"'));
        $this->assertSame($expected, $this->get($url.'&sort=round_tie')->assertOk()->viewData('fixtures')->getCollection()->modelKeys());
        $playOrder = $this->get($url.'&sort=play_order')->assertOk()->viewData('fixtures')->getCollection()->modelKeys();
        $this->assertSame(TeamFixture::where('draw_id', $draw->id)->inPlayOrder()->get()->modelKeys(), $playOrder);
        $this->assertNotSame($expected, $playOrder);
    }

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
        $expected = [$rows[6]->id, $rows[3]->id, $rows[1]->id, $rows[0]->id, $rows[2]->id, $rows[4]->id, $rows[5]->id];
        $this->assertSame($expected, TeamFixture::where('draw_id', $draw->id)->inPlayOrder()->get()->modelKeys());
        $this->assertSame($expected, app(TeamFixtureOrder::class)->sort($rows->reverse())->pluck('id')->all());
        $order = app(VenueMatchOrder::class);
        $this->assertSame($expected, $rows->reverse()->sort(fn ($a, $b) => $order->compare($a, $b))->pluck('id')->all());
        $this->assertSame(array_slice($expected, 0, 3), TeamFixture::where('draw_id', $draw->id)->inPlayOrder()->paginate(3)->getCollection()->modelKeys());
    }
    public function test_time_then_player_rank_agrees_across_draws_query_models_and_snapshot_rows(): void
    {
        $draws = Draw::factory()->count(2)->create();
        $rows = collect([
            ['draw_id' => $draws[0]->id, 'home_rank_nr' => 6, 'court_label' => 'Court 1', 'scheduled_at' => '2026-10-09 09:00:00'],
            ['draw_id' => $draws[1]->id, 'home_rank_nr' => 5, 'court_label' => '2', 'scheduled_at' => '2026-10-09 09:00:00'],
            ['draw_id' => $draws[0]->id, 'home_rank_nr' => 5, 'court_label' => '10', 'scheduled_at' => '2026-10-09 09:00:00'],
            ['draw_id' => $draws[1]->id, 'home_rank_nr' => 8, 'court_label' => 'Court 9', 'scheduled_at' => '2026-10-09 08:00:00'],
            ['draw_id' => $draws[0]->id, 'home_rank_nr' => 1, 'scheduled_at' => '2026-10-10 07:00:00'],
            ['draw_id' => $draws[0]->id, 'home_rank_nr' => 0, 'rank_nr' => -1, 'scheduled_at' => '2026-10-09 09:00:00'],
            ['draw_id' => $draws[0]->id, 'home_rank_nr' => 1, 'scheduled_at' => null],
        ])->map(fn ($row) => TeamFixture::create($row + ['fixture_type' => 1, 'numSets' => 3, 'round_nr' => 1, 'match_nr' => 1]));
        $expected = [$rows[3]->id, $rows[2]->id, $rows[1]->id, $rows[0]->id, $rows[5]->id, $rows[4]->id, $rows[6]->id];
        $this->assertSame($expected, TeamFixture::whereIn('draw_id', $draws->modelKeys())->orderByDesc('id')->inPlayOrder()->get()->modelKeys());
        $this->assertSame($expected, app(TeamFixtureOrder::class)->sort($rows->reverse())->pluck('id')->all());
        $snapshots = $rows->map(fn ($row) => [
            'fixture_kind' => 'team', 'fixture_id' => $row->id, 'draw_id' => $row->draw_id,
            'scheduled_at' => $row->scheduled_at, 'court' => $row->court_label,
            'rank' => app(TeamFixtureOrder::class)->rank($row), 'round_nr' => $row->round_nr,
            'tie_nr' => $row->tie_nr, 'rubber_sequence' => $row->rubber_sequence, 'match_nr' => $row->match_nr,
        ]);
        $order = app(VenueMatchOrder::class);
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $this->assertSame($expected, $snapshots->reverse()->sort(fn ($a, $b) => $order->compare($a, $b))->pluck('fixture_id')->all());
        $this->assertSame([], \Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();
    }

}
