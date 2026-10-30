<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Models\{CategoryEvent, Event, Player, Team, TeamPaymentOrder, TeamPlayer, TeamRegion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Log};
use Tests\TestCase;

class TeamCheckoutExpiryRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_only_expired_handoff_can_retry_without_changing_roster_or_debiting_wallet(): void
    {
        $this->freezeTime();
        $order = $this->scenario(true);
        $handoffAt = now()->subMinutes(6);
        $order->forceFill(['payfast_handed_off_at' => $handoffAt, 'wallet_reserved' => 40, 'payfast_amount_due' => 450])->save();
        $route = [$order->team_id, $order->player_id, $order->event_id];

        $newPayer = User::factory()->create();
        $this->actingAs($newPayer)->get(route('team.payment.payfast', $route))->assertOk();
        $replacement = TeamPaymentOrder::latest('id')->firstOrFail();
        $this->assertNotSame($order->id, $replacement->id);
        $this->assertSame($newPayer->id, $replacement->user_id);
        $this->assertSame(490.0, $replacement->total_amount);
        $this->assertSame(490.0, $replacement->payfast_amount_due);
        $this->assertNotNull($order->fresh()->withdrawn_at);
        $this->assertNull($order->fresh()->payfast_handed_off_at);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        $audit = \Spatie\Activitylog\Models\Activity::where('subject_id', $order->id)
            ->where('subject_type', $order->getMorphClass())
            ->where('description', 'Expired unresolved PayFast handoff retained for reconciliation')->sole();
        $this->assertSame($handoffAt->toIso8601String(), $audit->properties['handed_off_at']);
        $this->assertSame('450', $audit->properties['expected_provider_amount']);
        $this->assertSame($order->user_id, $audit->properties['payer_id']);
        $this->assertFalse(app(TeamPaymentService::class)->expireUnpaidCheckout($order));
        $this->post(route('team.payment.payfast.handoff', $route), ['custom_int5' => $order->id])->assertForbidden();
        $this->assertNull($replacement->fresh()->payfast_handed_off_at);
        $this->post(route('team.payment.payfast.handoff', $route), ['custom_int5' => $replacement->id, 'amount' => '0.01'])
            ->assertOk()->assertViewHas('payfastDue', 490.0)->assertViewHas('autoSubmitPayfast', true);
        $this->assertNotNull($replacement->fresh()->payfast_handed_off_at);
        $this->assertDatabaseCount('team_payment_orders', 2);
        $this->assertDatabaseCount('team_players', 1);
        $this->assertDatabaseHas('team_players', ['team_id' => $order->team_id, 'player_id' => $order->player_id, 'rank' => 1, 'pay_status' => 0]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('user_players', 0);

        // A delayed verified charge must remain with the original payer, even
        // after another payer opens the replacement checkout.
        $data = ['payment_status' => 'COMPLETE', 'pf_payment_id' => 'REGIONAL-EXPIRED-PF',
            'amount_gross' => '450.00', 'custom_str5' => 'TeamOrder', 'custom_int5' => $order->id,
            'custom_int4' => $order->user_id, 'custom_int3' => $order->event_id, 'custom_int2' => $order->player_id];
        $receipts = app(\App\Domain\Payments\Services\ExpiredPayfastHandoffService::class);
        $this->assertTrue($receipts->recordReceipt($order, $data));
        $this->assertTrue($receipts->recordReceipt($order, $data));
        $this->assertDatabaseHas('transactions_pf', ['custom_int5' => $order->id, 'custom_int4' => $order->user_id, 'amount_gross' => 450]);
        $this->assertSame('reconciliation_required', $order->fresh()->collection_status);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertFalse($replacement->fresh()->pay_status);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        $this->assertDatabaseHas('team_players', ['team_id' => $order->team_id, 'player_id' => $order->player_id, 'pay_status' => 0]);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('team_payment_orders', 2);
    }

    public function test_foreign_region_and_conflicting_category_cannot_release_an_expired_handoff_and_log_why(): void
    {
        $this->freezeTime();
        foreach (['foreign_region', 'conflicting_category'] as $mismatch) {
            $order = $this->scenario(true);
            $otherEvent = Event::factory()->create();
            if ($mismatch === 'foreign_region') {
                DB::table('event_regions')->where('region_id', $order->team->region_id)->update(['event_id' => $otherEvent->id]);
            } else {
                $order->team->update(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $otherEvent->id])->id]);
            }
            $order->forceFill(['payfast_handed_off_at' => now()->subMinutes(6)])->save();
            $before = $order->fresh()->getAttributes();
            Log::spy();
            $this->assertFalse(app(TeamPaymentService::class)->expireUnpaidCheckout($order));
            Log::shouldHaveReceived('warning')->with('Team checkout blocked', \Mockery::on(fn ($context) =>
                $context['reason'] === 'team_event_mismatch' && $context['operation'] === 'expiry'
                && $context['order_id'] === $order->id && $context['event_id'] === $order->event_id
                && $context['occurred_at'] === now()->toIso8601String()))->once();
            $this->assertSame($before, $order->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('team_payment_orders', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseMissing('activity_log', ['description' => 'Expired unresolved PayFast handoff retained for reconciliation']);
    }

    public function test_provider_evidence_is_preserved_and_logging_survives_failed_retry_transaction(): void
    {
        $this->freezeTime();
        $order = $this->scenario(true);
        $order->forceFill(['payfast_handed_off_at' => now()->subMinutes(6)])->save();
        DB::table('transactions_pf')->insert(['pf_payment_id' => 'PF-DO-NOT-LOG', 'custom_str5' => 'TeamOrder', 'custom_int5' => $order->id, 'amount_gross' => 490]);
        $before = $order->fresh()->getAttributes();
        Log::spy();
        try {
            app(TeamPaymentService::class)->ensureOrder(User::factory()->create(), $order->team, $order->player, $order->event, 490);
            $this->fail('A checkout with settlement evidence was replaced.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        Log::shouldHaveReceived('warning')->with('Team checkout blocked', \Mockery::on(fn ($context) =>
            $context['reason'] === 'provider_transaction' && $context['order_id'] === $order->id
            && $context['occurred_at'] === now()->toIso8601String()
            && !str_contains(json_encode($context), 'PF-DO-NOT-LOG')))->once();
        Log::shouldHaveReceived('warning')->with('Team checkout blocked', \Mockery::on(fn ($context) =>
            $context['operation'] === 'retry' && $context['reason'] === 'expired_handoff_not_safely_released'))->once();
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseMissing('activity_log', ['description' => 'Expired unresolved PayFast handoff retained for reconciliation']);
    }

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

    private function scenario(bool $regionOnly = false): TeamPaymentOrder
    {
        $event = Event::factory()->create(['entryFee' => 490, 'published' => true, 'signUp' => true, 'status' => 'active', 'start_date' => now()->addMonth(), 'deadline' => 2]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => false, 'published' => true]);
        if ($regionOnly) {
            $region = TeamRegion::create(['region_name' => 'Expiry test region', 'short_name' => 'ETR', 'region_fee' => 0]);
            $event->regions()->attach($region);
            $team->update(['category_event_id' => null, 'region_id' => $region->id]);
        }
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);

        return TeamPaymentOrder::create(['user_id' => User::factory()->create()->id, 'team_id' => $team->id, 'player_id' => $player->id, 'event_id' => $event->id, 'total_amount' => 490, 'wallet_reserved' => 0, 'payfast_amount_due' => 490, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);
    }
}
