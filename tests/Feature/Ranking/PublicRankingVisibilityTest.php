<?php

namespace Tests\Feature\Ranking;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicRankingVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_rankings_index_lists_only_published_series(): void
    {
        $published = Series::query()->create([
            'name' => 'Visible Junior Series 2026',
            'year' => 2026,
            'leaderboard_published' => true,
        ]);
        $hidden = Series::query()->create([
            'name' => 'Private Draft Series',
            'leaderboard_published' => false,
        ]);

        $this->get(route('rankings.index'))
            ->assertOk()
            ->assertSee('Published series rankings')
            ->assertSee('Only currently published leaderboards are listed.')
            ->assertSee($published->name)
            ->assertSee(route('frontend.ranking.show', $published), false)
            ->assertDontSee($hidden->name)
            ->assertSee('View leaderboard');
    }

    public function test_public_leaderboard_only_shows_latest_published_run(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $published = Player::factory()->create(['name' => 'Published Player']);
        $draft = Player::factory()->create(['name' => 'Draft Player']);
        $archived = Player::factory()->create(['name' => 'Archived Player']);

        $this->row($series, $list, $category, $published, RankingStatus::Published, 'run-live', now());
        $this->row($series, $list, $category, $draft, RankingStatus::Calculated, 'run-draft');
        $this->row($series, $list, $category, $archived, RankingStatus::Archived, 'run-old');

        $this->get(route('frontend.ranking.show', $series))
            ->assertOk()
            ->assertSee('Published Player')
            ->assertDontSee('Draft Player')
            ->assertDontSee('Archived Player');
    }

    public function test_public_leaderboard_hides_players_below_the_selected_event_minimum_and_closes_rank_gaps(): void
    {
        $series = Series::factory()->create([
            'leaderboard_published' => true,
            'minimum_events_for_team_selection' => 2,
        ]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $first = Player::factory()->create(['name' => 'Eligible First']);
        $hidden = Player::factory()->create(['name' => 'One Event Player']);
        $next = Player::factory()->create(['name' => 'Eligible Next']);

        foreach ([
            [$first, 1, 2],
            [$hidden, 2, 1],
            [$next, 3, 2],
        ] as [$player, $rank, $eventsPlayed]) {
            $this->row(
                $series,
                $list,
                $category,
                $player,
                RankingStatus::Published,
                'run-live',
                now(),
                ['events_played' => $eventsPlayed],
            );
            SeriesRanking::where('series_id', $series->id)
                ->where('player_id', $player->id)
                ->update(['rank_position' => $rank]);
        }

        $this->get(route('frontend.ranking.show', $series))
            ->assertOk()
            ->assertSee('Eligible First')
            ->assertSee('Eligible Next')
            ->assertDontSee('One Event Player')
            ->assertSee('Players ranked</span></div>', false)
            ->assertSee('public-ranking-stat__value">2</span><span class="public-ranking-stat__label">Players ranked', false)
            ->assertSee('public-ranking-rank">#1</td>', false)
            ->assertSee('public-ranking-rank">#2</td>', false)
            ->assertDontSee('public-ranking-rank">#3</td>', false)
            ->assertDontSee('Not eligible for team selection');
    }

    public function test_direct_leaderboard_url_returns_404_when_series_is_not_published(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => false]);

        $this->get(route('frontend.ranking.show', $series))->assertNotFound();
    }

    public function test_player_detail_does_not_expose_non_published_row(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $player = Player::factory()->create(['name' => 'Calculated Only']);
        $this->row($series, $list, $category, $player, RankingStatus::Calculated, 'run-draft');

        $this->get(route('frontend.ranking.player-detail', [$series, $player]))->assertNotFound();
    }

    public function test_published_legacy_leaderboard_without_run_ids_remains_visible(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $legacy = Player::factory()->create(['name' => 'Legacy Published Player']);
        $draft = Player::factory()->create(['name' => 'Canonical Draft Player']);

        $this->row($series, $list, $category, $legacy, RankingStatus::Calculated, null);
        $this->row($series, $list, $category, $draft, RankingStatus::Calculated, 'run-draft');

        $this->get(route('frontend.ranking.show', $series))
            ->assertOk()
            ->assertSee('Legacy Published Player')
            ->assertDontSee('Canonical Draft Player');

        $this->get(route('frontend.ranking.player-detail', [$series, $legacy]))->assertOk();
        $this->get(route('frontend.ranking.player-detail', [$series, $draft]))->assertNotFound();
    }

    public function test_public_and_authenticated_leaderboards_show_counted_and_dropped_event_scores(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create(['name' => 'u/10 Girls - A division']);
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $player = Player::factory()->create(['name' => 'Nicola', 'surname' => 'Middleton']);
        $countedEvent = Event::factory()->create(['series_id' => $series->id]);
        $droppedEvent = Event::factory()->create(['series_id' => $series->id]);
        $countedCategoryEvent = CategoryEvent::factory()->create([
            'event_id' => $countedEvent->id,
            'category_id' => $category->id,
        ]);
        $droppedCategoryEvent = CategoryEvent::factory()->create([
            'event_id' => $droppedEvent->id,
            'category_id' => $category->id,
        ]);

        $this->row(
            $series,
            $list,
            $category,
            $player,
            RankingStatus::Published,
            'run-live',
            now(),
            [
                'counting_legs' => [[
                    'category_event_id' => $countedCategoryEvent->id,
                    'position' => 1,
                    'points' => 100,
                    'synthetic' => false,
                ]],
                'dropped_legs' => [[
                    'category_event_id' => $droppedCategoryEvent->id,
                    'position' => 4,
                    'points' => 40,
                ]],
            ]
        );

        foreach ([null, User::factory()->create()] as $user) {
            if ($user) {
                $this->actingAs($user);
            }

            $this->get(route('frontend.ranking.show', $series))
                ->assertOk()
                ->assertSee('Total points')
                ->assertSee('Scores per event')
                ->assertSee('100')
                ->assertSee('40')
                ->assertDontSee("100 (E{$countedEvent->id})")
                ->assertDontSee("40 (E{$droppedEvent->id})")
                ->assertSee('public-ranking-score--counted', false)
                ->assertSee('public-ranking-score--dropped', false);
        }
    }

    public function test_public_leaderboard_explains_an_automatic_third_event_tiebreak(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create(['name' => 'u/12 Boys']);
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $tiedPlayer = Player::factory()->create(['name' => 'Third', 'surname' => 'Score']);
        $ordinaryPlayer = Player::factory()->create(['name' => 'No', 'surname' => 'Tie']);

        $this->row(
            $series,
            $list,
            $category,
            $tiedPlayer,
            RankingStatus::Published,
            'run-live',
            now(),
            [
                'tiebreak_notes' => ['Tied on 1800 points; compared by third-event score (600 points).'],
                'counting_legs' => [
                    ['position' => 1, 'points' => 1000],
                    ['position' => 2, 'points' => 800],
                ],
                'dropped_legs' => [
                    ['position' => 3, 'points' => 600],
                ],
            ]
        );
        $this->row($series, $list, $category, $ordinaryPlayer, RankingStatus::Published, 'run-live', now());

        $tiedRow = SeriesRanking::where('player_id', $tiedPlayer->id)->firstOrFail();
        $ordinaryRow = SeriesRanking::where('player_id', $ordinaryPlayer->id)->firstOrFail();

        $this->get(route('frontend.ranking.show', $series))
            ->assertOk()
            ->assertSee('How the tie was broken')
            ->assertSee('Third-event score')
            ->assertSee('The higher third-event score determined the order.')
            ->assertSee('This player’s comparison score')
            ->assertSee('600 points')
            ->assertSee('data-bs-target="#tie-break-'.$tiedRow->id.'"', false)
            ->assertDontSee('data-bs-target="#tie-break-'.$ordinaryRow->id.'"', false);
    }

    public function test_public_leaderboard_explains_an_automatic_latest_played_leg_placing_tiebreak(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create(['name' => 'u/13 Girls']);
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $player = Player::factory()->create(['name' => 'Final', 'surname' => 'Leg']);

        $this->row(
            $series,
            $list,
            $category,
            $player,
            RankingStatus::Published,
            'run-live',
            now(),
            [
                'tiebreak_notes' => ['Tied on 1800 points and third-event score; compared by latest-played-leg placing (3rd at Witzenberg Leg 3).'],
                'last_leg_position_decision' => [
                    'event_name' => 'Witzenberg Leg 3',
                    'event_date' => '2026-08-30',
                    'positions' => [$player->id => 3],
                ],
            ]
        );

        $this->get(route('frontend.ranking.show', $series))
            ->assertOk()
            ->assertSee('Latest-played-leg placing')
            ->assertSee('Witzenberg Leg 3')
            ->assertSee('This player’s finish in that leg')
            ->assertSee('#3')
            ->assertSee('The higher finish determined the order.');
    }

    public function test_public_head_to_head_explanation_omits_private_admin_details(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create(['name' => 'u/14 Girls']);
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $winner = Player::factory()->create(['name' => 'Head', 'surname' => 'Winner']);
        $loser = Player::factory()->create(['name' => 'Head', 'surname' => 'Runner-up']);
        $decision = [
            'reason' => 'head_to_head',
            'note' => 'Internal seeding note that must stay private.',
            'confirmed_by' => 987654,
            'confirmed_at' => now()->toIso8601String(),
            'head_to_head_decision' => [
                'winner_player_id' => $winner->id,
                'event_name' => 'Overberg Championship Final',
                'phase' => 'playoff',
                'qualifying_set' => ['score' => '7-5'],
            ],
        ];

        foreach ([[$winner, 1], [$loser, 2]] as [$player, $rank]) {
            $this->row(
                $series,
                $list,
                $category,
                $player,
                RankingStatus::Published,
                'run-live',
                now(),
                [
                    'tiebreak_notes' => ['Tied on 1600 points and third-event score; ordered by latest head-to-head winner.'],
                    'tie_decision' => $decision,
                ]
            );
            SeriesRanking::where('player_id', $player->id)->update([
                'rank_position' => $rank,
                'total_points' => 1600,
            ]);
        }

        $this->get(route('frontend.ranking.show', $series))
            ->assertOk()
            ->assertSee('Qualifying head-to-head')
            ->assertSee('Overberg Championship Final')
            ->assertSee('7-5')
            ->assertSee('Playoff')
            ->assertSee('This player won the qualifying head-to-head')
            ->assertDontSee('Internal seeding note that must stay private.')
            ->assertDontSee('987654');
    }

    public function test_public_explanation_uses_the_amended_admin_reason_instead_of_old_head_to_head_evidence(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create(['name' => 'u/14 Boys']);
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $player = Player::factory()->create();

        $this->row(
            $series,
            $list,
            $category,
            $player,
            RankingStatus::Published,
            'run-live',
            now(),
            [
                'tiebreak_notes' => ['Tie broken by previous published ranking — administrator confirmed.'],
                'tie_decision' => [
                    'reason' => 'previous_ranking',
                    'confirmed_at' => now()->toIso8601String(),
                    'head_to_head_decision' => [
                        'winner_player_id' => $player->id,
                        'event_name' => 'Earlier head-to-head evidence',
                        'phase' => 'playoff',
                        'qualifying_set' => ['score' => '6-4'],
                    ],
                ],
            ]
        );

        $this->get(route('frontend.ranking.show', $series))
            ->assertOk()
            ->assertSee('Previous published ranking')
            ->assertDontSee('Earlier head-to-head evidence');
    }

    public function test_player_event_names_prefer_published_draws_then_results_then_not_available(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create(['name' => 'u/13 Girls']);
        $otherCategory = Category::factory()->create();
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $player = Player::factory()->create();

        $drawEvent = Event::factory()->create([
            'series_id' => $series->id,
            'name' => 'Draw Event',
            'results_published' => true,
        ]);
        $resultsEvent = Event::factory()->create([
            'series_id' => $series->id,
            'name' => 'Results Event',
            'results_published' => true,
        ]);
        $unavailableEvent = Event::factory()->create([
            'series_id' => $series->id,
            'name' => 'Unavailable Event',
            'results_published' => false,
        ]);

        $drawCategoryEvent = CategoryEvent::factory()->create(['event_id' => $drawEvent->id, 'category_id' => $category->id]);
        $resultsCategoryEvent = CategoryEvent::factory()->create(['event_id' => $resultsEvent->id, 'category_id' => $category->id]);
        $unavailableCategoryEvent = CategoryEvent::factory()->create(['event_id' => $unavailableEvent->id, 'category_id' => $category->id]);
        $wrongCategoryEvent = CategoryEvent::factory()->create(['event_id' => $resultsEvent->id, 'category_id' => $otherCategory->id]);

        $publishedDraw = Draw::factory()->create([
            'event_id' => $drawEvent->id,
            'category_event_id' => $drawCategoryEvent->id,
            'published' => true,
        ]);
        Draw::factory()->create([
            'event_id' => $resultsEvent->id,
            'category_event_id' => $wrongCategoryEvent->id,
            'published' => true,
        ]);
        Draw::factory()->create([
            'event_id' => $unavailableEvent->id,
            'category_event_id' => $unavailableCategoryEvent->id,
            'published' => false,
        ]);

        $this->row(
            $series,
            $list,
            $category,
            $player,
            RankingStatus::Published,
            'run-live',
            now(),
            [
                'counting_legs' => [
                    ['category_event_id' => $drawCategoryEvent->id, 'position' => 1, 'points' => 100],
                    ['category_event_id' => $resultsCategoryEvent->id, 'position' => 2, 'points' => 80],
                    ['category_event_id' => $unavailableCategoryEvent->id, 'position' => 3, 'points' => 60],
                ],
            ]
        );

        $this->get(route('frontend.ranking.player-detail', [$series, $player]))
            ->assertOk()
            ->assertSee(route('public.roundrobin.show', $publishedDraw), false)
            ->assertSee(route('events.results', $resultsEvent), false)
            ->assertDontSee(route('events.results', $drawEvent), false)
            ->assertSee('View draw')
            ->assertSee('View results')
            ->assertSee('Not available');
    }

    private function row(
        Series $series,
        RankingList $list,
        Category $category,
        Player $player,
        RankingStatus $status,
        ?string $runId,
        $publishedAt = null,
        array $meta = [],
    ): void {
        SeriesRanking::create([
            'series_id' => $series->id,
            'ranking_list_id' => $list->id,
            'category_id' => $category->id,
            'player_id' => $player->id,
            'rank_position' => 1,
            'total_points' => 1000,
            'status' => $status->value,
            'run_id' => $runId,
            'published_at' => $publishedAt,
            'meta_json' => $meta,
        ]);
    }
}
