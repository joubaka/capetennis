<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamFixture;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamWorkspaceResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_singles_use_match_outcome_and_actual_roster_rank(): void
    {
        [$event, $category, $draw, $home, $away] = $this->scenario();
        // Historic set rows can have a last-set winner different from the match winner.
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1], [1, 6]]);
        $this->fixture($draw, $home, $away, [[6, 1]], 1); // Incomplete best of three.
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]], 2); // Doubles.
        $foreignCategory = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $foreignDraw = Draw::factory()->create(['event_id' => $event->id,
            'category_event_id' => $foreignCategory->id, 'drawName' => $category->category->name]);
        $this->fixture($foreignDraw, $home, $away, [[1, 6], [1, 6]]);
        $otherEventDraw = Draw::factory()->create(['event_id' => Event::factory()->create()->id,
            'drawName' => $category->category->name]);
        $this->fixture($otherEventDraw, $home, $away, [[1, 6], [1, 6]]);
        $response = $this->postJson(route('get.event.category.data'), [
            'event_id' => $event->id, 'categoryEvent' => $category->id,
        ])->assertOk()->assertJsonCount(2, 'ranking');
        $ranking = collect($response->json('ranking'))->keyBy('name');
        $this->assertSame(3, $ranking[$home->name.' '.$home->surname]['rank']);
        $this->assertSame(70, $ranking[$home->name.' '.$home->surname]['points']);
        $this->assertSame(0, $ranking[$away->name.' '.$away->surname]['points']);
        $this->assertStringContainsString('Won', $response->json('html'));
        $this->assertStringContainsString('Lost', $response->json('html'));
    }

    public function test_unplayed_and_partial_singles_produce_empty_results(): void
    {
        [$event, $category, $draw, $home, $away] = $this->scenario();
        $this->fixture($draw, $home, $away, []);
        $this->fixture($draw, $home, $away, [[6, 2]]);
        $response = $this->postJson(route('get.event.category.data'), [
            'event_id' => $event->id, 'categoryEvent' => $category->id,
        ])->assertOk()->assertJsonCount(0, 'ranking');
        $this->assertStringContainsString('No results recorded', $response->json('html'));
    }

    public function test_result_requests_preserve_event_authorization_and_category_isolation(): void
    {
        [$event, $category] = $this->scenario();
        $foreignCategory = CategoryEvent::factory()->create();
        $this->postJson(route('get.event.category.data'), [
            'event_id' => $event->id, 'categoryEvent' => $foreignCategory->id,
        ])->assertNotFound();
        $this->actingAs(User::factory()->create())->postJson(route('get.event.category.data'), [
            'event_id' => $event->id, 'categoryEvent' => $category->id,
        ])->assertForbidden();
    }

    private function scenario(): array
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        $home = Player::factory()->create();
        $away = Player::factory()->create();
        foreach ([[$home, 3], [$away, 4]] as [$player, $rank]) {
            $team = Team::factory()->create(['category_event_id' => $category->id]);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank, 'pay_status' => 1]);
        }
        return [$event, $category, $draw, $home, $away];
    }

    private function fixture(Draw $draw, Player $home, Player $away, array $sets, int $type = 1): void
    {
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => $type,
            'numSets' => 3, 'round_nr' => 1, 'match_nr' => 1]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $home->id, 'team2_id' => $away->id]);
        foreach ($sets as $index => [$homeScore, $awayScore]) {
            $fixture->teamResults()->create(['set_nr' => $index + 1, 'team1_score' => $homeScore, 'team2_score' => $awayScore]);
        }
    }
}
