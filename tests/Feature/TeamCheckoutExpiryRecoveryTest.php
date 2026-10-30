<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, Player, Team, TeamPaymentOrder, TeamPlayer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCheckoutExpiryRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_payment_submission_returns_to_event_and_fresh_retry_can_reach_payfast(): void
    {
        $this->freezeTime();
        $order = $this->scenario();
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $checkoutUrl = route('team.payment.payfast', [$order->team_id, $order->player_id, $order->event_id]);
        $handoffUrl = route('team.payment.payfast.handoff', [$order->team_id, $order->player_id, $order->event_id]);

        $this->actingAs($order->user)->from($checkoutUrl)->post($handoffUrl, ['custom_int5' => $order->id])
            ->assertRedirect(route('events.show', $order->event_id))
            ->assertSessionHasErrors('payment')
            ->assertSessionHas('info', 'Select the player on this event and start registration again to open a fresh checkout.');
        $this->assertNotNull($order->fresh()->withdrawn_at);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        $this->assertNull($order->fresh()->payfast_handed_off_at);

        $this->get($checkoutUrl)->assertOk()->assertViewHas('payfastDue', 490.0);
        $replacement = TeamPaymentOrder::latest('id')->firstOrFail();
        $this->assertNotSame($order->id, $replacement->id);
        $this->post($handoffUrl, ['custom_int5' => $order->id])->assertRedirect(route('events.show', $order->event_id));
        $this->assertNull($replacement->fresh()->payfast_handed_off_at);
        $this->post($handoffUrl, ['custom_int5' => $replacement->id, 'amount' => '0.01'])
            ->assertOk()->assertViewHas('payfastDue', 490.0)->assertViewHas('autoSubmitPayfast', true);
        $this->assertNotNull($replacement->fresh()->payfast_handed_off_at);
        $this->assertSame(490.0, $replacement->fresh()->payfast_amount_due);
        $this->assertDatabaseCount('team_payment_orders', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseHas('team_players', ['team_id' => $order->team_id, 'player_id' => $order->player_id, 'pay_status' => 0]);
    }

    public function test_foreign_payer_and_mismatched_route_cannot_expire_or_handoff_an_order(): void
    {
        $order = $this->scenario();
        $order->forceFill(['created_at' => now()->subMinutes(6)])->save();
        $before = $order->fresh()->getAttributes();
        $route = [$order->team_id, $order->player_id, $order->event_id];
        $this->actingAs(User::factory()->create())->post(route('team.payment.payfast.handoff', $route), ['custom_int5' => $order->id])->assertForbidden();
        foreach ([0 => Team::factory()->create()->id, 1 => Player::factory()->create()->id, 2 => Event::factory()->create()->id] as $index => $id) {
            $mismatched = $route;
            $mismatched[$index] = $id;
            $this->actingAs($order->user)->post(route('team.payment.payfast.handoff', $mismatched), ['custom_int5' => $order->id])->assertForbidden();
        }
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_payment_evidence_blocks_expiry_and_does_not_offer_a_fresh_checkout(): void
    {
        $order = $this->scenario();
        $order->forceFill(['created_at' => now()->subMinutes(6)])->save();
        \Illuminate\Support\Facades\DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'PF-RECOVERY-EVIDENCE', 'custom_str5' => 'TeamOrder',
            'custom_int5' => $order->id, 'amount_gross' => 490,
        ]);
        $before = $order->fresh()->getAttributes();
        $this->actingAs($order->user)->post(route('team.payment.payfast.handoff', [$order->team_id, $order->player_id, $order->event_id]), ['custom_int5' => $order->id])
            ->assertRedirect(route('events.show', $order->event_id))
            ->assertSessionHasErrors('payment')
            ->assertSessionHas('info', 'This checkout could not be restarted safely. Contact the tournament administrator. If you have already paid, do not pay again.');
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function scenario(): TeamPaymentOrder
    {
        $event = Event::factory()->create(['entryFee' => 490, 'published' => true, 'signUp' => true, 'status' => 'active', 'start_date' => now()->addMonth(), 'deadline' => 2]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => false, 'published' => true]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);

        return TeamPaymentOrder::create(['user_id' => User::factory()->create()->id, 'team_id' => $team->id, 'player_id' => $player->id, 'event_id' => $event->id, 'total_amount' => 490, 'wallet_reserved' => 0, 'payfast_amount_due' => 490, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);
    }
}
