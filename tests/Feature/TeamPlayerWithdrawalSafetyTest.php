<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Draw;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamFixture;
use App\Models\TeamFixturePlayer;
use App\Models\TeamFixtureResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPlayerWithdrawalSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_cannot_be_requested_before_paid_player_is_withdrawn(): void
    {
        [$user, $event, $team, $player] = $this->paidTeamPlayer();

        $response = $this->actingAs($user)->post(
            route('team.player.refund.request', [$team, $player, $event]),
            ['method' => 'wallet']
        );

        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('team_players', [
            'team_id' => $team->id,
            'player_id' => $player->id,
            'pay_status' => 1,
        ]);
    }

    public function test_team_withdrawal_rejects_an_event_the_team_does_not_belong_to(): void
    {
        [$user, $event, $team, $player] = $this->paidTeamPlayer();
        $otherEvent = Event::factory()->create();

        $this->actingAs($user)->post(
            route('team.player.withdraw', [$team, $player, $otherEvent])
        )->assertNotFound();

        $this->assertDatabaseHas('team_players', [
            'team_id' => $team->id,
            'player_id' => $player->id,
            'pay_status' => 1,
        ]);
    }

    public function test_late_paid_withdrawal_frees_slot_when_no_refund_flow_follows(): void
    {
        [$user, $event, $team, $player] = $this->paidTeamPlayer();
        $event->update(['withdrawal_deadline' => now()->subDay()]);

        $this->actingAs($user)->post(
            route('team.player.withdraw', [$team, $player, $event])
        )->assertRedirect();

        $this->assertDatabaseHas('team_players', [
            'team_id' => $team->id,
            'player_id' => 0,
            'pay_status' => 0,
        ]);
    }

    public function test_withdrawal_clears_future_fixture_assignments_but_preserves_completed_history(): void
    {
        [$user, $event, $team, $player] = $this->paidTeamPlayer();
        $event->update(['withdrawal_deadline' => now()->subDay()]);
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $future = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1]);
        $completed = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 2]);
        $futureAssignment = TeamFixturePlayer::create([
            'team_fixture_id' => $future->id, 'team1_id' => $player->id, 'team2_id' => null,
        ]);
        $completedAssignment = TeamFixturePlayer::create([
            'team_fixture_id' => $completed->id, 'team1_id' => $player->id, 'team2_id' => null,
        ]);
        TeamFixtureResult::create([
            'team_fixture_id' => $completed->id, 'set_nr' => 1,
            'team1_score' => 6, 'team2_score' => 3,
        ]);

        $this->actingAs($user)->post(
            route('team.player.withdraw', [$team, $player, $event])
        )->assertRedirect();

        $this->assertNull($futureAssignment->fresh()->team1_id);
        $this->assertEquals($player->id, $completedAssignment->fresh()->team1_id);
    }

    private function paidTeamPlayer(): array
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $user->players()->attach($player->id);
        $event = Event::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $categoryEvent->id]);
        TeamPlayer::create([
            'team_id' => $team->id,
            'player_id' => $player->id,
            'rank' => 1,
            'pay_status' => 1,
        ]);

        return [$user, $event, $team, $player];
    }
}
