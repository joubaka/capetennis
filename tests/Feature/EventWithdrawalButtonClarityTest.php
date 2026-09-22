<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventWithdrawalButtonClarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_withdrawal_control_pairs_its_cross_with_an_explicit_label(): void
    {
        $template = file_get_contents(resource_path('views/frontend/event/show.blade.php'));

        $this->assertStringContainsString('aria-label="Withdraw entry for {{ $registration->display_name }}"', $template);
        $this->assertStringContainsString('ti ti-x me-1', $template);
        $this->assertStringContainsString('<span class="small">Withdraw</span>', $template);
    }

    public function test_team_withdrawal_control_pairs_its_cross_with_an_explicit_label(): void
    {
        $template = file_get_contents(resource_path('views/frontend/event/partials/profile-team.blade.php'));

        $this->assertStringContainsString('aria-label="Withdraw {{ $playerName }} from this team"', $template);
        $this->assertStringContainsString('ti ti-x me-1', $template);
        $this->assertStringContainsString('</i>Withdraw', $template);
    }

    public function test_linked_paid_imported_roster_row_shows_withdraw_only_for_matching_payer_order(): void
    {
        $event = Event::factory()->create([
            'signUp' => true,
            'status' => 'active',
            'start_date' => now()->addMonth(),
        ]);
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create([
            'category_event_id' => $categoryEvent->id,
            'published' => true,
            'noProfile' => true,
        ]);
        $player = Player::factory()->create();
        NoProfileTeamPlayer::create([
            'team_id' => $team->id,
            'rank' => 1,
            'name' => $player->name,
            'surname' => $player->surname,
            'player_profile' => $player->id,
            'pay_status' => 0,
        ]);
        TeamPlayer::create([
            'team_id' => $team->id,
            'rank' => 1,
            'player_id' => $player->id,
            'pay_status' => 1,
        ]);
        $order = new TeamPaymentOrder([
            'team_id' => $team->id,
            'player_id' => $player->id,
            'event_id' => $event->id,
            'pay_status' => 1,
        ]);
        $viewData = [
            'team' => $team->fresh(),
            'event' => $event,
            'canWithdraw' => true,
        ];

        $payerHtml = view('frontend.event.partials.no-profile-team', $viewData + [
            'myPaidTeamOrdersByPlayer' => collect([$order])->keyBy(
                fn (TeamPaymentOrder $item) => $item->team_id.'-'.$item->player_id
            ),
        ])->render();
        $nonPayerHtml = view('frontend.event.partials.no-profile-team', $viewData + [
            'myPaidTeamOrdersByPlayer' => collect(),
        ])->render();

        $this->assertStringContainsString('Registered', $payerHtml);
        $this->assertStringContainsString('>Withdraw', $payerHtml);
        $this->assertStringContainsString(
            route('team.player.withdraw', [$team->id, $player->id, $event->id]),
            $payerHtml
        );
        $this->assertStringNotContainsString('>Withdraw', $nonPayerHtml);
    }

    public function test_paid_normal_roster_row_shows_withdraw_only_for_matching_payer_order(): void
    {
        $event = Event::factory()->create(['start_date' => now()->addMonth()]);
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create([
            'category_event_id' => $categoryEvent->id,
            'published' => true,
            'noProfile' => false,
        ]);
        $player = Player::factory()->create();
        TeamPlayer::create([
            'team_id' => $team->id,
            'rank' => 1,
            'player_id' => $player->id,
            'pay_status' => 1,
        ]);
        $order = new TeamPaymentOrder([
            'team_id' => $team->id,
            'player_id' => $player->id,
            'event_id' => $event->id,
            'pay_status' => 1,
        ]);
        $viewData = [
            'team' => $team->fresh(),
            'event' => $event,
            'region' => new \App\Models\TeamRegion(['clothing_order' => 0]),
            'canWithdraw' => true,
        ];

        $payerHtml = view('frontend.event.partials.profile-team', $viewData + [
            'myPaidTeamOrdersByPlayer' => collect([$order])->keyBy(
                fn (TeamPaymentOrder $item) => $item->team_id.'-'.$item->player_id
            ),
        ])->render();
        $nonPayerHtml = view('frontend.event.partials.profile-team', $viewData + [
            'myPaidTeamOrdersByPlayer' => collect(),
        ])->render();

        $this->assertStringContainsString('>Withdraw', $payerHtml);
        $this->assertStringContainsString(
            route('team.player.withdraw', [$team->id, $player->id, $event->id]),
            $payerHtml
        );
        $this->assertStringNotContainsString('>Withdraw', $nonPayerHtml);
    }

}
