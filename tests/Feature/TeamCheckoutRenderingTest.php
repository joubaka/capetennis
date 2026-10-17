<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, Player, Team, TeamPaymentOrder, TeamPlayer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCheckoutRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_renders_team_controls_and_stored_amounts_without_mutating_payment(): void
    {
        $order = $this->order();
        $original = $order->getAttributes();

        foreach ([1, 2] as $visit) {
            $this->actingAs($order->user)->get(route('team.checkout', $order))
                ->assertOk()
                ->assertViewIs('frontend.payfast.team_payment')
                ->assertViewHas('total', 490.0)
                ->assertViewHas('walletReserved', 40.0)
                ->assertViewHas('payfastDue', 450.0)
                ->assertViewHas('autoSubmitPayfast', false)
                ->assertSee('R490.00')
                ->assertSee('R450.00')
                ->assertSee(route('team.payment.payfast.handoff', [$order->team_id, $order->player_id, $order->event_id]), false)
                ->assertDontSee("document.getElementById('teamPayfastForm').submit();", false);
        }

        $this->assertSame($original, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_another_payer_cannot_view_the_order(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create())->get(route('team.checkout', $order))->assertForbidden();
        $this->assertDatabaseCount('team_payment_orders', 1);
    }

    public function test_checkout_rejects_a_team_from_another_event(): void
    {
        $order = $this->order();
        $order->update(['event_id' => Event::factory()->create()->id]);
        $this->actingAs($order->user)->get(route('team.checkout', $order))->assertNotFound();
    }

    public function test_wallet_only_checkout_uses_team_completion_controls(): void
    {
        $order = $this->order();
        $order->update(['wallet_reserved' => 490, 'payfast_amount_due' => 0]);
        $this->actingAs($order->user)->get(route('team.checkout', $order))
            ->assertOk()
            ->assertSee(route('team.hybrid.complete', $order), false)
            ->assertSee('Confirm Wallet Payment')
            ->assertDontSee('id="teamPayfastForm"', false);
        $this->assertFalse($order->fresh()->wallet_debited);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function order(): TeamPaymentOrder
    {
        $event = Event::factory()->create(['published' => true, 'signUp' => true, 'status' => 'active', 'start_date' => now()->addMonth(), 'deadline' => 2, 'entryFee' => 400]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => false, 'published' => true]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);

        return TeamPaymentOrder::create(['user_id' => User::factory()->create()->id, 'team_id' => $team->id, 'player_id' => $player->id, 'event_id' => $event->id, 'total_amount' => 490, 'wallet_reserved' => 40, 'payfast_amount_due' => 450, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);
    }
}
