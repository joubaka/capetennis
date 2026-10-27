<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\CategoryAdjustmentService;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Domain\Payments\Services\{LedgerService, PaymentTransactionService};
use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\{CategoryAdjustment, CategoryEvent, CategoryEventRegistration, Draw, Event, EventType, Player, Registration, RegistrationOrder, RegistrationOrderItems, SiteSetting, Transaction, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        SiteSetting::set('player_email_on_move', '0');
        SiteSetting::set('registration_open', '1');
    }

    private function fixture(float $paid = 500, float $targetFee = 700, string $method = 'payfast'): array
    {
        $payer = User::factory()->create();
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Category adjustment test', 'type' => EventType::INDIVIDUAL]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => true, 'signUp' => true, 'start_date' => now()->addMonth(), 'deadline' => 1]);
        $source = CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => $paid]);
        $target = CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => $targetFee]);
        $player = Player::factory()->create();
        $registration = Registration::create([]);
        $registration->players()->sync([$player->id]);
        $walletAmount = $method === 'wallet' ? $paid : ($method === 'hybrid' ? round($paid / 2, 2) : 0);
        $payfastAmount = $paid - $walletAmount;
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => $paid,
            'pay_status' => true, 'payfast_paid' => $payfastAmount > 0, 'payfast_amount_due' => $payfastAmount,
            'wallet_reserved' => $walletAmount, 'wallet_debited' => $walletAmount > 0,
            'payment_method' => $method, 'payfast_pf_payment_id' => $payfastAmount > 0 ? 'original-receipt' : null]);
        $item = new RegistrationOrderItems;
        $item->forceFill(['order_id' => $order->id, 'registration_id' => $registration->id, 'player_id' => $player->id,
            'category_event_id' => $source->id, 'user_id' => $payer->id, 'item_price' => $paid])->save();
        $walletTx = null;
        if ($walletAmount > 0) {
            $wallet = $payer->wallet ?? $payer->wallet()->create([]);
            app(LedgerService::class)->appendWalletCredit($wallet, $walletAmount, 'test_funding', $order->id);
            $walletTx = app(LedgerService::class)->appendWalletDebit($wallet, $walletAmount, 'event_registration_wallet_payment', $order->id);
            $order->update(['wallet_transaction_id' => $walletTx->id]);
        }
        $entry = CategoryEventRegistration::factory()->create(['category_event_id' => $source->id, 'registration_id' => $registration->id,
            'user_id' => $payer->id, 'payment_status_id' => 1, 'payment_method' => $method,
            'pf_transaction_id' => $payfastAmount > 0 ? 'original-receipt' : null, 'wallet_transaction_id' => $walletTx?->id]);
        if ($payfastAmount > 0) {
            app(PaymentTransactionService::class)->record(['pf_payment_id' => 'original-receipt', 'amount_gross' => $payfastAmount,
                'custom_int1' => $source->id, 'custom_int2' => $player->id, 'custom_int3' => $event->id,
                'custom_int4' => $payer->id, 'custom_int5' => $order->id, 'cape_tennis_fee' => 0], $order);
        }
        $service = app(CategoryAdjustmentService::class);
        return compact('payer', 'event', 'source', 'target', 'player', 'registration', 'order', 'item', 'entry', 'service');
    }

    private function start(array $f): CategoryAdjustment
    {
        $quote = $f['service']->quote($f['entry']->fresh(), $f['target']->fresh(), $f['payer']);
        return $f['service']->start($f['entry']->fresh(), $f['target']->fresh(), $f['payer'], $quote['quote_token'], $quote['request_token']);
    }

    private function itn(CategoryAdjustment $adjustment, string $receipt = 'upgrade-receipt'): array
    {
        return ['payment_status' => 'COMPLETE', 'pf_payment_id' => $receipt, 'amount_gross' => $adjustment->difference,
            'custom_str5' => 'CategoryAdjustment', 'custom_int1' => $adjustment->target_category_id,
            'custom_int2' => $adjustment->player_id, 'custom_int3' => $adjustment->event_id,
            'custom_int4' => $adjustment->user_id, 'custom_int5' => $adjustment->id];
    }

    public function test_upgrade_stays_in_original_category_until_verified_payment_and_replays_once(): void
    {
        $f = $this->fixture();
        $originalOrder = $f['order']->fresh()->getRawOriginal();
        $originalItem = $f['item']->fresh()->getRawOriginal();
        $adjustment = $this->start($f);
        $this->assertSame('200.00', $adjustment->difference);
        $this->assertEquals($f['source']->id, $f['entry']->fresh()->category_event_id);
        $f['service']->handoff($adjustment, $f['payer']);
        $f['service']->receive($this->itn($adjustment));
        $f['service']->receive($this->itn($adjustment));
        $this->assertEquals($f['target']->id, $f['entry']->fresh()->category_event_id);
        $this->assertEquals(700, $f['entry']->fresh()->maxRefundableAmount());
        $this->assertNull($f['entry']->fresh()->paymentInfo()['pf_payment_id']);
        $this->assertSame($originalOrder, $f['order']->fresh()->getRawOriginal());
        $this->assertSame($originalItem, $f['item']->fresh()->getRawOriginal());
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseCount('transactions_pf', 2);
        $this->assertDatabaseCount('category_adjustments', 1);
    }

    public function test_downgrade_credits_wallet_once_without_withdrawing_or_marking_refunded(): void
    {
        $f = $this->fixture(500, 300);
        $quote = $f['service']->quote($f['entry'], $f['target'], $f['payer']);
        $first = $f['service']->start($f['entry'], $f['target'], $f['payer'], $quote['quote_token'], $quote['request_token']);
        $second = $f['service']->start($f['entry']->fresh(), $f['target'], $f['payer'], $quote['quote_token'], $quote['request_token']);
        $this->assertSame($first->id, $second->id);
        $this->assertEquals(200, $f['payer']->fresh()->wallet->balance);
        $this->assertSame('active', $f['entry']->fresh()->status);
        $this->assertSame('not_refunded', $f['entry']->fresh()->refund_status);
        $this->assertEquals(300, $f['entry']->fresh()->maxRefundableAmount());
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_downgrade_then_upgrade_requires_money_previously_credited_back(): void
    {
        $f = $this->fixture(500, 300);
        $this->start($f);
        $f['target'] = $f['source'];
        $upgrade = $this->start($f);
        $this->assertSame('200.00', $upgrade->difference);
        $this->assertEquals(300, $f['entry']->fresh()->maxRefundableAmount());
        $f['service']->handoff($upgrade, $f['payer']);
        $f['service']->receive($this->itn($upgrade));
        $this->assertEquals(500, $f['entry']->fresh()->maxRefundableAmount());
        $this->assertEquals(200, $f['payer']->fresh()->wallet->balance);
    }

    public function test_wallet_and_hybrid_originals_retain_exact_remaining_funding(): void
    {
        $f = $this->fixture(500, 300, 'hybrid');
        $this->start($f);
        $info = $f['entry']->fresh()->paymentInfo();
        $this->assertEquals(50, $info['gross']);
        $this->assertEquals(250, $info['wallet_paid']);
        $this->assertEquals(200, $f['payer']->fresh()->wallet->balance);
    }

    public function test_same_price_move_has_no_payment_or_wallet_transaction(): void
    {
        $f = $this->fixture(500, 500, 'wallet');
        $this->start($f);
        $this->assertEquals($f['target']->id, $f['entry']->fresh()->category_event_id);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_wrong_amount_identity_and_missing_handoff_are_rejected_without_mutation(): void
    {
        $f = $this->fixture();
        $adjustment = $this->start($f);
        foreach ([['amount_gross' => '199.99'], ['custom_int4' => User::factory()->create()->id], []] as $tampered) {
            try { $f['service']->receive(array_merge($this->itn($adjustment), $tampered)); $this->fail('Invalid ITN accepted.'); }
            catch (ValidationException) {}
        }
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertEquals($f['source']->id, $f['entry']->fresh()->category_event_id);
    }

    public function test_cancelled_or_expired_verified_payments_are_retained_for_reconciliation(): void
    {
        $f = $this->fixture();
        $adjustment = $this->start($f);
        $f['service']->handoff($adjustment, $f['payer']);
        $f['service']->cancel($adjustment, $f['payer']);
        $f['service']->receive($this->itn($adjustment));
        $this->assertSame('reconciliation_required', $adjustment->fresh()->status);
        $this->assertEquals($f['source']->id, $f['entry']->fresh()->category_event_id);
        $this->assertDatabaseCount('transactions_pf', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_deadline_is_entry_close_not_later_withdrawal_close_and_caps_checkout(): void
    {
        $f = $this->fixture();
        $close = $f['event']->registrationClosesAt();
        $this->travelTo($close->copy()->subSeconds(30));
        $adjustment = $this->start($f);
        $this->assertSame($close->timestamp, $adjustment->expires_at->timestamp);
        $f['service']->handoff($adjustment, $f['payer']);
        $this->travelTo($close->copy()->addSecond());
        $f['service']->receive($this->itn($adjustment));
        $this->assertSame('reconciliation_required', $adjustment->fresh()->status);
        $this->assertEquals($f['source']->id, $f['entry']->fresh()->category_event_id);
    }

    public function test_second_handoff_is_blocked_and_extra_real_receipt_is_reconciled(): void
    {
        $f = $this->fixture();
        $adjustment = $this->start($f);
        $f['service']->handoff($adjustment, $f['payer']);
        try { $f['service']->handoff($adjustment, $f['payer']); $this->fail('Second handoff accepted.'); }
        catch (ValidationException) {}
        $f['service']->receive($this->itn($adjustment));
        $f['service']->receive($this->itn($adjustment, 'second-real-receipt'));
        $this->assertDatabaseCount('transactions_pf', 3);
        $this->assertSame('upgrade-receipt', $adjustment->fresh()->payfast_pf_payment_id);
        $this->assertEquals(700, $f['entry']->fresh()->maxRefundableAmount());
        $ledger = app(FinancialLedgerService::class)->buildForEvent($f['event']);
        $this->assertCount(1, $ledger['paymentRows']->where('subtype', 'superseded_payfast_receipt'));
    }

    public function test_non_owner_cross_event_draw_and_duplicate_player_are_blocked(): void
    {
        $f = $this->fixture();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson(route('registrations.categoryQuote', $f['entry']->id), ['new_category_event_id' => $f['target']->id])->assertForbidden();
        $other = CategoryEvent::factory()->create();
        $this->actingAs($f['payer'])->postJson(route('registrations.categoryQuote', $f['entry']->id), ['new_category_event_id' => $other->id])->assertUnprocessable();
        Draw::factory()->create(['event_id' => $f['event']->id, 'category_event_id' => $f['source']->id]);
        $this->actingAs($f['payer'])->postJson(route('registrations.categoryQuote', $f['entry']->id), ['new_category_event_id' => $f['target']->id])->assertUnprocessable();
        $this->assertDatabaseCount('category_adjustments', 0);
    }

    public function test_stale_quote_is_rejected_and_client_amount_is_ignored(): void
    {
        $f = $this->fixture();
        $quote = $f['service']->quote($f['entry'], $f['target'], $f['payer']);
        $f['target']->update(['entry_fee' => 701]);
        $this->actingAs($f['payer'])->postJson(route('registrations.moveCategory', $f['entry']->id),
            ['new_category_event_id' => $f['target']->id, 'quote_token' => $quote['quote_token'], 'request_token' => $quote['request_token'], 'amount' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('category_adjustments', 0);
    }

    public function test_adjustment_income_and_wallet_credits_do_not_count_extra_entries(): void
    {
        $f = $this->fixture();
        $adjustment = $this->start($f);
        $f['service']->handoff($adjustment, $f['payer']);
        $f['service']->receive($this->itn($adjustment));
        $f['target'] = $f['source'];
        $this->start($f);
        $ledger = app(FinancialLedgerService::class)->buildForEvent($f['event']);
        $this->assertEquals(700, $ledger['totals']['gross_payments']);
        $this->assertEquals(200, $ledger['totals']['completed_refunds']);
        $this->assertEquals(1, $ledger['paymentRows']->sum('entryCount'));
        $this->assertEquals(0, $ledger['paymentRows']->where('subtype', 'category_adjustment')->sum('capeFee'));
    }

    public function test_withdrawal_after_downgrade_can_only_refund_remaining_funding(): void
    {
        $f = $this->fixture(500, 300);
        $this->start($f);
        $entry = $f['entry']->fresh();
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        try {
            app(RefundExecutionService::class)->executeWalletRefund($entry, $f['payer']->fresh()->wallet, 450,
                'event_registration_refund', $entry->id, [], ['refund_gross' => 500, 'refund_fee' => 50, 'refund_net' => 450]);
            $this->fail('Over-refund accepted.');
        } catch (ValidationException) {}
        app(RefundExecutionService::class)->executeWalletRefund($entry, $f['payer']->fresh()->wallet, 270,
            'event_registration_refund', $entry->id, [], ['refund_gross' => 300, 'refund_fee' => 30, 'refund_net' => 270]);
        $this->assertEquals(470, $f['payer']->fresh()->wallet->balance);
        $this->assertEquals(0, $entry->fresh()->maxRefundableAmount());
    }

    public function test_signed_callback_rejects_invalid_signature_before_recording(): void
    {
        $f = $this->fixture();
        $adjustment = $this->start($f);
        $f['service']->handoff($adjustment, $f['payer']);
        $this->post('/notify', $this->itn($adjustment) + ['signature' => 'invalid'])->assertStatus(400);
        $this->assertDatabaseCount('transactions_pf', 1);
    }

    public function test_ordinary_paired_entry_retains_membership(): void
    {
        $f = $this->fixture(500, 300);
        $partner = Player::factory()->create();
        $f['registration']->players()->attach($partner->id);
        $this->start($f);
        $this->assertEqualsCanonicalizing([$f['player']->id, $partner->id], $f['registration']->players()->pluck('players.id')->all());
        $this->assertEquals(200, $f['payer']->fresh()->wallet->balance);
    }

    private function sharedOrder(): array
    {
        $f = $this->fixture(100, 0);
        $category = CategoryEvent::factory()->create(['event_id' => $f['event']->id, 'entry_fee' => 300]);
        $player = Player::factory()->create();
        $registration = Registration::create([]);
        $registration->players()->attach($player->id);
        $item = new RegistrationOrderItems;
        $item->forceFill(['order_id' => $f['order']->id, 'registration_id' => $registration->id,
            'category_event_id' => $category->id, 'player_id' => $player->id, 'user_id' => $f['payer']->id, 'item_price' => 300])->save();
        $expensive = CategoryEventRegistration::factory()->create(['category_event_id' => $category->id,
            'registration_id' => $registration->id, 'user_id' => $f['payer']->id, 'payment_status_id' => 1,
            'payment_method' => 'payfast', 'pf_transaction_id' => 'original-receipt']);
        $f['order']->update(['total_fee' => 400, 'payfast_amount_due' => 400]);
        DB::table('transactions_pf')->where('pf_payment_id', 'original-receipt')->update(['amount_gross' => 400]);
        return $f + compact('expensive');
    }

    public function test_unequal_price_shared_order_cannot_refund_the_same_funding_twice(): void
    {
        $f = $this->sharedOrder();
        $cheap = $f['entry'];
        $f['entry'] = $f['expensive'];
        $this->start($f);
        $this->assertEquals(300, $f['payer']->fresh()->wallet->balance);
        $this->assertEquals(100, $cheap->fresh()->maxRefundableAmount());
        $cheap->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        try {
            app(RefundExecutionService::class)->executeWalletRefund($cheap, $f['payer']->fresh()->wallet, 180,
                'event_registration_refund', $cheap->id, [], ['refund_gross' => 200, 'refund_fee' => 20, 'refund_net' => 180]);
            $this->fail('Old equal split amount accepted.');
        } catch (ValidationException) {}
        app(RefundExecutionService::class)->executeWalletRefund($cheap, $f['payer']->fresh()->wallet, 90,
            'event_registration_refund', $cheap->id, [], ['refund_gross' => 100, 'refund_fee' => 10, 'refund_net' => 90]);
        $this->assertEquals(390, $f['payer']->fresh()->wallet->balance);
        $this->assertDatabaseCount('category_event_registrations', 2);
    }

    public function test_shared_order_with_prior_unreconciled_refund_cannot_begin_adjustment(): void
    {
        $f = $this->sharedOrder();
        $f['entry']->update(['status' => 'withdrawn', 'withdrawn_at' => now(), 'refund_status' => 'completed', 'refund_gross' => 200, 'refund_net' => 180]);
        $f['entry'] = $f['expensive'];
        $this->expectException(ValidationException::class);
        $this->start($f);
    }

    public function test_duplicate_player_different_registration_is_a_validation_error(): void
    {
        $f = $this->fixture();
        $another = Registration::create([]);
        $another->players()->attach($f['player']->id);
        CategoryEventRegistration::factory()->paid()->create(['registration_id' => $another->id, 'category_event_id' => $f['target']->id]);
        $this->actingAs($f['payer'])->postJson(route('registrations.categoryQuote', $f['entry']->id), ['new_category_event_id' => $f['target']->id])
            ->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->assertDatabaseCount('category_adjustments', 0);
    }

    public function test_valid_signed_http_itn_finalizes_only_after_signature_verification(): void
    {
        $f = $this->fixture();
        $adjustment = $this->start($f);
        $f['service']->handoff($adjustment, $f['payer']);
        config(['services.payfast.sandbox' => true, 'services.payfast.sandbox_id' => 'test-merchant', 'services.payfast.passphrase_sandbox' => 'test-passphrase']);
        $data = $this->itn($adjustment) + ['merchant_id' => 'test-merchant'];
        $data['signature'] = md5(http_build_query($data).'&passphrase=test-passphrase');
        $this->post('/notify', $data)->assertOk();
        $this->assertEquals($f['target']->id, $f['entry']->fresh()->category_event_id);
        $this->assertDatabaseCount('transactions_pf', 2);
    }

    public function test_price_tampering_does_not_reduce_the_payfast_difference(): void
    {
        $f = $this->fixture();
        $quote = $f['service']->quote($f['entry'], $f['target'], $f['payer']);
        $this->actingAs($f['payer'])->postJson(route('registrations.moveCategory', $f['entry']->id),
            ['new_category_event_id' => $f['target']->id, 'quote_token' => $quote['quote_token'], 'request_token' => $quote['request_token'], 'amount' => 1])->assertOk();
        $this->assertEquals(200, CategoryAdjustment::sole()->payfast_amount_due);
    }

    public function test_downgrade_rolls_back_wallet_credit_if_category_move_fails(): void
    {
        $f = $this->fixture(500, 300);
        $this->mock(\App\Domain\Entries\Services\EntryService::class)->shouldReceive('transferEntry')->once()->andThrow(new \RuntimeException('Simulated failed move'));
        $f['service'] = app(CategoryAdjustmentService::class);
        try { $this->start($f); $this->fail('Failure expected.'); }
        catch (\RuntimeException $exception) { $this->assertSame('Simulated failed move', $exception->getMessage()); }
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('category_adjustments', 0);
        $this->assertEquals($f['source']->id, $f['entry']->fresh()->category_event_id);
    }

    public function test_difference_below_payfast_minimum_leaves_entry_and_money_unchanged(): void
    {
        $f = $this->fixture(500, 504.99);
        $this->actingAs($f['payer'])->postJson(route('registrations.categoryQuote', $f['entry']),
            ['new_category_event_id' => $f['target']->id])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->assertDatabaseCount('category_adjustments', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertEquals($f['source']->id, $f['entry']->fresh()->category_event_id);
    }

    public function test_changed_player_membership_cannot_reuse_prior_category_funding(): void
    {
        $f = $this->fixture(500, 300);
        $this->start($f);
        $f['registration']->players()->sync([Player::factory()->create()->id]);
        $this->expectException(ValidationException::class);
        $f['entry']->fresh()->paymentInfo();
    }

    public function test_admin_origin_real_upgrade_is_refundable_to_its_payer_wallet_after_withdrawal(): void
    {
        $f = $this->fixture(0, 200, 'admin');
        $f['item']->delete();
        $f['order']->delete();
        $f['entry']->update(['admin_payment_status' => 'paid']);
        $adjustment = $this->start($f);
        $f['service']->handoff($adjustment, $f['payer']);
        $f['service']->receive($this->itn($adjustment));
        $entry = $f['entry']->fresh();
        $this->assertNotNull($f['payer']->fresh()->wallet);
        $this->assertEquals(200, $entry->maxRefundableAmount());
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $this->assertTrue($entry->fresh()->canRequestRefund());
        $this->actingAs($f['payer'])->post(route('registrations.refund.request', $entry), ['method' => 'wallet'])->assertRedirect();
        $this->assertSame('completed', $entry->fresh()->refund_status);
        $this->assertEquals(180, $f['payer']->fresh()->wallet->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_super_user_can_confirm_decrease_to_original_payer_wallet_only(): void
    {
        $f = $this->fixture(500, 300);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('super-user');
        $quote = $f['service']->quote($f['entry'], $f['target'], $admin, true);
        $f['service']->start($f['entry'], $f['target'], $admin, $quote['quote_token'], $quote['request_token'], true);
        $this->assertEquals(200, $f['payer']->fresh()->wallet->balance);
        $this->assertNull($admin->fresh()->wallet);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame($admin->id, CategoryAdjustment::sole()->actor_id);
    }
}
