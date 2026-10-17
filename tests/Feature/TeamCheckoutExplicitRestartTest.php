<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Models\{CategoryEvent, Event, EventType, Player, Team, TeamPaymentOrder, TeamPlayer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeamCheckoutExplicitRestartTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_payer_reopens_recent_and_old_checkout_without_popup_cancellation_or_repricing(): void
    {
        [$order] = $this->scenario();
        $payer = $order->user;
        $order->event->update(['entryFee' => 999]);
        foreach ([now(), now()->subMinutes(6), now()->subHour()] as $handoff) {
            $order->update(['payfast_handed_off_at' => $handoff]);
            $before = $order->fresh()->getAttributes();
            $this->actingAs($payer)->get(route('team.payment.payfast', [$order->team_id, $order->player_id, $order->event_id]))
                ->assertOk()
                ->assertViewIs('frontend.payfast.team_payment')
                ->assertViewHas('total', 490.0)
                ->assertViewHas('walletReserved', 40.0)
                ->assertViewHas('payfastDue', 450.0)
                ->assertDontSee('R999.00')
                ->assertSessionMissing('team_payment_recovery');
            $this->post(route('team.payment.payfast.handoff', [$order->team_id, $order->player_id, $order->event_id]), ['custom_int5' => $order->id])
                ->assertOk()->assertViewHas('payfastDue', 450.0)->assertViewHas('autoSubmitPayfast', true);
            $this->assertSame($before, $order->fresh()->getAttributes());
            $this->assertDatabaseCount('team_payment_orders', 1);
            $this->assertDatabaseCount('wallet_transactions', 0);
        }
    }

    public function test_same_payer_signed_restart_resumes_exact_existing_order_without_cancellation(): void
    {
        [$order] = $this->scenario();
        $before = $order->fresh()->getAttributes();
        $this->actingAs($order->user)->post($this->restartUrl($order, $order->user), ['confirm_restart' => 1])
            ->assertRedirect(route('team.checkout', $order));
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_confirmed_restart_is_immediate_and_repeated_confirmation_cannot_cancel_successor(): void
    {
        [$order, $actor] = $this->scenario();
        $service = app(TeamPaymentService::class);
        $context = $service->pendingRestartContext($actor, $order->team, $order->player, $order->event);
        $replacement = $service->restartPendingCheckout($actor, $order->team, $order->player, $order->event, $context['order'], $context['state']);
        $this->assertSame((int) $actor->id, (int) $replacement->user_id);
        $this->assertSame(490.0, $replacement->total_amount);
        $this->assertSame(490.0, $replacement->payfast_amount_due);
        $this->assertSame(0.0, $replacement->wallet_reserved);
        $this->assertNull($replacement->payfast_handed_off_at);
        $this->assertNotNull($order->fresh()->withdrawn_at);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        try {
            $service->restartPendingCheckout($actor, $order->team, $order->player, $order->event, $context['order'], $context['state']);
            $this->fail('A repeated confirmation restarted the successor.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('changed', $exception->errors()['payment'][0]);
        }
        $this->assertNull($replacement->fresh()->withdrawn_at);
        $this->assertDatabaseCount('team_payment_orders', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_changed_handoff_snapshot_cannot_be_cancelled(): void
    {
        [$order, $actor] = $this->scenario();
        $service = app(TeamPaymentService::class);
        $context = $service->pendingRestartContext($actor, $order->team, $order->player, $order->event);
        $order->update(['wallet_reserved' => 20, 'payfast_amount_due' => 470]);
        try {
            $service->restartPendingCheckout($actor, $order->team, $order->player, $order->event, $context['order'], $context['state']);
            $this->fail('A stale confirmation cancelled a changed handoff.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('changed', $exception->errors()['payment'][0]);
        }
        $this->assertNull($order->fresh()->withdrawn_at);
        $this->assertDatabaseCount('team_payment_orders', 1);
    }

    public function test_paid_wallet_debited_and_receipted_attempts_are_protected(): void
    {
        foreach ([['pay_status' => true], ['payfast_paid' => true], ['wallet_debited' => true], ['receipt' => true]] as $state) {
            [$order, $actor] = $this->scenario();
            $service = app(TeamPaymentService::class);
            $context = $service->pendingRestartContext($actor, $order->team, $order->player, $order->event);
            if (isset($state['receipt'])) {
                DB::table('transactions_pf')->insert(['custom_str5' => 'TeamOrder', 'custom_int5' => $order->id, 'pf_payment_id' => 'RESTART-BLOCK-'.$order->id, 'amount_gross' => 450]);
            } else {
                $order->update($state);
            }
            try {
                $service->restartPendingCheckout($actor, $order->team, $order->player, $order->event, $context['order'], $context['state']);
                $this->fail('Settlement evidence was ignored.');
            } catch (ValidationException) {
                $this->assertNull($order->fresh()->withdrawn_at);
            }
        }
        $this->assertDatabaseCount('team_payment_orders', 4);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_event_isolation_closed_registration_and_trials_are_protected(): void
    {
        [$order, $actor] = $this->scenario();
        $service = app(TeamPaymentService::class);
        $context = $service->pendingRestartContext($actor, $order->team, $order->player, $order->event);
        try {
            $service->restartPendingCheckout($actor, $order->team, $order->player, Event::factory()->create(), $context['order'], $context['state']);
            $this->fail('A cross-event restart was allowed.');
        } catch (ValidationException) {
            $this->assertNull($order->fresh()->withdrawn_at);
        }
        $order->event->update(['signUp' => false]);
        try {
            $service->restartPendingCheckout($actor, $order->team, $order->player, $order->event, $context['order'], $context['state']);
            $this->fail('Closed registration was ignored.');
        } catch (ValidationException) {
            $this->assertNull($order->fresh()->withdrawn_at);
        }
        $type = EventType::query()->forceCreate(['name' => 'Interprovincial Trials', 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $order->event->update(['signUp' => true, 'eventType' => $type->id]);
        $this->expectException(ValidationException::class);
        $service->restartPendingCheckout($actor, $order->team, $order->player, $order->event, $context['order'], $context['state']);
    }

    public function test_signed_confirmation_is_actor_bound_and_requires_explicit_acceptance(): void
    {
        [$order, $actor] = $this->scenario();
        $url = $this->restartUrl($order, $actor);
        $this->actingAs(User::factory()->create())->post($url, ['confirm_restart' => 1])->assertForbidden();
        $this->actingAs($actor)->post($url)->assertSessionHasErrors('confirm_restart');
        $this->post($url.'&expected_order=9999', ['confirm_restart' => 1])->assertForbidden();
        $this->travel(11)->minutes();
        $this->post($url, ['confirm_restart' => 1])->assertForbidden();
        $this->travelBack();
        $this->get($url)->assertStatus(405);
        $this->assertNull($order->fresh()->withdrawn_at);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->post($url, ['confirm_restart' => 1])->assertRedirect();
        $this->assertDatabaseCount('team_payment_orders', 2);
    }

    public function test_old_checkout_and_old_handoff_form_cannot_take_back_replacement(): void
    {
        [$order, $actor] = $this->scenario();
        $oldPayer = $order->user;
        $this->actingAs($actor)->post($this->restartUrl($order, $actor), ['confirm_restart' => 1])->assertRedirect();
        $replacement = TeamPaymentOrder::latest('id')->firstOrFail();
        $this->actingAs($oldPayer)->get(route('team.checkout', $order))->assertRedirect(route('events.show', $order->event));
        $this->post(route('team.payment.payfast.handoff', [$order->team_id, $order->player_id, $order->event_id]))
            ->assertSessionHasErrors('custom_int5');
        $this->assertDatabaseCount('team_payment_orders', 2);
        $this->post(route('team.payment.payfast.handoff', [$order->team_id, $order->player_id, $order->event_id]), ['custom_int5' => $order->id])
            ->assertRedirect(route('events.show', $order->event));
        $this->assertSame((int) $actor->id, (int) $replacement->fresh()->user_id);
        $this->assertNull($replacement->fresh()->withdrawn_at);
        $this->assertNull($replacement->fresh()->payfast_handed_off_at);
        $this->assertDatabaseCount('team_payment_orders', 2);
    }

    public function test_late_receipts_keep_original_payer_and_exact_amount_without_entry_or_wallet_debit(): void
    {
        [$order, $actor] = $this->scenario();
        $service = app(TeamPaymentService::class);
        $context = $service->pendingRestartContext($actor, $order->team, $order->player, $order->event);
        $replacement = $service->restartPendingCheckout($actor, $order->team, $order->player, $order->event, $context['order'], $context['state']);
        $receipt = ['payment_status' => 'COMPLETE', 'pf_payment_id' => 'EXPLICIT-LATE', 'amount_gross' => '450.00', 'custom_str5' => 'TeamOrder', 'custom_int5' => $order->id, 'custom_int4' => $order->user_id, 'custom_int3' => $order->event_id, 'custom_int2' => $order->player_id];
        $this->assertTrue($service->recordSupersededPayfastReceipt($order, $receipt));
        $this->assertTrue($service->recordSupersededPayfastReceipt($order, $receipt));
        foreach ([['amount_gross' => '490.00'], ['custom_int4' => $actor->id]] as $mismatch) {
            try {
                $service->recordSupersededPayfastReceipt($order, array_replace($receipt, $mismatch));
                $this->fail('A mismatched late receipt was accepted.');
            } catch (\RuntimeException) {
                $this->assertDatabaseCount('transactions_pf', 1);
            }
        }
        $this->assertDatabaseHas('transactions_pf', ['custom_int5' => $order->id, 'custom_int4' => $order->user_id, 'amount_gross' => 450]);
        $this->assertFalse($replacement->fresh()->pay_status);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertSame('reconciliation_required', $order->fresh()->collection_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('team_players', 1);
        $this->assertDatabaseHas('team_players', ['player_id' => $order->player_id, 'pay_status' => 0]);
    }

    private function restartUrl(TeamPaymentOrder $order, User $actor): string
    {
        $context = app(TeamPaymentService::class)->pendingRestartContext($actor, $order->team, $order->player, $order->event);

        return URL::temporarySignedRoute('team.payment.restart', now()->addMinutes(10), ['team' => $order->team_id, 'player' => $order->player_id, 'event' => $order->event_id, 'expected_order' => $context['order'], 'expected_state' => $context['state'], 'actor' => $actor->id]);
    }

    private function scenario(): array
    {
        $event = Event::factory()->create(['entryFee' => 490, 'published' => true, 'signUp' => true, 'status' => 'active', 'start_date' => now()->addMonth(), 'deadline' => 2]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => false, 'published' => true]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        $order = TeamPaymentOrder::create(['user_id' => User::factory()->create()->id, 'team_id' => $team->id, 'player_id' => $player->id, 'event_id' => $event->id, 'total_amount' => 490, 'wallet_reserved' => 40, 'payfast_amount_due' => 450, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false, 'payfast_handed_off_at' => now()]);

        return [$order, User::factory()->create()];
    }
}
