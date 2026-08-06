<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
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
