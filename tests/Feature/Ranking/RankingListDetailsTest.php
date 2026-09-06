<?php

namespace Tests\Feature\Ranking;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\CategoryResult;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RankingListDetailsTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    public function test_score_card_shows_actual_finish_and_links_to_the_event_category(): void
    {
        $series = Series::factory()->create();
        $category = Category::factory()->create(['name' => 'U/12 Girls']);
        $event = Event::factory()->create([
            'series_id' => $series->id,
            'name' => 'Overberg Leg 3',
            'results_published' => true,
        ]);
        $this->authorizeEvent($event);
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'category_id' => $category->id,
        ]);
        $player = Player::factory()->create(['name' => 'Amy', 'surname' => 'Adams']);
        $registration = $this->registrationFor($player);
        CategoryResult::create([
            'event_id' => $event->id,
            'category_id' => $category->id,
            'registration_id' => $registration->id,
            'position' => 1,
        ]);
        SeriesRanking::create([
            'series_id' => $series->id,
            'category_id' => $category->id,
            'player_id' => $player->id,
            'rank_position' => 1,
            'total_points' => 800,
            'status' => 'calculated',
            'run_id' => 'ranking-details',
            'meta_json' => [
                'counting_legs' => [[
                    'category_event_id' => $categoryEvent->id,
                    'position' => 2,
                    'points' => 800,
                    'synthetic' => false,
                ]],
                'dropped_legs' => [],
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('ranking.series.list', $series))
            ->assertOk()
            ->assertSee('Overberg Leg 3')
            ->assertSee('Finished #1')
            ->assertSee('Ranking points position #2')
            ->assertSee(route('admin.events.results.individual', $event).'#category-event-'.$categoryEvent->id, false);
    }

    public function test_tied_players_show_the_latest_recorded_head_to_head(): void
    {
        $series = Series::factory()->create();
        $category = Category::factory()->create(['name' => 'U/14 Boys']);
        $event = Event::factory()->create([
            'series_id' => $series->id,
            'name' => 'Overberg Leg 2',
            'start_date' => '2026-05-02',
            'results_published' => true,
        ]);
        $this->authorizeEvent($event);
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'category_id' => $category->id,
        ]);
        $winner = Player::factory()->create(['name' => 'Winner', 'surname' => 'Player']);
        $loser = Player::factory()->create(['name' => 'Other', 'surname' => 'Player']);
        $winnerRegistration = $this->registrationFor($winner);
        $loserRegistration = $this->registrationFor($loser);
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'category_event_id' => $categoryEvent->id,
        ]);
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'registration1_id' => $winnerRegistration->id,
            'registration2_id' => $loserRegistration->id,
            'winner_registration' => $winnerRegistration->id,
        ]);
        FixtureResult::factory()->create([
            'fixture_id' => $fixture->id,
            'winner_registration' => $winnerRegistration->id,
            'loser_registration' => $loserRegistration->id,
            'registration1_score' => 6,
            'registration2_score' => 3,
        ]);

        foreach ([[$winner, 1], [$loser, 2]] as [$player, $rank]) {
            SeriesRanking::create([
                'series_id' => $series->id,
                'category_id' => $category->id,
                'player_id' => $player->id,
                'rank_position' => $rank,
                'total_points' => 1600,
                'status' => 'calculated',
                'run_id' => 'ranking-head-to-head',
                'meta_json' => [],
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('ranking.series.list', $series))
            ->assertOk()
            ->assertSee('Head-to-head review')
            ->assertSee('Winner Player')
            ->assertSee('beat Other Player')
            ->assertSee('(6-3)')
            ->assertSee('The most recent match is used only after the best-two total and third-event score remain tied.');
    }

    public function test_event_results_page_exposes_a_category_anchor(): void
    {
        $series = Series::factory()->create();
        $category = Category::factory()->create();
        $event = Event::factory()->create(['series_id' => $series->id]);
        $this->authorizeEvent($event);
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.events.results.individual', $event))
            ->assertOk()
            ->assertSee('id="category-event-'.$categoryEvent->id.'"', false);
    }

    private function registrationFor(Player $player): Registration
    {
        $registration = Registration::factory()->create();
        $registration->players()->attach($player->id);

        return $registration;
    }

    private function authorizeEvent(Event $event): void
    {
        DB::table('event_admins')->insert([
            'event_id' => $event->id,
            'user_id' => $this->admin->id,
        ]);
    }
}
