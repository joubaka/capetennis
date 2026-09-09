<?php

namespace Tests\Feature\Ranking;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RankingTieDecisionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unresolved_equal_points_group_requires_an_admin_decision_before_review(): void
    {
        [$series, $event, $players, $tieKey] = $this->seedTie();
        $admin = $this->authorizedAdmin($event);

        $this->actingAs($admin)
            ->get(route('ranking.series.list', $series))
            ->assertOk()
            ->assertSee('Event score summary')
            ->assertSee('Primary Schools Witzenberg/Breede Valley Leg 2')
            ->assertSee('Leg 2')
            ->assertSee('1500 pts');

        $this->actingAs($admin)->postJson(route('ranking.series.ranking.review', $series))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Confirm every ranking tie before marking this ranking reviewed. 1 decision(s) remain.');

        $response = $this->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
            'ordered_player_ids' => [$players[1]->id, $players[0]->id],
            'reason' => 'previous_ranking',
            'note' => 'Previous published ranking places the second listed player first.',
        ])->assertOk()
            ->assertJsonPath('decision.reason', 'previous_ranking')
            ->assertJsonPath('decision.confirmed_order.0', $players[1]->id);

        $confirmedAt = $response->json('decision.confirmed_at');
        $this->assertDatabaseHas('ranking_tie_decisions', [
            'series_id' => $series->id,
            'run_id' => 'tie-run',
            'tie_key' => $tieKey,
            'reason' => 'previous_ranking',
            'confirmed_by' => $admin->id,
        ]);
        $this->assertSame(1, SeriesRanking::where('player_id', $players[1]->id)->value('rank_position'));
        $this->assertSame(2, SeriesRanking::where('player_id', $players[0]->id)->value('rank_position'));
        $this->assertStringContainsString(
            'Tie broken by previous published ranking',
            SeriesRanking::where('player_id', $players[0]->id)->first()->meta_json['tiebreak_notes'][1]
        );

        $this->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
            'ordered_player_ids' => [$players[1]->id, $players[0]->id],
            'reason' => 'previous_ranking',
            'note' => 'Previous published ranking places the second listed player first.',
        ])->assertOk()->assertJsonPath('decision.confirmed_at', $confirmedAt);
        $this->assertSame(1, DB::table('ranking_tie_decisions')->where('series_id', $series->id)->count());

        $this->actingAs($admin)->postJson(route('ranking.series.ranking.review', $series))->assertOk();
    }

    public function test_confirmed_decision_can_be_reordered_and_its_reason_changed_while_calculated(): void
    {
        [$series, $event, $players, $tieKey] = $this->seedTie();
        $admin = $this->authorizedAdmin($event);

        $this->actingAs($admin)->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
            'ordered_player_ids' => [$players[0]->id, $players[1]->id],
            'reason' => 'previous_ranking',
            'note' => 'Current order retained.',
        ])->assertOk();

        $originalCreatedAt = DB::table('ranking_tie_decisions')
            ->where('series_id', $series->id)
            ->value('created_at');

        $this->actingAs($admin)
            ->get(route('ranking.series.list', $series))
            ->assertOk()
            ->assertSee('Edit tie decision')
            ->assertSee('Current order retained.');

        $this->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
            'ordered_player_ids' => [$players[1]->id, $players[0]->id],
            'reason' => 'other',
            'note' => 'Tournament committee corrected the order after reviewing the records.',
        ])->assertOk()
            ->assertJsonPath('message', 'Tie decision saved for this ranking run.')
            ->assertJsonPath('decision.reason', 'other')
            ->assertJsonPath('decision.confirmed_order.0', $players[1]->id);

        $this->assertSame(1, SeriesRanking::where('player_id', $players[1]->id)->value('rank_position'));
        $this->assertSame(2, SeriesRanking::where('player_id', $players[0]->id)->value('rank_position'));
        $this->assertDatabaseHas('ranking_tie_decisions', [
            'series_id' => $series->id,
            'tie_key' => $tieKey,
            'reason' => 'other',
            'note' => 'Tournament committee corrected the order after reviewing the records.',
        ]);
        $this->assertSame(
            (string) $originalCreatedAt,
            (string) DB::table('ranking_tie_decisions')->where('series_id', $series->id)->value('created_at')
        );
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Series::class,
            'subject_id' => $series->id,
            'causer_id' => $admin->id,
            'description' => 'Ranking tie decision updated',
        ]);
    }

    public function test_reviewed_decision_cannot_be_edited_without_a_new_calculated_run(): void
    {
        [$series, $event, $players, $tieKey] = $this->seedTie();
        $admin = $this->authorizedAdmin($event);

        $this->actingAs($admin)->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
            'ordered_player_ids' => [$players[0]->id, $players[1]->id],
            'reason' => 'previous_ranking',
            'note' => 'Current order retained.',
        ])->assertOk();
        $this->postJson(route('ranking.series.ranking.review', $series))->assertOk();

        $this->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
            'ordered_player_ids' => [$players[1]->id, $players[0]->id],
            'reason' => 'other',
            'note' => 'Late change.',
        ])->assertUnprocessable()
            ->assertJsonPath('message', "Expected exactly one calculated ranking run for series {$series->id}; found 0.");
    }

    public function test_existing_third_event_resolution_does_not_require_admin_approval(): void
    {
        [$series, $event, $players] = $this->seedTie();
        $admin = $this->authorizedAdmin($event);
        $rows = SeriesRanking::where('series_id', $series->id)->orderBy('id')->get();

        foreach ($rows as $index => $row) {
            $meta = $row->meta_json;
            $meta['tie_decision']['suggested_method'] = 'third_event_score';
            $meta['tie_decision']['suggested_order'] = collect($players)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $row->update([
                'rank_position' => $index + 1,
                'meta_json' => $meta,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('ranking.series.list', $series))
            ->assertOk()
            ->assertDontSee('Confirmation required');

        $this->postJson(route('ranking.series.ranking.review', $series))->assertOk();
    }

    public function test_multi_player_tie_supports_a_confirmed_shared_position(): void
    {
        [$series, $event, $players, $tieKey] = $this->seedTie(3);
        $admin = $this->authorizedAdmin($event);

        $this->actingAs($admin)->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
            'ordered_player_ids' => [$players[2]->id, $players[0]->id, $players[1]->id],
            'reason' => 'shared_position',
            'note' => 'Insufficient evidence to separate the players.',
        ])->assertOk();

        $this->assertSame(
            [1],
            SeriesRanking::where('series_id', $series->id)->pluck('rank_position')->unique()->values()->all()
        );
    }

    public function test_other_reason_requires_a_custom_note_and_exact_player_permutation(): void
    {
        [$series, $event, $players, $tieKey] = $this->seedTie();
        $admin = $this->authorizedAdmin($event);

        $this->actingAs($admin)->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
            'ordered_player_ids' => [$players[0]->id, $players[1]->id],
            'reason' => 'other',
            'note' => '',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'A custom note is required when Other is selected.');

        $this->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
            'ordered_player_ids' => [$players[0]->id, $players[0]->id],
            'reason' => 'previous_ranking',
        ])->assertUnprocessable();
    }

    public function test_admin_without_series_access_cannot_confirm_a_tie(): void
    {
        [$series, , $players, $tieKey] = $this->seedTie();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
            'ordered_player_ids' => [$players[0]->id, $players[1]->id],
            'reason' => 'previous_ranking',
        ])->assertForbidden();
    }

    /** @return array{Series, Event, array<int, Player>, string} */
    private function seedTie(int $playerCount = 2): array
    {
        $series = Series::factory()->create();
        $category = Category::factory()->create(['name' => 'U/13 Boys']);
        $event = Event::factory()->create([
            'series_id' => $series->id,
            'name' => 'Primary Schools Witzenberg/Breede Valley Leg 2',
            'results_published' => true,
        ]);
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'category_id' => $category->id,
        ]);
        DB::table('ranking_list_category_events')->insert([
            'ranking_list_id' => $list->id,
            'category_event_id' => $categoryEvent->id,
        ]);
        $players = Player::factory()->count($playerCount)->create()->values();
        $playerIds = $players->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $tieKey = hash('sha256', implode(':', [$list->id, 1500, $playerIds->implode(',')]));
        $decision = [
            'tie_key' => $tieKey,
            'ranking_list_id' => $list->id,
            'total_points' => 1500,
            'player_ids' => $playerIds->all(),
            'suggested_order' => $players->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'suggested_method' => 'manual',
            'head_to_head_decision' => null,
            'confirmed_order' => null,
            'reason' => null,
            'note' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
        ];

        foreach ($players as $player) {
            SeriesRanking::create([
                'series_id' => $series->id,
                'ranking_list_id' => $list->id,
                'category_id' => $category->id,
                'player_id' => $player->id,
                'rank_position' => 1,
                'total_points' => 1500,
                'status' => 'calculated',
                'run_id' => 'tie-run',
                'meta_json' => [
                    'counting_legs' => [[
                        'category_event_id' => $categoryEvent->id,
                        'position' => 1,
                        'points' => 1500,
                        'synthetic' => false,
                    ]],
                    'dropped_legs' => [],
                    'tiebreak_notes' => ['Tied on 1500 points; no enabled tiebreak rule resolved the tie.'],
                    'head_to_head_decision' => null,
                    'tie_decision' => $decision,
                ],
            ]);
        }

        return [$series, $event, $players->all(), $tieKey];
    }

    private function authorizedAdmin(Event $event): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        return $admin;
    }
}
