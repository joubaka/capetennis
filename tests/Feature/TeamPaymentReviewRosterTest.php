<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPaymentReviewRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_retained_payment_replaces_register_on_normal_and_imported_rosters_without_exposing_receipt_details(): void
    {
        $event = Event::factory()->create(['published' => true, 'signUp' => true, 'status' => 'active', 'start_date' => now()->addMonth()]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $category->id, 'published' => true]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'player_profile' => $player->id, 'name' => $player->name, 'surname' => $player->surname, 'rank' => 1, 'pay_status' => 0]);
        $data = ['event' => $event, 'team' => $team->fresh(), 'region' => new TeamRegion(['clothing_order' => 0]),
            'teamPaymentsUnderReview' => collect([$team->id.'-'.$player->id => true])];

        foreach (['profile-team', 'no-profile-team'] as $partial) {
            $html = view('frontend.event.partials.'.$partial, $data)->render();
            $this->assertStringContainsString('Payment being checked', $html);
            $this->assertStringContainsString('Please do not pay again.', $html);
            $this->assertStringNotContainsString('team-registration-button', $html);
            $this->assertStringNotContainsString('pf_payment_id', $html);

            $otherTeam = view('frontend.event.partials.'.$partial, array_replace($data, [
                'teamPaymentsUnderReview' => collect([($team->id + 1).'-'.$player->id => true]),
            ]))->render();
            $this->assertStringNotContainsString('Payment being checked', $otherTeam);
        }
    }
}
