<?php

namespace Tests\Feature;

use App\Domain\Draws\Enums\FixtureState;
use App\Models\{CategoryEvent, Draw, Event, Fixture, Player, Registration, Team, TeamFixture, TeamTie, User};
use App\Services\Performance\{PlayerPlusRecordService, PlayerRatingHistoryService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MyTennisLiveResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_scores_appear_on_the_next_read_and_corrections_replace_them(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $opponent = Player::factory()->create(['name' => 'VisibleOpponent']);
        $event = Event::factory()->create(['published' => true, 'results_published' => false]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => true]);
        $registrations = Registration::factory()->count(2)->create();
        foreach ([$opponent, $player] as $side => $entrant) {
            $registrations[$side]->players()->attach($entrant);
            $registrations[$side]->categoryEvents()->attach($category);
        }
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id, 'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id,
            'match_status' => FixtureState::STATUS_PARTIAL,
        ]);
        $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 2, 'registration2_score' => 6]);
        $this->mock(PlayerPlusRecordService::class)->shouldNotReceive('forPlayer');
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('recordSource');

        Gate::define('player-premium-rating', fn () => false);
        $url = route('my.tennis.record.live', $player);
        $this->get($url)->assertRedirect();
        $this->actingAs($user)->get($url)->assertForbidden();
        Gate::define('player-premium-rating', fn () => true);
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $this->actingAs($user)->get($url)->assertOk()->assertDontSee('VisibleOpponent');
        $second = $fixture->fixtureResults()->create(['set_nr' => 2, 'registration1_score' => 3, 'registration2_score' => 6]);
        $fixture->update(['match_status' => FixtureState::STATUS_COMPLETED, 'winner_registration' => $registrations[1]->id]);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('VisibleOpponent')->assertSee('Win')->assertSee('6-2 6-3');
        $second->update(['registration1_score' => 4]);
        $this->get($url)->assertOk()->assertSee('6-2 6-4')->assertDontSee('6-2 6-3');

        $draw->update(['published' => false]);
        $this->get($url)->assertOk()->assertDontSee('VisibleOpponent');
        $draw->update(['published' => true]);
        $event->update(['published' => false]);
        $this->get($url)->assertOk()->assertDontSee('VisibleOpponent');
        $event->update(['published' => true]);
        $registrations[0]->categoryEvents()->detach($category);
        $this->get($url)->assertOk()->assertDontSee('VisibleOpponent');
    }

    public function test_team_rubber_requires_a_published_tie_and_exact_event_participants(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $opponent = Player::factory()->create(['name' => 'TeamOpponent']);
        $event = Event::factory()->create(['published' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => true]);
        $teams = Team::factory()->count(2)->create(['category_event_id' => $category->id]);
        foreach ([$player, $opponent] as $side => $entrant) {
            $teams[$side]->team_players()->create(['player_id' => $entrant->id, 'rank' => 1]);
        }
        $tie = TeamTie::create([
            'draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id,
            'status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now(),
        ]);
        $fixture = TeamFixture::create([
            'draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1, 'numSets' => 1,
            'match_nr' => 1, 'round_nr' => 1, 'match_status' => FixtureState::STATUS_COMPLETED,
        ]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $player->id, 'team2_id' => $opponent->id]);
        $fixture->teamResults()->create(['set_nr' => 1, 'team1_score' => 4, 'team2_score' => 6]);

        Gate::define('player-premium-rating', fn () => true);
        $url = route('my.tennis.record.live', $player);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('TeamOpponent')->assertSee('Loss')->assertSee('4-6');
        $fixture->update(['numSets' => 3]);
        $this->get($url)->assertOk()->assertDontSee('TeamOpponent');
        $fixture->update(['numSets' => 1]);
        $tie->update(['published_at' => null]);
        $this->get($url)->assertOk()->assertDontSee('TeamOpponent');
        $tie->update(['published_at' => now()]);
        $foreignCategory = CategoryEvent::factory()->create(['event_id' => Event::factory()->create()->id]);
        $teams[1]->update(['category_event_id' => $foreignCategory->id]);
        $this->get($url)->assertOk()->assertDontSee('TeamOpponent');
        $teams[1]->update(['category_event_id' => $category->id]);
        $fixture->teamResults()->delete();
        $this->get($url)->assertOk()->assertDontSee('TeamOpponent');
    }
}
