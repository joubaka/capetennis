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

    public function test_lucky_loser_results_require_audited_same_event_source_without_creating_an_entry(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $opponent = Player::factory()->create();
        $event = Event::factory()->create(['published' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $otherDivision = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => true]);
        $draw->settings()->create(['score_format' => 'one_set_to_6', 'num_sets' => 1]);
        $source = Registration::factory()->create();
        $source->players()->attach($player);
        $entry = \App\Models\CategoryEventRegistration::factory()->create(['registration_id' => $source->id,
            'category_event_id' => $otherDivision->id, 'payment_status_id' => 1, 'withdrawn_at' => null, 'status' => 'confirmed']);
        $alias = Registration::factory()->create(['competition_replacement' => ['draw_id' => $draw->id,
            'source_entry_id' => $entry->id, 'source_registration_id' => $source->id, 'show_lucky_loser' => true]]);
        $alias->players()->attach($player);
        $other = Registration::factory()->create();
        $other->players()->attach($opponent);
        $other->categoryEvents()->attach($category);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN',
            'registration1_id' => $alias->id, 'registration2_id' => $other->id,
            'match_status' => FixtureState::STATUS_COMPLETED, 'winner_registration' => $alias->id]);
        $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $record = ['incoming_registration_id' => $alias->id, 'source_entry_id' => $entry->id, 'source_registration_id' => $source->id];
        $service = app(\App\Services\Performance\PlayerLiveResults::class);
        $this->assertCount(0, $service->forPlayer($user, $player));
        $draw->team_draw_selection = ['playoff_lucky_losers' => [$fixture->id.':1' => $record]];
        $draw->save();
        $this->assertCount(1, $service->forPlayer($user, $player));
        $this->assertSame(0, $alias->categoryEvents()->count());
        $foreign = CategoryEvent::factory()->create(['event_id' => Event::factory()->create()->id]);
        $entry->update(['category_event_id' => $foreign->id]);
        $this->assertCount(0, $service->forPlayer($user, $player));
        $entry->update(['category_event_id' => $otherDivision->id]);
        $entry->delete();
        $this->assertCount(0, $service->forPlayer($user, $player));
    }

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
        $this->mock(\App\Services\Performance\PlayerRatingTopTen::class)->shouldReceive('forMatchPlayers')->twice()
            ->withArgs(fn (User $viewer, Player $selected, array $ids) => $viewer->is($user) && $selected->is($player) && $ids === [$opponent->id])
            ->andReturn([$opponent->id => ['score' => 64.6]]);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('VisibleOpponent')->assertSee('CTR 64.6')->assertSee('Win')->assertSee('6-2 6-3');
        $second->update(['registration1_score' => 4]);
        $this->get($url)->assertOk()->assertSee('6-2 6-4')->assertDontSee('6-2 6-3');

        $fixture->update(['round_robin_voided_registration_id' => $registrations[0]->id]);
        $this->get($url)->assertOk()->assertDontSee('VisibleOpponent');
        $this->assertSame(2, $fixture->fixtureResults()->count());
        $fixture->update(['round_robin_voided_registration_id' => null]);

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
        $this->mock(\App\Services\Performance\PlayerRatingTopTen::class)->shouldReceive('forMatchPlayers')->once()
            ->withArgs(fn (User $viewer, Player $selected, array $ids) => $viewer->is($user) && $selected->is($player) && $ids === [$opponent->id])
            ->andReturn([$opponent->id => ['score' => 63.7]]);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('TeamOpponent')->assertSee('CTR 63.7')->assertSee('Loss')->assertSee('4-6');
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

    public function test_rating_directory_accepts_public_team_participation_without_a_series_and_rechecks_its_context(): void
    {
        $players = Player::factory()->count(2)->create();
        $event = Event::factory()->create(['published' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => true]);
        $teams = Team::factory()->count(2)->create(['category_event_id' => $category->id]);
        foreach ($players as $side => $player) {
            $teams[$side]->team_players()->create(['player_id' => $player->id, 'rank' => 1]);
        }
        $tie = TeamTie::create([
            'draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id,
            'status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now(),
        ]);
        $fixture = TeamFixture::create([
            'draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1,
            'numSets' => 1, 'match_nr' => 1, 'round_nr' => 1,
        ]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $players[0]->id, 'team2_id' => $players[1]->id]);
        $ratings = [];
        foreach ($players as $index => $player) {
            $ratings[$player->id] = ['component' => 'shared', 'comparable' => true, 'score' => 80 - $index, 'confidence_label' => 'Moderate'];
        }
        $snapshot = ['reason' => null, 'all_age' => [
            'reference_component' => 'shared',
            'components' => ['shared' => ['converged' => true, 'members' => $players->modelKeys()]],
            'ratings' => $ratings,
        ]];
        $snapshot['ball_ratings']['standard'] = $snapshot['all_age'];
        $this->mock(\App\Services\Performance\PlayerAbilitySnapshotStore::class)->shouldReceive('current')->times(4)->andReturn($snapshot);
        $this->mock(\App\Services\Performance\PlayerRatingAccess::class)->shouldReceive('allowsDiscovery')->times(4)->andReturnTrue();
        $viewer = \Mockery::mock(User::class)->makePartial();
        $viewer->shouldReceive('hasRole')->with('super-user')->andReturnTrue();
        $service = app(\App\Services\Performance\PlayerRatingTopTen::class);
        $this->assertDatabaseCount('series_rankings', 0);
        $this->assertSame($players->modelKeys(), array_column($service->build($viewer)['lists']['overall']['players'], 'id'));
        $tie->update(['published_at' => null]);
        $this->assertSame([], $service->build($viewer)['lists']['overall']['players']);
        $tie->update(['published_at' => now()]);
        $foreignCategory = CategoryEvent::factory()->create(['event_id' => Event::factory()->create()->id]);
        $teams[1]->update(['category_event_id' => $foreignCategory->id]);
        $this->assertSame([], $service->build($viewer)['lists']['overall']['players']);
        $teams[1]->update(['category_event_id' => $category->id]);
        $event->update(['published' => false]);
        $this->assertSame([], $service->build($viewer)['lists']['overall']['players']);
    }
}
