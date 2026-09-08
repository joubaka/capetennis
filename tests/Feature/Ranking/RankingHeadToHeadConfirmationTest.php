<?php

namespace Tests\Feature\Ranking;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\Registration;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RankingHeadToHeadConfirmationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_applied_head_to_head_must_be_confirmed_before_review(): void
    {
        [$series, $event, $fixture] = $this->seedCalculatedDecision();
        $admin = $this->authorizedAdmin($event);

        $this->actingAs($admin)
            ->get(route('ranking.series.list', $series))
            ->assertOk()
            ->assertSee('Confirmation required')
            ->assertSee('qualifying full set 7-5')
            ->assertSee(route('ranking.series.ranking.head-to-head.confirm', [$series, $fixture]), false);

        $this->actingAs($admin)
            ->postJson(route('ranking.series.ranking.review', $series))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Confirm all applied head-to-head decisions before marking this ranking reviewed. 1 confirmation(s) remain.');

        $confirmedResponse = $this->actingAs($admin)
            ->postJson(route('ranking.series.ranking.head-to-head.confirm', [$series, $fixture]))
            ->assertOk()
            ->assertJsonPath('decision.fixture_id', $fixture->id)
            ->assertJsonPath('decision.qualifying_set.score', '7-5');
        $confirmedAt = $confirmedResponse->json('decision.confirmed_at');

        $this->postJson(route('ranking.series.ranking.head-to-head.confirm', [$series, $fixture]))
            ->assertOk()
            ->assertJsonPath('decision.confirmed_at', $confirmedAt);

        $this->assertDatabaseHas('ranking_head_to_head_confirmations', [
            'series_id' => $series->id,
            'run_id' => 'h2h-run',
            'fixture_id' => $fixture->id,
            'confirmed_by' => $admin->id,
        ]);
        $this->assertSame(1, DB::table('ranking_head_to_head_confirmations')->where('series_id', $series->id)->count());
        $this->assertNotEmpty(
            SeriesRanking::where('series_id', $series->id)->first()->meta_json['head_to_head_decision']['confirmed_at']
        );

        $this->actingAs($admin)
            ->postJson(route('ranking.series.ranking.review', $series))
            ->assertOk();
        $this->assertSame(2, SeriesRanking::where('series_id', $series->id)->where('status', 'reviewed')->count());
    }

    public function test_confirmation_rejects_a_source_fixture_that_no_longer_has_a_full_set(): void
    {
        [$series, $event, $fixture] = $this->seedCalculatedDecision();
        $admin = $this->authorizedAdmin($event);
        FixtureResult::where('fixture_id', $fixture->id)->update([
            'registration1_score' => 4,
            'registration2_score' => 2,
        ]);

        $this->actingAs($admin)
            ->postJson(route('ranking.series.ranking.head-to-head.confirm', [$series, $fixture]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The source fixture changed or is no longer eligible. Rebuild the ranking before confirming it.');

        $this->assertDatabaseMissing('ranking_head_to_head_confirmations', ['series_id' => $series->id]);
    }

    public function test_general_tie_confirmation_requires_and_records_the_qualifying_head_to_head(): void
    {
        [$series, $event, $fixture] = $this->seedCalculatedDecision();
        $admin = $this->authorizedAdmin($event);
        $rows = SeriesRanking::where('series_id', $series->id)->orderBy('rank_position')->get();
        $playerIds = $rows->pluck('player_id')->map(fn ($id) => (int) $id)->sort()->values();
        $tieKey = hash('sha256', implode(':', [
            $rows->first()->ranking_list_id,
            $rows->first()->total_points,
            $playerIds->implode(','),
        ]));
        $headToHead = $rows->first()->meta_json['head_to_head_decision'];
        $decision = [
            'tie_key' => $tieKey,
            'ranking_list_id' => (int) $rows->first()->ranking_list_id,
            'total_points' => (int) $rows->first()->total_points,
            'player_ids' => $playerIds->all(),
            'suggested_order' => $rows->pluck('player_id')->map(fn ($id) => (int) $id)->values()->all(),
            'suggested_method' => 'head_to_head',
            'head_to_head_decision' => $headToHead,
            'confirmed_order' => null,
            'reason' => null,
            'note' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
        ];
        foreach ($rows as $row) {
            $meta = $row->meta_json;
            $meta['tie_decision'] = $decision;
            $row->update(['meta_json' => $meta]);
        }

        $this->actingAs($admin)
            ->postJson(route('ranking.series.ranking.tie-decision.confirm', [$series, $tieKey]), [
                'ordered_player_ids' => $decision['suggested_order'],
                'reason' => 'head_to_head',
                'note' => 'Head-to-head result approved by the administrator.',
            ])
            ->assertOk()
            ->assertJsonPath('decision.reason', 'head_to_head')
            ->assertJsonPath('decision.head_to_head_decision.fixture_id', $fixture->id);

        $this->assertDatabaseHas('ranking_head_to_head_confirmations', [
            'series_id' => $series->id,
            'run_id' => 'h2h-run',
            'fixture_id' => $fixture->id,
        ]);
        $this->assertDatabaseHas('ranking_tie_decisions', [
            'series_id' => $series->id,
            'run_id' => 'h2h-run',
            'tie_key' => $tieKey,
            'reason' => 'head_to_head',
        ]);
        $this->actingAs($admin)
            ->postJson(route('ranking.series.ranking.review', $series))
            ->assertOk();
    }

    public function test_unauthorized_admin_cannot_confirm_another_series_decision(): void
    {
        [$series, , $fixture] = $this->seedCalculatedDecision();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->postJson(route('ranking.series.ranking.head-to-head.confirm', [$series, $fixture]))
            ->assertForbidden();
    }

    /** @return array{Series, Event, Fixture} */
    private function seedCalculatedDecision(): array
    {
        $series = Series::factory()->create();
        $category = Category::factory()->create(['name' => 'U/13 Boys']);
        $event = Event::factory()->create(['series_id' => $series->id, 'results_published' => true]);
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'category_id' => $category->id,
        ]);
        DB::table('ranking_list_category_events')->insert([
            'ranking_list_id' => $list->id,
            'category_event_id' => $categoryEvent->id,
        ]);
        $winner = Player::factory()->create();
        $loser = Player::factory()->create();
        $winnerRegistration = Registration::factory()->create();
        $loserRegistration = Registration::factory()->create();
        $winnerRegistration->players()->attach($winner->id);
        $loserRegistration->players()->attach($loser->id);
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'category_event_id' => $categoryEvent->id,
        ]);
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'registration1_id' => $winnerRegistration->id,
            'registration2_id' => $loserRegistration->id,
            'winner_registration' => $winnerRegistration->id,
            'draw_group_id' => null,
        ]);
        FixtureResult::factory()->create([
            'fixture_id' => $fixture->id,
            'registration1_score' => 7,
            'registration2_score' => 5,
            'winner_registration' => $winnerRegistration->id,
        ]);
        $decision = [
            'winner_player_id' => $winner->id,
            'player_ids' => [$winner->id, $loser->id],
            'ranking_list_id' => $list->id,
            'event_id' => $event->id,
            'event_name' => $event->name,
            'fixture_id' => $fixture->id,
            'phase' => 'playoff',
            'qualifying_set' => [
                'set_nr' => 1,
                'score' => '7-5',
                'registration1_score' => 7,
                'registration2_score' => 5,
            ],
        ];

        foreach ([[$winner, 1], [$loser, 2]] as [$player, $rank]) {
            SeriesRanking::create([
                'series_id' => $series->id,
                'ranking_list_id' => $list->id,
                'category_id' => $category->id,
                'player_id' => $player->id,
                'rank_position' => $rank,
                'total_points' => 1500,
                'status' => 'calculated',
                'run_id' => 'h2h-run',
                'meta_json' => [
                    'tiebreak_notes' => ['Tied on 1500 points and third-event score; ordered by latest head-to-head winner.'],
                    'head_to_head_decision' => $decision,
                ],
            ]);
        }

        return [$series, $event, $fixture];
    }

    private function authorizedAdmin(Event $event): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        return $admin;
    }
}
