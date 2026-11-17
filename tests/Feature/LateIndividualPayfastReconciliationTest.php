<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\{CheckoutExpiryPolicy, LateIndividualPayfastReconciliationService, RegistrationPaymentService, UnpaidCheckoutExpiryService};
use App\Models\{AuditEvent, CategoryEvent, CategoryEventRegistration, Event, EventType, Player, Registration, RegistrationOrder, RegistrationOrderItems, SiteSetting, Transaction, User};
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\{DB, Mail, Schema};
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LateIndividualPayfastReconciliationTest extends TestCase
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

    public function test_existing_receipt_original_entry_and_counts_are_preserved_through_apply_repeat_and_signed_itn(): void
    {
        [$actor, $order, $entry, $receipt, $payload] = $this->scenario();
        Schema::table('transactions_pf', fn ($table) => $table->dropColumn(['registration_id', 'payment_status']));
        try {
            $receipt = $receipt->fresh();
            $beforeReceipt = $receipt->getRawOriginal();
            $before = $this->counts();
            $preview = $this->runRecovery($order, $actor);
            $this->assertSame($before, $this->counts());
            $this->assertSame($receipt->id, $preview['receipt_id']);
            $this->assertFalse($preview['new_charge']);
            $this->assertFalse($preview['new_receipt']);
            $this->assertSame(30, $preview['original_expiry_minutes']);
            $this->assertTrue($this->runRecovery($order, $actor, $preview['confirmation'])['applied']);
            $this->assertSame('completed', $order->fresh()->status);
            $this->assertTrue($order->fresh()->pay_status);
            $this->assertFalse($entry->fresh()->trashed());
            $this->assertSame('active', $entry->fresh()->status);
            $this->assertEquals(1, $entry->fresh()->payment_status_id);
            $this->assertSame($receipt->pf_payment_id, $entry->fresh()->pf_transaction_id);
            $afterReceipt = $receipt->fresh()->getRawOriginal();
            unset($beforeReceipt['cape_tennis_fee'], $beforeReceipt['updated_at'], $afterReceipt['cape_tennis_fee'], $afterReceipt['updated_at']);
            $this->assertSame($beforeReceipt, $afterReceipt);
            $this->assertEquals(15, $receipt->fresh()->cape_tennis_fee);
            $this->assertSame($before, $this->counts());
            $this->assertTrue($this->runRecovery($order, $actor, $preview['confirmation'])['already_reconciled']);
            $this->sendCallback($payload)->assertOk();
            $this->assertSame($before, $this->counts());
            $this->assertSame(1, Activity::where('description', LateIndividualPayfastReconciliationService::AUDIT)->count());
            Mail::assertNothingSent(); Mail::assertNothingQueued();
        } finally {
            Schema::table('transactions_pf', function ($table) {
                $table->unsignedBigInteger('registration_id')->nullable();
                $table->string('payment_status')->nullable();
            });
        }
    }

    public function test_actor_provider_cross_event_and_stale_tokens_cannot_apply(): void
    {
        [$actor, $order] = $this->scenario();
        $preview = $this->runRecovery($order, $actor);
        try { $this->runRecovery($order, User::factory()->create()); $this->fail('Non-super-user accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $this->blocked(fn () => $this->runRecovery($order, $actor, null, 'FOREIGN-REFERENCE'));
        $this->assertFalse($order->fresh()->pay_status, 'Provider mismatch mutated order.');
        $other = User::factory()->create()->assignRole('super-user');
        $this->blocked(fn () => $this->runRecovery($order, $other, $preview['confirmation']));
        $this->assertFalse($order->fresh()->pay_status, 'Foreign actor token mutated order.');
        $category = $order->items->sole()->category_event;
        $category->event->forceFill(['cape_tennis_fee' => 16])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $preview['confirmation']));
        $this->assertFalse($order->fresh()->pay_status, 'Stale event fee token mutated order.');
        $receipt = Transaction::sole();
        $receipt->forceFill(['event_id' => $category->event_id + 100])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor));
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_refund_withdrawal_wallet_and_changed_expiry_records_block_without_writes(): void
    {
        [$actor, $order, $entry] = $this->scenario();
        $before = $this->counts();
        foreach ([['refund_status' => 'completed'], ['withdrawn_at' => now()], ['admin_payment_status' => 'paid'], ['payment_status_id' => 1]] as $bad) {
            $original = $entry->getRawOriginal();
            DB::table('category_event_registrations')->where('id', $entry->id)->update($bad);
            $this->blocked(fn () => $this->runRecovery($order, $actor));
            DB::table('category_event_registrations')->where('id', $entry->id)->update($original);
        }
        $order->forceFill(['wallet_reserved' => 10])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor));
        $this->assertSame($before, $this->counts());
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_missing_or_inconsistent_late_receipt_and_provenance_are_rejected(): void
    {
        [$actor, $order, , $receipt] = $this->scenario();
        foreach ([['amount_gross' => 451], ['custom_int2' => $receipt->custom_int2 + 1], ['is_test' => true], ['custom_str5' => 'PlayerPlus']] as $bad) {
            $raw = $receipt->getRawOriginal();
            DB::table('transactions_pf')->where('id', $receipt->id)->update($bad);
            $this->blocked(fn () => $this->runRecovery($order, $actor));
            DB::table('transactions_pf')->where('id', $receipt->id)->update($raw);
        }
        Activity::where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)
            ->where('description', 'Expired PayFast charge requires reconciliation')->delete();
        $this->blocked(fn () => $this->runRecovery($order, $actor));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 1);
    }

    public function test_duplicate_receipt_and_successor_checkout_require_separate_review(): void
    {
        [$actor, $order, , $receipt] = $this->scenario();
        $duplicate = $receipt->replicate(); $duplicate->pf_payment_id = 'OTHER-RECEIPT'; $duplicate->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor));
        $duplicate->delete();
        $other = RegistrationOrder::create(['user_id' => $order->user_id, 'status' => 'pending', 'total_fee' => 450]);
        $item = $order->items->sole()->replicate(); $item->order_id = $other->id; $item->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor));
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_current_event_category_capacity_and_competition_gates_are_rechecked(): void
    {
        [$actor, $order, $entry] = $this->scenario();
        $category = $order->items->sole()->category_event;
        $event = $category->event;
        $event->update(['signUp' => false]); $this->blocked(fn () => $this->runRecovery($order, $actor));
        $event->update(['signUp' => true]);
        $category->update(['entry_limit' => 0]); $this->blocked(fn () => $this->runRecovery($order, $actor));
        $category->update(['entry_limit' => null]);
        DB::table('withdrawals')->insert(['registration_id' => $entry->registration_id, 'created_at' => now(), 'updated_at' => now()]);
        $this->blocked(fn () => $this->runRecovery($order, $actor));
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_tampered_history_or_expired_preview_cannot_apply(): void
    {
        [$actor, $order] = $this->scenario();
        $preview = $this->runRecovery($order, $actor);
        $this->travel(11)->minutes();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $preview['confirmation']));
        $audit = AuditEvent::where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)->firstOrFail();
        DB::table('audit_events')->where('id', $audit->id)->update(['action' => 'tampered']);
        $this->blocked(fn () => $this->runRecovery($order, $actor));
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_prior_unsent_expired_attempt_is_disclosed_fingerprinted_and_preserved_unchanged(): void
    {
        [$actor, $order, , , , $prior] = $this->scenario(true);
        $priorEntry = CategoryEventRegistration::withTrashed()->where('registration_id', $prior->items->sole()->registration_id)->sole();
        $raw = [$prior->getRawOriginal(), $priorEntry->getRawOriginal()];
        $before = $this->counts();
        $preview = $this->runRecovery($order, $actor);
        $this->assertSame([$prior->id], $preview['preserved_never_handed_off_order_ids']);
        $this->assertTrue($this->runRecovery($order, $actor, $preview['confirmation'])['applied']);
        $this->assertTrue($this->runRecovery($order, $actor, $preview['confirmation'])['already_reconciled']);
        $this->assertSame($before, $this->counts());
        $this->assertSame($raw, [$prior->fresh()->getRawOriginal(), $priorEntry->fresh()->getRawOriginal()]);
        $this->assertSame([$prior->id], Activity::where('description', LateIndividualPayfastReconciliationService::AUDIT)->sole()->properties['preserved_never_handed_off_order_ids']);
    }

    public function test_prior_unsent_attempt_state_changes_overlap_and_handoff_history_block_recovery(): void
    {
        [$actor, $order, , , , $prior] = $this->scenario(true);
        $preview = $this->runRecovery($order, $actor);
        DB::table('registration_orders')->where('id', $prior->id)->update(['created_at' => $prior->created_at->copy()->subSecond()]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $preview['confirmation']));
        DB::table('registration_orders')->where('id', $prior->id)->update(['created_at' => $prior->created_at]);
        $expiry = Activity::where('subject_type', $prior->getMorphClass())->where('subject_id', $prior->id)->sole();
        $oldTime = $expiry->created_at;
        $expiry->forceFill(['created_at' => $order->created_at])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor));
        $expiry->forceFill(['created_at' => $oldTime])->save();
        activity('payment')->performedOn($prior)->log('supervised unresolved PayFast handoff released');
        $this->blocked(fn () => $this->runRecovery($order, $actor));
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_prior_attempt_requires_both_order_and_entry_history_independently(): void
    {
        [$actor, $order, , , , $prior] = $this->scenario(true);
        $entry = CategoryEventRegistration::withTrashed()->where('registration_id', $prior->items->sole()->registration_id)->sole();
        foreach ([$prior, $entry] as $subject) {
            $query = DB::table('audit_events')->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->id);
            $raw = $query->get()->map(fn ($row) => (array) $row)->all();
            $this->assertNotEmpty($raw);
            $query->delete();
            $this->blocked(fn () => $this->runRecovery($order, $actor));
            DB::table('audit_events')->insert($raw);
        }
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 1);
    }

    private function runRecovery(RegistrationOrder $order, User $actor, ?string $confirmation = null, string $provider = 'PF-LATE-ORIGINAL'): array
    {
        return app(LateIndividualPayfastReconciliationService::class)->run($order->id, $actor, $provider, 'Reviewed verified late original payment', $confirmation);
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

    private function sendCallback(array $payload): \Illuminate\Testing\TestResponse
    {
        $payload['signature'] = md5(http_build_query($payload).'&passphrase=test-passphrase');
        return $this->call('POST', route('notify'), $payload, [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($payload));
    }

    private function scenario(bool $withPrior = false): array
    {
        $this->assertTrue($this->isolated());
        $this->travelTo(now()->startOfSecond());
        Mail::fake(); SiteSetting::set('player_email_on_registration', '1');
        config(['services.payfast.sandbox' => false, 'services.payfast.merchant_id' => 'test-merchant', 'services.payfast.passphrase_live' => 'test-passphrase', 'services.payment_failure_alert.enabled' => false]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $type = EventType::forceCreate(['name' => 'Ordinary individual', 'type' => EventType::INDIVIDUAL, 'code' => 'ordinary-individual-test']);
        $event = Event::factory()->create(['eventType' => $type->id, 'entryFee' => 450, 'cape_tennis_fee' => 15, 'deadline' => 2,
            'start_date' => now()->addDays(20)->toDateString(), 'published' => true, 'signUp' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => 450]);
        $payer = User::factory()->create(); $payer->wallet()->create();
        $player = Player::factory()->create();
        $prior = null;
        if ($withPrior) {
            $priorRegistration = Registration::create([]); $priorRegistration->players()->attach($player);
            $priorRegistration->categoryEvents()->attach($category, ['user_id' => $payer->id, 'payment_status_id' => 0, 'status' => 'active']);
            $prior = RegistrationOrder::create(['user_id' => $payer->id, 'status' => 'pending', 'total_fee' => 450,
                'wallet_reserved' => 0, 'payfast_amount_due' => 450, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);
            (new RegistrationOrderItems())->forceFill(['order_id' => $prior->id, 'user_id' => $payer->id, 'registration_id' => $priorRegistration->id,
                'player_id' => $player->id, 'category_event_id' => $category->id, 'item_price' => 450])->save();
            $this->travel(CheckoutExpiryPolicy::MINUTES + 1)->minutes();
            $this->assertTrue(app(UnpaidCheckoutExpiryService::class)->expire($prior->fresh()));
            $prior = $prior->fresh('items');
            $this->travel(1)->minutes();
        }
        $registration = Registration::create([]); $registration->players()->attach($player);
        $registration->categoryEvents()->attach($category, ['user_id' => $payer->id, 'payment_status_id' => 0, 'status' => 'active']);
        $entry = CategoryEventRegistration::where('registration_id', $registration->id)->sole();
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'status' => 'pending', 'total_fee' => 450, 'payment_method' => 'payfast',
            'wallet_reserved' => 0, 'payfast_amount_due' => 450, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);
        (new RegistrationOrderItems())->forceFill(['order_id' => $order->id, 'user_id' => $payer->id, 'registration_id' => $registration->id,
            'player_id' => $player->id, 'category_event_id' => $category->id, 'item_price' => 450])->save();
        $order = app(RegistrationPaymentService::class)->preparePayfastHandoff($order, $payer, 0, 450);
        $this->travel(CheckoutExpiryPolicy::MINUTES + 1)->minutes();
        $this->assertTrue(app(UnpaidCheckoutExpiryService::class)->expire($order->fresh()));
        $this->travel(2)->minutes();
        $payload = ['merchant_id' => 'test-merchant', 'payment_status' => 'COMPLETE', 'pf_payment_id' => 'PF-LATE-ORIGINAL', 'amount_gross' => '450.00',
            'custom_int1' => $category->id, 'custom_int2' => $player->id, 'custom_int3' => $event->id, 'custom_int4' => $payer->id, 'custom_int5' => $order->id];
        $this->sendCallback($payload)->assertOk();
        return [$actor, $order->fresh('items.category_event.event'), $entry->fresh(), Transaction::sole(), $payload, $prior];
    }
}
