<?php

namespace Tests\Feature\Authorization;

use App\Models\{Draw, Event, Player, Team, TeamFixture, TeamFixturePlayer, TeamFixtureResult, TeamTie, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerPrivateResultArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function recordedMatch(Draw $draw, Player $first, Player $second, bool $scored = true): TeamFixturePlayer
    {
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'numSets' => 1, 'match_nr' => 1, 'match_status' => 1]);
        $row = TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_id' => $first->id, 'team2_id' => $second->id]);
        if ($scored) TeamFixtureResult::create(['team_fixture_id' => $fixture->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 4]);
        return $row;
    }

    public function test_related_free_viewer_receives_only_published_own_results_with_safe_opponent_fields(): void
    {
        $user = User::factory()->create(); $player = Player::factory()->create(['userId' => $user->id]);
        $opponent = Player::factory()->create(['email' => 'private-opponent@example.test', 'cellNr' => '0829999999', 'dateOfBirth' => '2012-05-17']);
        $other = Player::factory()->create();
        $event = Event::factory()->create(['name' => 'Published result event', 'published' => true, 'results_published' => true]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $this->recordedMatch($draw, $player, $opponent); $this->recordedMatch($draw, $opponent, $player);
        $this->recordedMatch($draw, $player, $opponent, false); $this->recordedMatch($draw, $opponent, $other);
        $privateDraw = Draw::factory()->create(['event_id' => $event->id, 'published' => false]);
        $this->recordedMatch($privateDraw, $player, $opponent);
        $teams = Team::factory()->count(2)->create();
        $foreignTie = TeamTie::create(['draw_id' => $privateDraw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id, 'status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now()]);
        $wrongDraw = $this->recordedMatch($draw, $player, $opponent);
        $wrongDraw->fixture->update(['team_tie_id' => $foreignTie->id]);
        foreach ([['published' => false, 'results_published' => true], ['published' => true, 'results_published' => false]] as $publication) {
            $hiddenEvent = Event::factory()->create($publication);
            $this->recordedMatch(Draw::factory()->create(['event_id' => $hiddenEvent->id, 'published' => true]), $player, $opponent);
        }
        $response = $this->actingAs($user)->getJson(route('player.events.results', $player->id))->assertOk()
            ->assertJsonCount(2, 'fixture')->assertJsonPath('event.0', 'Published result event')
            ->assertJsonPath('fixture.0.fixture.team_results.0.team1_score', 6)
            ->assertJsonPath('fixture.0.fixture.team_results.0.team2_score', 4)
            ->assertDontSee('private-opponent@example.test')->assertDontSee('0829999999')->assertDontSee('2012-05-17');
        foreach (['email', 'cellNr', 'dateOfBirth', 'userId', 'fixture_players', 'team_format_snapshot'] as $privateField) $this->assertStringNotContainsString('"'.$privateField.'"', $response->getContent());
        $this->assertStringContainsString($opponent->surname, $response->getContent());
        $this->assertSame(['team1', 'team2', 'fixture'], array_values(array_intersect(['team1', 'team2', 'fixture'], array_keys($response->json('fixture.0')))));
        $this->actingAs($user)->get(route('player.results', $player->id))->assertOk()->assertSee('Published result event')->assertSee('6-4;');
        $this->actingAs($user)->getJson(route('player.events.results', $other->id))->assertForbidden();
    }

    public function test_archive_is_bounded_and_staff_can_still_review_unpublished_records(): void
    {
        $user = User::factory()->create(); $player = Player::factory()->create(['userId' => $user->id]); $opponent = Player::factory()->create();
        $event = Event::factory()->create(['published' => true, 'results_published' => true]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        for ($i = 0; $i < 101; $i++) $this->recordedMatch($draw, $player, $opponent);
        $this->actingAs($user)->getJson(route('player.events.results', $player->id))->assertOk()->assertJsonCount(100, 'fixture')->assertJsonPath('has_more', true)->assertJsonPath('limit', 100);
        $draw->update(['published' => false]);
        $this->actingAs($user)->getJson(route('player.events.results', $player->id))->assertOk()->assertJsonCount(0, 'fixture');
        Role::findOrCreate('admin', 'web'); $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->getJson(route('player.events.results', $player->id))->assertOk()->assertJsonCount(100, 'fixture');
    }
}
