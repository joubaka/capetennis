<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\{CheckoutExpiryPolicy, OrdinaryDashboardPayfastReconciliationService, PaymentTransactionService, RegistrationPaymentService, UnpaidCheckoutExpiryService};
use App\Models\{AuditEvent, CategoryEvent, CategoryEventRegistration, Event, EventType, Player, Registration, RegistrationOrder, RegistrationOrderItems, SiteSetting, Transaction, User};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\{DB, Mail, Schema};
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrdinaryDashboardPayfastReconciliationTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        $this->assertTrue($this->isolated(), 'Payment regressions require isolated ct_testing MySQL or in-memory SQLite.');
        if (DB::connection()->getDriverName() === 'sqlite') \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->app && $this->app->bound('db') && $this->isolated()) {
                try { $this->truncateTablesForAllConnections(); }
                finally { if (DB::connection()->getDriverName() === 'sqlite') \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false; }
            }
        } finally { parent::tearDown(); }
    }

    private function isolated(): bool
    {
        $db = DB::connection();
        return ($db->getDriverName() === 'mysql' && $db->getDatabaseName() === 'ct_testing')
            || ($db->getDriverName() === 'sqlite' && $db->getDatabaseName() === ':memory:');
    }

    public function test_two_player_legacy_schema_basket_records_one_exact_receipt_and_replays_without_side_effects(): void
    {
        [$actor, $order, $evidence] = $this->scenario(2);
        Schema::table('transactions_pf', fn ($table) => $table->dropColumn(['registration_id', 'payment_status']));
        try {
            $before = $this->counts();
            $preview = $this->runRecovery($order, $actor, $evidence);
            $this->assertSame($before, $this->counts());
            $this->assertCount(2, $preview['basket']);
            $this->assertSame('900.00', $preview['gross']);
            $this->assertSame('35.42', $preview['fee']);
            $this->assertSame('30.00', $preview['reviewed_current_platform_fee']);
            $this->assertTrue($this->runRecovery($order, $actor, $evidence, $preview['confirmation'])['applied']);
            $receipt = Transaction::sole();
            $this->assertEquals(900, $receipt->amount_gross);
            $this->assertEquals(35.42, $receipt->amount_fee);
            $this->assertEquals(864.58, $receipt->amount_net);
            $this->assertEquals(30, $receipt->cape_tennis_fee);
            $this->assertSame($evidence['paid_at'], $receipt->created_at->toIso8601String());
            $this->assertSame(2, CategoryEventRegistration::where('payment_status_id', 1)->where('pf_transaction_id', $evidence['provider_id'])->count());
            $this->assertTrue($order->fresh()->pay_status);
            $after = $this->counts();
            $expected = $before; $expected['transactions_pf']++;
            $this->assertSame($expected, $after);
            $this->assertTrue($this->runRecovery($order, $actor, $evidence, $preview['confirmation'])['already_reconciled']);
            $this->sendCallback($order, $evidence)->assertOk();
            $this->assertSame($after, $this->counts());
            $this->assertSame(1, Activity::where('description', OrdinaryDashboardPayfastReconciliationService::AUDIT)->count());
            Mail::assertNothingSent(); Mail::assertNothingQueued();
        } finally {
            Schema::table('transactions_pf', function ($table) {
                $table->unsignedBigInteger('registration_id')->nullable(); $table->string('payment_status')->nullable();
            });
        }
    }

    public function test_single_player_and_historical_five_minute_window_are_supported_but_late_payment_is_not(): void
    {
        [$actor, $order, $evidence] = $this->scenario();
        $expiry = Activity::where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)
            ->where('description', 'Unpaid registration checkout expired; reservation released')->sole();
        $properties = $expiry->properties->all(); $properties['timeout_minutes'] = 5;
        $expiry->properties = $properties; $expiry->save();
        $late = $evidence; $late['paid_at'] = $order->created_at->addMinutes(6)->toIso8601String();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $late));
        $preview = $this->runRecovery($order, $actor, $evidence);
        $this->assertSame(5, $preview['original_expiry_minutes']);
        $this->assertTrue($this->runRecovery($order, $actor, $evidence, $preview['confirmation'])['applied']);
        $this->assertDatabaseCount('transactions_pf', 1);
    }

    public function test_actor_evidence_source_freshness_and_every_provider_identifier_are_checked(): void
    {
        [$actor, $order, $evidence] = $this->scenario(2);
        try { $this->runRecovery($order, User::factory()->create(), $evidence); $this->fail('Foreign actor accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        foreach (['order_id', 'payer_id', 'event_id', 'category_event_id', 'player_id'] as $field) {
            $bad = $evidence; $bad[$field]++;
            $this->blocked(fn () => $this->runRecovery($order, $actor, $bad));
        }
        foreach ([['source' => 'signed_itn'], ['attested' => false], ['verified_by' => $actor->id + 1],
            ['verified_at' => now()->subMinutes(31)->toIso8601String()], ['verified_at' => now()->addMinutes(1)->toIso8601String()],
            ['gross' => '900.001'], ['gross' => '901.00', 'net' => '865.58'], ['fee' => '35.43'], ['observed_on' => now()->toDateString()]] as $bad) {
            $this->blocked(fn () => $this->runRecovery($order, $actor, array_replace($evidence, $bad)));
        }
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_non_anchor_basket_item_provenance_and_capacity_are_mandatory(): void
    {
        [$actor, $order, $evidence] = $this->scenario(2);
        $other = $order->items->last();
        $raw = $other->getRawOriginal();
        DB::table('registration_order_items')->where('id', $other->id)->update(['player_id' => Player::factory()->create()->id]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        DB::table('registration_order_items')->where('id', $other->id)->update($raw);
        $history = DB::table('audit_events')->where('subject_type', $other->getMorphClass())->where('subject_id', $other->id)->get()->map(fn ($r) => (array) $r)->all();
        DB::table('audit_events')->where('subject_type', $other->getMorphClass())->where('subject_id', $other->id)->delete();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        DB::table('audit_events')->insert($history);
        CategoryEvent::findOrFail($other->category_event_id)->forceFill(['entry_limit' => 0])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_same_category_basket_requires_capacity_for_all_items_together(): void
    {
        [$actor, $order, $evidence] = $this->scenario(2, true);
        $category = $order->items->first()->category_event;
        $category->forceFill(['entry_limit' => 1])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $category->forceFill(['entry_limit' => 2])->save();
        $preview = $this->runRecovery($order, $actor, $evidence);
        $this->assertTrue($this->runRecovery($order, $actor, $evidence, $preview['confirmation'])['applied']);
        $this->assertSame(2, CategoryEventRegistration::where('category_event_id', $category->id)->where('payment_status_id', 1)->count());
    }

    public function test_changed_actor_reason_basket_or_evidence_invalidates_the_preview(): void
    {
        [$actor, $order, $evidence] = $this->scenario(2);
        $preview = $this->runRecovery($order, $actor, $evidence);
        $other = User::factory()->create()->assignRole('super-user');
        $this->blocked(fn () => $this->runRecovery($order, $other, array_replace($evidence, ['verified_by' => $other->id]), $preview['confirmation']));
        $this->blocked(fn () => app(OrdinaryDashboardPayfastReconciliationService::class)->run($order->id, $actor, $evidence, 'Changed reviewed reason', $preview['confirmation']));
        $changed = $evidence; $changed['fee'] = '35.41'; $changed['net'] = '864.59';
        $this->blocked(fn () => $this->runRecovery($order, $actor, $changed, $preview['confirmation']));
        $event = $order->items->first()->category_event->event;
        $event->forceFill(['cape_tennis_fee' => 16])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence, $preview['confirmation']));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_existing_receipt_wallet_refund_or_competing_checkout_blocks_the_entire_basket(): void
    {
        [$actor, $order, $evidence] = $this->scenario(2);
        $entry = CategoryEventRegistration::withTrashed()->where('registration_id', $order->items->last()->registration_id)->sole();
        $raw = $entry->getRawOriginal();
        DB::table('category_event_registrations')->where('id', $entry->id)->update(['refund_status' => 'completed']);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        DB::table('category_event_registrations')->where('id', $entry->id)->update($raw);
        DB::table('registration_orders')->where('id', $order->id)->update(['wallet_reserved' => 1]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        DB::table('registration_orders')->where('id', $order->id)->update(['wallet_reserved' => 0]);
        $foreign = RegistrationOrder::create(['user_id' => $order->user_id, 'status' => 'completed', 'total_fee' => 450,
            'pay_status' => true, 'payfast_paid' => true, 'payfast_pf_payment_id' => 'PAID-COMPETING-ORDER']);
        $copy = $order->items->last()->replicate(); $copy->order_id = $foreign->id; $copy->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $copy->delete(); $foreign->delete();
        $receipt = new Transaction(); $receipt->forceFill(['pf_payment_id' => $evidence['provider_id'], 'amount_gross' => 900])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_receipt_failure_after_restoration_rolls_back_the_whole_basket(): void
    {
        [$actor, $order, $evidence] = $this->scenario(2);
        $before = $this->counts();
        $rawOrder = $order->getRawOriginal();
        $rawEntries = CategoryEventRegistration::withTrashed()->orderBy('id')->get()->map(fn ($m) => $m->getRawOriginal())->all();
        $preview = $this->runRecovery($order, $actor, $evidence);
        $this->app->instance(PaymentTransactionService::class, new class extends PaymentTransactionService {
            public function recordAttestedOrdinaryRegistrationReceipt(RegistrationOrder $order, array $evidence, \Carbon\CarbonInterface $paidAt): Transaction
            {
                parent::recordAttestedOrdinaryRegistrationReceipt($order, $evidence, $paidAt);
                throw new \RuntimeException('Injected storage failure after original basket restoration.');
            }
        });
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence, $preview['confirmation']));
        $this->assertSame($before, $this->counts());
        $this->assertSame($rawOrder, $order->fresh()->getRawOriginal());
        $this->assertSame($rawEntries, CategoryEventRegistration::withTrashed()->orderBy('id')->get()->map(fn ($m) => $m->getRawOriginal())->all());
        $this->assertSame(0, Activity::where('description', OrdinaryDashboardPayfastReconciliationService::AUDIT)->count());
        Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    public function test_non_anchor_eligibility_denial_keeps_all_original_entries_closed(): void
    {
        [$actor, $order, $evidence] = $this->scenario(2);
        $denied = (int) $order->items->last()->player_id;
        $this->app->instance(\App\Domain\Entries\Services\EntryEligibilityService::class, new class($denied) extends \App\Domain\Entries\Services\EntryEligibilityService {
            public function __construct(private int $denied) { parent::__construct(); }
            public function assertCanRegister(CategoryEvent $categoryEvent, int $playerId): void
            {
                if ($playerId === $this->denied) throw new \RuntimeException('This non-anchor player is ineligible.');
                parent::assertCanRegister($categoryEvent, $playerId);
            }
        });
        $before = $this->counts();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertSame($before, $this->counts());
        $this->assertSame(0, CategoryEventRegistration::count());
    }

    public function test_prior_never_handed_off_expired_attempt_is_preserved_unchanged(): void
    {
        [$actor, $order, $evidence, $prior] = $this->scenario(1, false, false);
        $before = $prior->getRawOriginal();
        $preview = $this->runRecovery($order, $actor, $evidence);
        $this->assertSame($prior->id, $preview['preserved_prior_attempts'][0]['order_id']);
        $this->assertFalse($preview['preserved_prior_attempts'][0]['had_payfast_handoff']);
        $this->assertTrue($this->runRecovery($order, $actor, $evidence, $preview['confirmation'])['applied']);
        $this->assertSame($before, $prior->fresh()->getRawOriginal());
    }

    public function test_prior_handed_off_negative_observation_is_bound_and_future_payment_remains_receipt_only(): void
    {
        [$actor, $order, $evidence, $prior] = $this->scenario(1, false, true);
        $withoutObservation = $evidence; unset($withoutObservation['prior_attempt_observations']);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $withoutObservation));
        $bad = $evidence; $bad['prior_attempt_observations'][0]['outcome'] = 'provider_closed';
        $this->blocked(fn () => $this->runRecovery($order, $actor, $bad));
        $bad = $evidence; $bad['prior_attempt_observations'][0]['searched_until'] = now()->subMinutes(31)->toIso8601String();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $bad));
        $preview = $this->runRecovery($order, $actor, $evidence);
        $this->assertTrue($preview['preserved_prior_attempts'][0]['future_settlement_still_possible']);
        $bad = $evidence; $bad['prior_attempt_observations'][0]['searched_from'] = CarbonImmutable::parse($bad['prior_attempt_observations'][0]['searched_from'])->subSecond()->toIso8601String();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $bad, $preview['confirmation']));
        $priorBefore = $prior->getRawOriginal();
        $this->assertTrue($this->runRecovery($order, $actor, $evidence, $preview['confirmation'])['applied']);
        $this->assertSame($priorBefore, $prior->fresh()->getRawOriginal());
        $targetBefore = $order->fresh()->getRawOriginal();
        $targetReceiptBefore = Transaction::sole()->getRawOriginal();
        $targetEntriesBefore = CategoryEventRegistration::orderBy('id')->get()->map(fn ($m) => $m->getRawOriginal())->all();
        $priorEvidence = $evidence; $priorEvidence['provider_id'] = 'PF-PRIOR-LATE-CHARGE'; $priorEvidence['order_id'] = $prior->id;
        $this->sendCallback($prior, $priorEvidence)->assertOk();
        $this->sendCallback($prior, $priorEvidence)->assertOk();
        $this->assertSame(2, Transaction::count());
        $this->assertSame($targetBefore, $order->fresh()->getRawOriginal());
        $this->assertSame($targetReceiptBefore, Transaction::where('pf_payment_id', $evidence['provider_id'])->sole()->getRawOriginal());
        $this->assertSame($targetEntriesBefore, CategoryEventRegistration::orderBy('id')->get()->map(fn ($m) => $m->getRawOriginal())->all());
        $this->assertSame('cancelled', $prior->fresh()->status);
        $this->assertFalse($prior->fresh()->pay_status);
        $this->assertTrue(CategoryEventRegistration::withTrashed()->where('registration_id', $prior->items->sole()->registration_id)->sole()->trashed());
        $this->assertDatabaseCount('wallet_transactions', 0);
        Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    private function runRecovery(RegistrationOrder $order, User $actor, array $evidence, ?string $confirmation = null): array
    {
        return app(OrdinaryDashboardPayfastReconciliationService::class)->run($order->id, $actor, $evidence, 'Reviewed on-time original dashboard payment', $confirmation);
    }

    private function blocked(callable $action): void
    {
        try { $action(); }
        catch (ValidationException|\RuntimeException $e) { $this->assertNotSame('', $e->getMessage()); return; }
        $this->fail('Unsafe recovery was accepted.');
    }

    private function counts(): array
    {
        $counts = [];
        foreach (['registration_orders', 'registration_order_items', 'registrations', 'category_event_registrations', 'transactions_pf', 'wallet_transactions', 'bulk_email_logs', 'user_players'] as $table) $counts[$table] = DB::table($table)->count();
        return $counts;
    }

    private function sendCallback(RegistrationOrder $order, array $evidence): \Illuminate\Testing\TestResponse
    {
        $payload = ['merchant_id' => 'test-merchant', 'payment_status' => 'COMPLETE', 'pf_payment_id' => $evidence['provider_id'],
            'amount_gross' => $evidence['gross'], 'custom_int1' => $evidence['category_event_id'], 'custom_int2' => $evidence['player_id'],
            'custom_int3' => $evidence['event_id'], 'custom_int4' => $evidence['payer_id'], 'custom_int5' => $order->id];
        $payload['signature'] = md5(http_build_query($payload).'&passphrase=test-passphrase');
        return $this->call('POST', route('notify'), $payload, [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($payload));
    }

    private function scenario(int $count = 1, bool $sameCategory = false, ?bool $priorHandoff = null): array
    {
        $this->assertTrue($this->isolated()); $this->travelTo(now()->startOfSecond());
        Mail::fake(); SiteSetting::set('player_email_on_registration', '1');
        config(['services.payfast.sandbox' => false, 'services.payfast.merchant_id' => 'test-merchant', 'services.payfast.passphrase_live' => 'test-passphrase', 'services.payment_failure_alert.enabled' => false]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $type = EventType::forceCreate(['name' => 'Ordinary individual', 'type' => EventType::INDIVIDUAL, 'code' => 'ordinary-dashboard-test']);
        $event = Event::factory()->create(['eventType' => $type->id, 'entryFee' => 450, 'cape_tennis_fee' => 15, 'deadline' => 2,
            'start_date' => now()->addDays(20)->toDateString(), 'published' => true, 'signUp' => true]);
        $payer = User::factory()->create(); $payer->wallet()->create();
        $specs = []; $firstCategory = null;
        for ($i = 0; $i < $count; $i++) {
            $category = $sameCategory && $firstCategory ? $firstCategory : CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => 450]);
            $firstCategory ??= $category; $specs[] = [$category, Player::factory()->create()];
        }
        $prior = null; $priorHandedAt = null;
        if ($priorHandoff !== null) {
            [$priorCategory, $priorPlayer] = $specs[0];
            $r = Registration::create([]); $r->players()->attach($priorPlayer);
            $r->categoryEvents()->attach($priorCategory, ['user_id' => $payer->id, 'payment_status_id' => 0, 'status' => 'active']);
            $prior = RegistrationOrder::create(['user_id' => $payer->id, 'status' => 'pending', 'total_fee' => 450, 'wallet_reserved' => 0,
                'payfast_amount_due' => 450, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);
            (new RegistrationOrderItems())->forceFill(['order_id' => $prior->id, 'user_id' => $payer->id, 'registration_id' => $r->id,
                'player_id' => $priorPlayer->id, 'category_event_id' => $priorCategory->id, 'item_price' => 450])->save();
            if ($priorHandoff) { $prior = app(RegistrationPaymentService::class)->preparePayfastHandoff($prior, $payer, 0, 450); $priorHandedAt = now()->toIso8601String(); }
            $this->travel(CheckoutExpiryPolicy::MINUTES + 1)->minutes();
            $this->assertTrue(app(UnpaidCheckoutExpiryService::class)->expire($prior->fresh()));
            $prior = $prior->fresh('items'); $this->travel(1)->minutes();
        }
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'status' => 'pending', 'total_fee' => 450 * $count, 'payment_method' => 'payfast',
            'wallet_reserved' => 0, 'payfast_amount_due' => 450 * $count, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);
        foreach ($specs as [$category, $player]) {
            $registration = Registration::create([]); $registration->players()->attach($player);
            $registration->categoryEvents()->attach($category, ['user_id' => $payer->id, 'payment_status_id' => 0, 'status' => 'active']);
            (new RegistrationOrderItems())->forceFill(['order_id' => $order->id, 'user_id' => $payer->id, 'registration_id' => $registration->id,
                'player_id' => $player->id, 'category_event_id' => $category->id, 'item_price' => 450])->save();
        }
        $order = app(RegistrationPaymentService::class)->preparePayfastHandoff($order, $payer, 0, 450 * $count);
        $paidAt = now()->addMinutes(2)->toIso8601String();
        $this->travel(CheckoutExpiryPolicy::MINUTES + 1)->minutes();
        $this->assertTrue(app(UnpaidCheckoutExpiryService::class)->expire($order->fresh()));
        $order = $order->fresh('items.category_event.event'); $anchor = $order->items->first();
        $evidence = ['source' => 'authenticated_payfast_dashboard', 'attested' => true, 'verified_by' => $actor->id,
            'verified_at' => now()->toIso8601String(), 'provider_status' => 'successful', 'currency' => 'ZAR', 'provider_id' => 'PF-ORDINARY-BASKET',
            'order_id' => $order->id, 'payer_id' => $payer->id, 'event_id' => $event->id,
            'category_event_id' => $anchor->category_event_id, 'player_id' => $anchor->player_id, 'paid_at' => $paidAt,
            'gross' => $count === 2 ? '900.00' : '450.00', 'fee' => $count === 2 ? '35.42' : '18.86', 'net' => $count === 2 ? '864.58' : '431.14'];
        if ($priorHandoff) {
            $evidence['prior_attempt_observations'] = [['source' => 'authenticated_payfast_dashboard_search', 'attested' => true,
                'verified_by' => $actor->id, 'verified_at' => now()->toIso8601String(), 'order_id' => $prior->id, 'payer_id' => $payer->id,
                'event_id' => $event->id, 'category_event_id' => $anchor->category_event_id, 'player_id' => $anchor->player_id,
                'amount' => '450.00', 'searched_from' => $priorHandedAt, 'searched_until' => now()->toIso8601String(), 'outcome' => 'no_matching_settlement_observed']];
        }
        return [$actor, $order, $evidence, $prior];
    }
}
