<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Models\{CategoryEvent, Event, Player, Team, TeamPaymentOrder, TeamPlayer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeamCheckoutAutomaticRestartTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_restarts_old_handoff_for_new_payer_and_preserves_other_events(): void
    {
        [$order, $event, $team, $player] = $this->scenario();
        $otherEvent = Event::factory()->create();
        $other = TeamPaymentOrder::create(array_replace($order->getAttributes(), ['id' => null, 'event_id' => $otherEvent->id]));
        $payer = User::factory()->create();
        $replacement = app(TeamPaymentService::class)->ensureOrder($payer, $team, $player, $event, 200);
        $this->assertNotSame($order->id, $replacement->id);
        $this->assertSame($payer->id, (int) $replacement->user_id);
        $this->assertSame(200.0, $replacement->payfast_amount_due);
        $this->assertNotNull($order->fresh()->withdrawn_at);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        $this->assertSame(40.0, $other->fresh()->wallet_reserved);
        $this->assertNotNull($other->fresh()->payfast_handed_off_at);
        $this->assertDatabaseCount('team_payment_orders', 3);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('user_players', 0);
        $this->assertSame($replacement->id, app(TeamPaymentService::class)->ensureOrder($payer, $team, $player, $event, 200)->id);
    }

    public function test_same_payer_continues_recent_and_old_handoff_and_paid_order_is_protected(): void
    {
        [$order, $event, $team, $player] = $this->scenario();
        $service = app(TeamPaymentService::class);
        foreach ([now(), now()->subMinutes(6), now()->subHour()] as $handoff) {
            $order->update(['payfast_handed_off_at' => $handoff]);
            $before = $order->fresh()->getAttributes();
            $continued = $service->ensureOrder($order->user, $team, $player, $event, 999);
            $this->assertSame($order->id, $continued->id);
            $this->assertSame($before, $order->fresh()->getAttributes());
        }
        $order->update(['pay_status' => true, 'payfast_paid' => true]);
        $this->assertSame($order->id, $service->ensureOrder(User::factory()->create(), $team, $player, $event, 200)->id);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_at_exactly_five_minutes_and_recent_new_payer_checkout_is_still_protected(): void
    {
        $this->freezeTime();
        [$order, $event, $team, $player] = $this->scenario();
        $service = app(TeamPaymentService::class);
        $newPayer = User::factory()->create();
        foreach ([now()->subMinutes(5), now()->subMinutes(4)] as $handoff) {
            $order->update(['payfast_handed_off_at' => $handoff]);
            try {
                $service->ensureOrder($newPayer, $team, $player, $event, 200);
                $this->fail('The protected handoff was restarted.');
            } catch (ValidationException $exception) {
                $this->assertSame('Someone is busy processing a payment. Please wait five minutes before trying again.', $exception->errors()['payment'][0]);
            }
        }
        $this->assertDatabaseCount('team_payment_orders', 1);
    }

    public function test_provider_receipt_is_immutable_and_cannot_be_rebound_to_registration_checkout(): void
    {
        [$order, $event, $team, $player] = $this->scenario();
        $service = app(TeamPaymentService::class);
        $service->ensureOrder(User::factory()->create(), $team, $player, $event, 200);
        $data = $this->receipt($order);
        $service->recordSupersededPayfastReceipt($order, $data);
        $recorder = app(\App\Domain\Payments\Services\PaymentTransactionService::class);
        $receipt = \App\Models\Transaction::where('pf_payment_id', 'PF-LATE')->firstOrFail();
        $this->assertSame($receipt->id, $recorder->record($data + ['item_name' => 'Changed label'], $order)->id);
        $this->assertSame($receipt->item_name, $receipt->fresh()->item_name);
        foreach ([['custom_str5' => 'RegistrationOrder'], ['custom_int5' => $order->id + 1],
            ['custom_int4' => $order->user_id + 1], ['custom_int3' => $event->id + 1],
            ['custom_int2' => $player->id + 1], ['amount_gross' => '200.00']] as $override) {
            try {
                $recorder->record(array_replace($data, $override), $order);
                $this->fail('The receipt was changed.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('PayFast receipt', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertSame($receipt->getAttributes(), $receipt->fresh()->getAttributes());
    }

    public function test_a_receipt_with_stale_unpaid_flags_blocks_automatic_restart(): void
    {
        [$order, $event, $team, $player] = $this->scenario();
        app(\App\Domain\Payments\Services\PaymentTransactionService::class)->record($this->receipt($order), $order);
        try {
            app(TeamPaymentService::class)->ensureOrder(User::factory()->create(), $team, $player, $event, 200);
            $this->fail('An order with an existing provider receipt was restarted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('payment evidence', $exception->errors()['payment'][0]);
        }
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertSame(40.0, $order->fresh()->wallet_reserved);
        $this->assertNotNull($order->fresh()->payfast_handed_off_at);
    }

    public function test_signed_late_callback_is_recorded_once_and_invalid_signature_cannot_record_receipt(): void
    {
        [$order, $event, $team, $player] = $this->scenario();
        app(TeamPaymentService::class)->ensureOrder(User::factory()->create(), $team, $player, $event, 200);
        config()->set('services.payfast.sandbox', false);
        config()->set('services.payfast.merchant_id', 'test-merchant');
        config()->set('services.payfast.passphrase_live', 'test-passphrase');
        $payload = ['merchant_id' => 'test-merchant'] + $this->receipt($order);
        $raw = http_build_query($payload);
        $this->call('POST', route('notify.team'), $payload + ['signature' => 'invalid'], [], [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], $raw.'&signature=invalid')->assertStatus(400);
        $payload['signature'] = md5($raw.'&passphrase='.urlencode('test-passphrase'));
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->call('POST', route('notify.team'), $payload, [], [],
                ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($payload))->assertOk();
        }
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseHas('team_players', ['team_id' => $team->id, 'player_id' => $player->id, 'pay_status' => 0]);
    }

    public function test_late_receipt_keeps_original_payer_exact_amount_and_never_debits_released_wallet_or_marks_roster(): void
    {
        [$order, $event, $team, $player] = $this->scenario();
        $service = app(TeamPaymentService::class);
        $replacement = $service->ensureOrder(User::factory()->create(), $team, $player, $event, 200);
        $data = $this->receipt($order);
        $this->assertTrue($service->recordSupersededPayfastReceipt($order, $data));
        $this->assertTrue($service->recordSupersededPayfastReceipt($order, $data));
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertFalse($replacement->fresh()->pay_status);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertSame('reconciliation_required', $order->fresh()->collection_status);
        $this->assertSame(0.0, $order->fresh()->maxRefundableAmount());
        $this->assertDatabaseHas('transactions_pf', ['pf_payment_id' => 'PF-LATE', 'custom_int4' => $order->user_id,
            'custom_int5' => $order->id, 'amount_gross' => 160, 'cape_tennis_fee' => 0]);
        $this->assertDatabaseHas('team_players', ['team_id' => $team->id, 'player_id' => $player->id, 'pay_status' => 0]);
        // An unrelated registration order with the same numeric ID must not
        // supply entry counts or wallet funds to this archived team receipt.
        \App\Models\RegistrationOrder::create(['id' => $order->id, 'user_id' => $replacement->user_id,
            'total_fee' => 900, 'wallet_reserved' => 700, 'payfast_amount_due' => 200]);
        $map = new \ReflectionMethod(\App\Domain\Finance\Services\FinancialLedgerService::class, 'mapPayfastRow');
        $row = $map->invoke(app(\App\Domain\Finance\Services\FinancialLedgerService::class),
            \App\Models\Transaction::where('pf_payment_id', 'PF-LATE')->firstOrFail(), 10.0);
        $this->assertInstanceOf(TeamPaymentOrder::class, $row->order);
        $this->assertSame(0, $row->entryCount);
        $this->assertSame(0.0, $row->walletUsed);
        $this->assertSame(0.0, $row->capeFee);
        $this->assertSame(160.0, $row->gross);
        $this->assertSame('Late payment — reconciliation required', $row->status_label);
        // A second genuine provider charge is also retained for reconciliation.
        $service->recordSupersededPayfastReceipt($order, array_replace($data, ['pf_payment_id' => 'PF-LATE-SECOND']));
        $this->assertDatabaseCount('transactions_pf', 2);
        \Illuminate\Support\Facades\Event::fake([\App\Events\PaymentCompleted::class]);
        $service->finalizePayment($replacement, ['payfast_amount_received' => 200, 'payfast_amount_due' => 200,
            'pf_payment_id' => 'PF-REPLACEMENT', 'payment_method' => 'payfast']);
        app(\App\Domain\Payments\Services\PaymentTransactionService::class)->record(
            array_replace($this->receipt($replacement), ['pf_payment_id' => 'PF-REPLACEMENT', 'amount_gross' => '200.00']), $replacement);
        $this->assertTrue($replacement->fresh()->pay_status);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 3);
        $this->assertDatabaseCount('team_players', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_wrong_amount_identity_and_provider_receipt_collision_are_rejected(): void
    {
        [$order, $event, $team, $player] = $this->scenario();
        $service = app(TeamPaymentService::class);
        $replacement = $service->ensureOrder(User::factory()->create(), $team, $player, $event, 200);
        foreach ([['amount_gross' => '200.00'], ['custom_int4' => $replacement->user_id], ['custom_int3' => $event->id + 1]] as $override) {
            try {
                $service->recordSupersededPayfastReceipt($order, array_replace($this->receipt($order), $override));
                $this->fail('A mismatched provider receipt was accepted.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('original handoff', $exception->getMessage());
            }
        }
        DB::table('transactions_pf')->insert(['pf_payment_id' => 'PF-LATE', 'custom_str5' => 'TeamOrder',
            'custom_int5' => $replacement->id, 'amount_gross' => 160]);
        $this->expectException(\RuntimeException::class);
        $service->recordSupersededPayfastReceipt($order, $this->receipt($order));
    }

    private function receipt(TeamPaymentOrder $order): array
    {
        return ['payment_status' => 'COMPLETE', 'pf_payment_id' => 'PF-LATE', 'amount_gross' => '160.00',
            'custom_str5' => 'TeamOrder', 'custom_int5' => $order->id, 'custom_int4' => $order->user_id,
            'custom_int3' => $order->event_id, 'custom_int2' => $order->player_id];
    }

    private function scenario(): array
    {
        $event = Event::factory()->create();
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => false]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        $order = TeamPaymentOrder::create(['user_id' => User::factory()->create()->id, 'team_id' => $team->id,
            'player_id' => $player->id, 'event_id' => $event->id, 'total_amount' => 200,
            'wallet_reserved' => 40, 'payfast_amount_due' => 160, 'pay_status' => false, 'payfast_paid' => false,
            'wallet_debited' => false, 'payfast_handed_off_at' => now()->subMinutes(6)]);
        return [$order, $event, $team, $player];
    }
}
