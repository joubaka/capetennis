<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\LedgerService;
use App\Domain\Refunds\Services\{IndividualProviderRefundGuard, PendingBankWalletConversionService, RefundExecutionService};
use App\Models\{CategoryEventRegistration, Event, EventNomination, IndividualProviderRefundAttempt, Player, RegistrationOrder, RegistrationOrderItems, Transaction, User, WalletTransaction};
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\{DB, Http, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PendingBankWalletConversionTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        $this->assertTrue((DB::connection()->getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:')
            || (DB::connection()->getDriverName() === 'mysql' && DB::connection()->getDatabaseName() === 'ct_testing'));
        if (DB::connection()->getDriverName() === 'sqlite') {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$inMemoryConnections = [];
        }
    }

    protected function tearDown(): void
    {
        $inMemory = $this->app && $this->app->bound('db') && DB::connection()->getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:';
        try { parent::tearDown(); }
        finally {
            if ($inMemory) {
                \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
                \Illuminate\Foundation\Testing\RefreshDatabaseState::$inMemoryConnections = [];
            }
        }
    }

    public function test_review_then_conversion_credits_exact_original_payer_once_and_preserves_other_event_and_source_evidence(): void
    {
        [$actor, $payer, $entry, $order, $nomination, $other] = $this->scenario();
        $source = [$order->getAttributes(), $order->items()->sole()->getAttributes(), Transaction::where('pf_payment_id', 'PF-CONVERT')->sole()->getAttributes(),
            $nomination->getAttributes(), $other->getAttributes(), $entry->withdrawn_at->toIso8601String()];
        $allPayments = $this->immutablePaymentRows();
        $preview = $this->preview($actor, $entry);
        $this->assertSame(45000, $preview['amount_cents']);
        $this->assertSame($payer->id, $preview['payer_id']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertNull($payer->fresh()->wallet);
        $otherWallet = User::factory()->create()->wallet()->create();
        $url = route('admin.refunds.bank.wallet-conversion.complete', [$entry->categoryEvent->event_id, $entry]);
        $this->actingAs($actor)->post($url, ['preview_token' => $preview['token'], 'amount' => 9999, 'wallet_id' => $otherWallet->id])->assertRedirect();
        $this->post($url, ['preview_token' => $preview['token']])->assertRedirect();
        $this->assertDatabaseCount('wallet_transactions', 1);
        $credit = WalletTransaction::sole();
        $this->assertEquals(450, $credit->amount);
        $this->assertSame($payer->fresh()->wallet->id, $credit->wallet_id);
        $this->assertEquals(0, $otherWallet->balance);
        $this->assertSame('wallet', $entry->fresh()->refund_method);
        $this->assertSame('completed', $entry->fresh()->refund_status);
        $this->assertSame('withdrawn', $entry->fresh()->status);
        $this->assertEquals(0, $entry->fresh()->refund_fee);
        $this->assertSame($source, [$order->fresh()->getAttributes(), $order->items()->sole()->getAttributes(), Transaction::where('pf_payment_id', 'PF-CONVERT')->sole()->getAttributes(),
            $nomination->fresh()->getAttributes(), $other->fresh()->getAttributes(), $entry->fresh()->withdrawn_at->toIso8601String()]);
        $this->assertSame($allPayments, $this->immutablePaymentRows());
        $this->assertDatabaseCount('individual_provider_refund_attempts', 0);
        Http::assertNothingSent(); Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    public function test_preview_ui_identifies_payer_full_amount_and_closure_without_performing_refund(): void
    {
        [$actor, $payer, $entry] = $this->scenario();
        $this->actingAs($actor)->post(route('admin.refunds.bank.wallet-conversion.preview', [$entry->categoryEvent->event_id, $entry]), [
            'provider_id' => 'PF-CONVERT', 'reason' => 'Operator verified no refund in dashboard and no bank payout.', 'verified_not_refunded' => 1,
        ])->assertOk()->assertSee($payer->name)->assertSee('R450.00')->assertSee('no deduction')->assertSee('closes this pending bank refund');
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame('pending', $entry->fresh()->refund_status);
        Http::assertNothingSent();
    }

    public function test_role_event_reference_attestation_stale_and_expired_review_are_rejected(): void
    {
        [$actor, $payer, $entry] = $this->scenario();
        $service = app(PendingBankWalletConversionService::class);
        try { $service->preview($entry->categoryEvent->event, $entry, $payer, 'PF-CONVERT', 'Reviewed independently.', true); $this->fail('Non-super accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        try { $service->preview(Event::factory()->create(), $entry, $actor, 'PF-CONVERT', 'Reviewed independently.', true); $this->fail('Foreign event accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        foreach ([['OTHER-REFERENCE', true], ['PF-CONVERT', false]] as [$reference, $verified]) {
            $this->blocked(fn () => $service->preview($entry->categoryEvent->event, $entry, $actor, $reference, 'Reviewed independently.', $verified));
        }
        $preview = $this->preview($actor, $entry);
        $entry->update(['refund_account_name' => 'Changed after review']);
        $this->blocked(fn () => $service->execute($entry->categoryEvent->event, $entry, $actor, $preview['token']));
        $preview = $this->preview($actor, $entry);
        $otherActor = User::factory()->create()->assignRole('super-user');
        $this->blocked(fn () => $service->execute($entry->categoryEvent->event, $entry, $otherActor, $preview['token']));
        $this->travel(16)->minutes();
        $this->blocked(fn () => $service->execute($entry->categoryEvent->event, $entry, $actor, $preview['token']));
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_non_full_claim_or_mismatched_capture_or_existing_wallet_credit_requires_reconciliation(): void
    {
        [$actor, $payer, $entry, $order] = $this->scenario();
        $entry->update(['refund_net' => 405, 'refund_fee' => 45]);
        $this->blocked(fn () => $this->preview($actor, $entry));
        $entry->update(['refund_net' => 450, 'refund_fee' => 0]);
        $order->update(['payfast_amount_due' => 451]);
        $this->blocked(fn () => $this->preview($actor, $entry));
        $order->update(['payfast_amount_due' => 450]);
        $wallet = $payer->wallet()->create();
        app(LedgerService::class)->appendWalletCredit($wallet, 450, 'event_registration_refund', $entry->id);
        $this->blocked(fn () => $this->preview($actor, $entry));
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_ledger_failure_rolls_back_bank_claim_wallet_creation_and_audit(): void
    {
        [$actor, $payer, $entry] = $this->scenario();
        $preview = $this->preview($actor, $entry);
        $this->mock(LedgerService::class, fn ($mock) => $mock->shouldReceive('appendWalletCredit')->once()->andThrow(new \RuntimeException('Storage failed')));
        try { app(PendingBankWalletConversionService::class)->execute($entry->categoryEvent->event, $entry, $actor, $preview['token']); $this->fail('Expected failure'); }
        catch (\RuntimeException $e) { $this->assertSame('Storage failed', $e->getMessage()); }
        $this->assertSame('pending', $entry->fresh()->refund_status);
        $this->assertSame('bank', $entry->fresh()->refund_method);
        $this->assertNull($payer->fresh()->wallet);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(0, \Spatie\Activitylog\Models\Activity::where('description', PendingBankWalletConversionService::AUDIT)->count());
    }

    public function test_durable_inflight_or_uncertain_provider_claim_blocks_conversion_and_every_canonical_wallet_fallback(): void
    {
        [$actor, $payer, $entry, $order] = $this->scenario();
        $attempt = IndividualProviderRefundAttempt::create(['entry_id' => $entry->id, 'order_id' => $order->id,
            'receipt_id' => Transaction::where('pf_payment_id', 'PF-CONVERT')->sole()->id, 'pf_payment_id' => 'PF-CONVERT', 'amount' => 450, 'status' => 'dispatching']);
        $this->blocked(fn () => $this->preview($actor, $entry));
        $wallet = $payer->wallet()->create();
        $this->blocked(fn () => app(RefundExecutionService::class)->executeWalletRefund($entry, $wallet, 450, 'admin_refund', $entry->id));
        $attempt->update(['status' => 'uncertain', 'finished_at' => now()]);
        $this->blocked(fn () => $this->preview($actor, $entry));
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_provider_dispatch_claim_commits_before_transport_and_prevents_second_dispatch_and_wallet_conversion(): void
    {
        [$actor, , $entry] = $this->scenario();
        $guard = app(IndividualProviderRefundGuard::class);
        $count = 0;
        $result = $guard->dispatch('PF-CONVERT', 450, function () use (&$count, $actor, $entry) {
            $count++;
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame('dispatching', IndividualProviderRefundAttempt::sole()->status);
            $this->blocked(fn () => $this->preview($actor, $entry));
            return ['success' => true];
        });
        $this->assertTrue($result['success']);
        $this->assertSame('confirmed', IndividualProviderRefundAttempt::sole()->status);
        $this->blocked(fn () => $guard->dispatch('PF-CONVERT', 450, function () use (&$count) { $count++; return ['success' => true]; }));
        $this->blocked(fn () => $this->preview($actor, $entry));
        $this->assertSame(1, $count);
    }

    public function test_stale_provider_request_after_conversion_never_posts(): void
    {
        [$actor, , $entry] = $this->scenario();
        $preview = $this->preview($actor, $entry);
        app(PendingBankWalletConversionService::class)->execute($entry->categoryEvent->event, $entry, $actor, $preview['token']);
        $this->blocked(fn () => app(\App\Services\Payfast::class)->refund('PF-CONVERT', 450));
        Http::assertNothingSent();
    }

    public function test_failed_provider_response_is_uncertain_and_does_not_authorize_wallet_fallback(): void
    {
        [$actor, , $entry] = $this->scenario();
        $result = app(IndividualProviderRefundGuard::class)->dispatch('PF-CONVERT', 450, fn () => ['success' => false, 'error' => 'HTTP 502']);
        $this->assertFalse($result['success']);
        $this->assertSame('uncertain', IndividualProviderRefundAttempt::sole()->status);
        $this->blocked(fn () => $this->preview($actor, $entry));
        $this->blocked(fn () => app(IndividualProviderRefundGuard::class)->dispatch('PF-CONVERT', 450, fn () => $this->fail('No second dispatch')));
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_provider_timeout_retains_uncertain_claim_and_real_payfast_post_is_intercepted(): void
    {
        [$actor, , $entry] = $this->scenario();
        try {
            app(IndividualProviderRefundGuard::class)->dispatch('PF-CONVERT', 450, fn () => throw new \RuntimeException('Provider timed out'));
            $this->fail('Expected provider failure');
        } catch (\RuntimeException $e) { $this->assertSame('Provider timed out', $e->getMessage()); }
        $this->assertSame('uncertain', IndividualProviderRefundAttempt::sole()->status);
        $this->blocked(fn () => $this->preview($actor, $entry));
        $this->blocked(fn () => app(\App\Services\Payfast::class)->refund('PF-CONVERT', 450));
        Http::assertNothingSent();
    }

    public function test_real_payfast_refund_wrapper_records_one_claim_before_its_fake_http_post(): void
    {
        $this->scenario();
        config(['services.payfast.merchant_id' => '10000100', 'services.payfast.passphrase_live' => 'test-passphrase']);
        Http::fake(['https://api.payfast.co.za/refunds/*' => function ($request) {
            $this->assertSame('dispatching', IndividualProviderRefundAttempt::sole()->status);
            $this->assertSame(0, DB::transactionLevel());
            return Http::response(['status' => 'success'], 200);
        }]);
        $this->assertTrue(app(\App\Services\Payfast::class)->refund('PF-CONVERT', 450)['success']);
        $this->assertSame('confirmed', IndividualProviderRefundAttempt::sole()->status);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['amount'] === 45000);
    }

    public function test_corrected_source_keeps_owning_provider_path_but_cannot_bypass_completed_wallet_guard(): void
    {
        [, , $entry, $order] = $this->scenario();
        \App\Models\IndividualBeneficiaryCorrection::forceCreate(['order_id' => $order->id, 'entry_id' => $entry->id,
            'original_player_id' => $order->items()->sole()->player_id, 'target_player_id' => 999, 'source_invitation_id' => 1, 'target_invitation_id' => 2,
            'actor_id' => 1, 'reason' => 'Unsupported corrected source fixture', 'fingerprint' => str_repeat('a', 64), 'payment_snapshot' => [], 'invitation_snapshot' => []]);
        $called = 0;
        app(IndividualProviderRefundGuard::class)->dispatch('PF-CONVERT', 450, function () use (&$called) { $called++; return ['success' => true]; });
        $this->assertSame(1, $called);
        $this->assertDatabaseCount('individual_provider_refund_attempts', 0);
        $entry->update(['refund_status' => 'completed', 'refund_method' => 'wallet', 'refunded_at' => now()]);
        $this->blocked(fn () => app(IndividualProviderRefundGuard::class)->dispatch('PF-CONVERT', 450, fn () => $this->fail('Converted source cannot bypass guard')));
    }

    public function test_wrong_provider_dispatch_amount_is_rejected_before_transport(): void
    {
        [, , $entry] = $this->scenario();
        $this->blocked(fn () => app(IndividualProviderRefundGuard::class)->dispatch('PF-CONVERT', 405, fn () => $this->fail('Transport must not run')));
        $this->assertDatabaseCount('individual_provider_refund_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_query_failure_creates_no_dispatch_claim_and_is_not_negative_verification(): void
    {
        [$actor, , $entry] = $this->scenario();
        config(['services.payfast.merchant_id' => '10000100', 'services.payfast.passphrase_live' => 'test-passphrase']);
        Http::fake(['https://api.payfast.co.za/*' => Http::response(['error' => 'Unavailable'], 502)]);
        $result = app(\App\Services\Payfast::class)->refundUsingAvailableMethod('PF-CONVERT', 450, 'Test query failure');
        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('individual_provider_refund_attempts', 0);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        $this->blocked(fn () => app(PendingBankWalletConversionService::class)->preview($entry->categoryEvent->event, $entry, $actor,
            'PF-CONVERT', 'API query failed so there is no verified evidence.', false));
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_provider_claim_appearing_after_review_prevents_conversion(): void
    {
        [$actor, , $entry, $order] = $this->scenario();
        $preview = $this->preview($actor, $entry);
        IndividualProviderRefundAttempt::create(['entry_id' => $entry->id, 'order_id' => $order->id,
            'receipt_id' => Transaction::where('pf_payment_id', 'PF-CONVERT')->sole()->id, 'pf_payment_id' => 'PF-CONVERT', 'amount' => 450, 'status' => 'dispatching']);
        $this->blocked(fn () => app(PendingBankWalletConversionService::class)->execute($entry->categoryEvent->event, $entry, $actor, $preview['token']));
        $this->assertSame('bank', $entry->fresh()->refund_method);
        $this->assertSame('pending', $entry->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_multiple_player_registration_retains_its_owning_provider_workflow(): void
    {
        [, , $entry] = $this->scenario();
        $entry->registration->players()->attach(Player::factory()->create()->id);
        $called = 0;
        app(IndividualProviderRefundGuard::class)->dispatch('PF-CONVERT', 450, function () use (&$called) { $called++; return ['success' => true]; });
        $this->assertSame(1, $called);
        $this->assertDatabaseCount('individual_provider_refund_attempts', 0);
    }

    public function test_ordinary_admin_payfast_route_claims_approved_amount_before_the_real_fake_post(): void
    {
        [$actor, , $entry] = $this->scenario();
        $entry->update(['refund_status' => 'not_refunded', 'refund_method' => null, 'refund_gross' => 0, 'refund_fee' => 0, 'refund_net' => 0]);
        Http::fake([
            'api.payfast.co.za/refunds/*' => function ($request) use ($entry) {
                if ($request->method() === 'GET') return Http::response(['data' => ['status' => 'REFUNDABLE',
                    'amount_available_for_refund' => 45000, 'refund_partial' => ['method' => 'PAYMENT_SOURCE']]], 200);
                $this->assertSame('pending', $entry->fresh()->refund_status);
                $this->assertSame('bank', $entry->fresh()->refund_method);
                $this->assertEquals(405, $entry->fresh()->refund_net);
                $this->assertSame('dispatching', IndividualProviderRefundAttempt::sole()->status);
                return Http::response(['status' => 'success'], 200);
            },
        ]);
        $this->actingAs($actor)->post(route('admin.registration.refund.store', [$entry->categoryEvent->event_id, $entry]), ['method' => 'payfast'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $entry->fresh()->refund_status);
        $this->assertSame('payfast', $entry->fresh()->refund_method);
        $this->assertEquals(405, $entry->fresh()->refund_net);
        $this->assertSame('confirmed', IndividualProviderRefundAttempt::sole()->status);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['amount'] === 40500);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_ordinary_admin_provider_query_failure_leaves_visible_pending_bank_claim_without_dispatch(): void
    {
        [$actor, , $entry] = $this->scenario();
        $entry->update(['refund_status' => 'not_refunded', 'refund_method' => null, 'refund_gross' => 0, 'refund_fee' => 0, 'refund_net' => 0]);
        Http::fake(['api.payfast.co.za/*' => Http::response(['error' => 'Unavailable'], 502)]);
        $this->actingAs($actor)->post(route('admin.registration.refund.store', [$entry->categoryEvent->event_id, $entry]), ['method' => 'payfast'])->assertSessionHasErrors();
        $this->assertSame('pending', $entry->fresh()->refund_status);
        $this->assertSame('bank', $entry->fresh()->refund_method);
        $this->assertDatabaseCount('individual_provider_refund_attempts', 0);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    private function preview($actor, $entry): array
    {
        return app(PendingBankWalletConversionService::class)->preview($entry->categoryEvent->event, $entry, $actor, 'PF-CONVERT',
            'Operator independently verified no PayFast refund and no bank payment.', true);
    }

    private function blocked(callable $operation): void
    {
        try { $operation(); $this->fail('Unsafe refund operation accepted'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }

    private function immutablePaymentRows(): array
    {
        return collect(['registration_orders', 'registration_order_items', 'transactions_pf', 'event_nominations', 'player_registrations', 'user_players'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    private function scenario(): array
    {
        Mail::fake(); Http::preventStrayRequests(); config(['services.payment_failure_alert.enabled' => false]);
        Role::findOrCreate('super-user', 'web'); $actor = User::factory()->create()->assignRole('super-user');
        $payer = User::factory()->create(['name' => 'Original Payer']);
        $entry = CategoryEventRegistration::factory()->pendingRefund()->create(['user_id' => $payer->id, 'payment_method' => 'payfast',
            'pf_transaction_id' => 'PF-CONVERT', 'refund_gross' => 450, 'refund_fee' => 0, 'refund_net' => 450]);
        $player = Player::factory()->create(); $entry->registration->players()->attach($player->id);
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 450, 'pay_status' => true, 'payfast_paid' => true,
            'payfast_amount_due' => 450, 'payfast_pf_payment_id' => 'PF-CONVERT', 'payment_method' => 'payfast', 'status' => 'completed']);
        RegistrationOrderItems::forceCreate(['order_id' => $order->id, 'registration_id' => $entry->registration_id, 'category_event_id' => $entry->category_event_id,
            'player_id' => $player->id, 'user_id' => $payer->id, 'item_price' => 450]);
        Transaction::forceCreate(['pf_payment_id' => 'PF-CONVERT', 'custom_int5' => $order->id, 'custom_int4' => $payer->id,
            'custom_int1' => $entry->category_event_id, 'custom_int2' => $player->id, 'custom_int3' => $entry->categoryEvent->event_id,
            'amount_gross' => 450, 'amount_fee' => 19.10, 'amount_net' => 430.90]);
        $nomination = EventNomination::create(['event_id' => $entry->categoryEvent->event_id, 'category_event_id' => $entry->category_event_id, 'player_id' => $player->id]);
        $nomination->forceFill(['archived_at' => now(), 'archived_by' => $actor->id, 'archive_reason' => 'Already withdrawn'])->save();
        $other = CategoryEventRegistration::factory()->paid()->create(['user_id' => $payer->id, 'payment_method' => 'payfast', 'pf_transaction_id' => 'PF-CAV-PRESERVED']);
        $other->registration->players()->attach($player->id);
        $otherOrder = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 450, 'pay_status' => true, 'payfast_paid' => true,
            'payfast_amount_due' => 450, 'payfast_pf_payment_id' => 'PF-CAV-PRESERVED', 'payment_method' => 'payfast', 'status' => 'completed']);
        RegistrationOrderItems::forceCreate(['order_id' => $otherOrder->id, 'registration_id' => $other->registration_id,
            'category_event_id' => $other->category_event_id, 'player_id' => $player->id, 'user_id' => $payer->id, 'item_price' => 450]);
        Transaction::forceCreate(['pf_payment_id' => 'PF-CAV-PRESERVED', 'custom_int5' => $otherOrder->id, 'custom_int4' => $payer->id,
            'custom_int1' => $other->category_event_id, 'custom_int2' => $player->id, 'custom_int3' => $other->categoryEvent->event_id,
            'amount_gross' => 450, 'amount_fee' => 19.10, 'amount_net' => 430.90]);
        return [$actor, $payer, $entry->fresh(), $order->fresh(), $nomination->fresh(), $other->fresh()];
    }
}
