<?php

namespace Tests\Feature\Clothing;

use App\Domain\Finance\Services\RefundRequestService;
use App\Models\CategoryEvent;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\ClothingSize;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use App\Services\Clothing\ClothingPayfastRefundRecoveryService;
use App\Services\Clothing\ClothingPayfastRefundService;
use App\Services\Payfast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClothingPayfastRefundRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Http::preventStrayRequests();
        $this->app->singleton(Payfast::class, fn () => new Payfast);
    }

    protected function tearDown(): void
    {
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_exact_395_recovery_is_previewed_audited_and_idempotent_without_moving_money(): void
    {
        [$actor, $payer, $order, $refund] = $this->fixture();
        $this->fakeProvider();
        $original = (array) DB::table('clothing_provider_refund_attempts')->first();
        $service = app(ClothingPayfastRefundRecoveryService::class);
        $preview = $service->preview($refund, $actor);
        $this->assertSame('395.00', $preview['refund_net']);
        $this->assertFalse($preview['applied']);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['id' => $original['id'], 'status' => 'uncertain']);
        $this->assertSame(0, DB::table('activity_log')->where('description', 'Clothing PayFast uncertain attempt reconciled for retry')->count());
        $applied = $service->apply($refund, $actor, $preview['confirmation_token']);
        $this->assertTrue($applied['applied']);
        $this->assertFalse($applied['refund_dispatched']);
        $this->assertFalse($applied['wallet_credited']);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['id' => $original['id'], 'status' => 'reconciled_ready',
            'created_at' => $original['created_at'], 'requested_by' => $actor->id, 'amount' => '395.00', 'confirmed_at' => null]);
        $this->assertTrue($service->apply($refund, $actor, $preview['confirmation_token'])['already_applied']);
        Http::assertSentCount(4);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        Http::assertSent(fn ($request) => $request->hasHeader('merchant-id') && $request->hasHeader('signature') && $request->hasHeader('timestamp'));
        $audit = DB::table('activity_log')->where('description', 'Clothing PayFast uncertain attempt reconciled for retry')->sole();
        $properties = json_decode($audit->properties, true);
        $this->assertSame($original, $properties['original_snapshot']['attempt']);
        $this->assertSame(101000, $properties['provider_evidence']['available_cents']);
        $this->assertSame($actor->id, $audit->causer_id);
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->assertSame(0, $payer->wallet()->count());
        $this->assertSame(1, $order->fresh()->pay_status);
    }

    public function test_reconciled_refund_can_finish_exactly_once_after_fresh_evidence_and_required_bank_details(): void
    {
        [$actor, , $order, $refund] = $this->fixture();
        $this->fakeProvider();
        $service = app(ClothingPayfastRefundRecoveryService::class);
        $service->apply($refund, $actor, $service->preview($refund, $actor)['confirmation_token']);
        $dispatch = app(ClothingPayfastRefundService::class);
        // A new preflight failure preserves recovery provenance for the next retry.
        $this->reject(fn () => $dispatch->dispatch($refund, $actor));
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'not_dispatched']);
        $dispatch->dispatch($refund, $actor, $this->bank());
        $dispatch->dispatch($refund->fresh(), $actor);
        $this->assertSame('completed', $refund->fresh()->refund_status);
        $posts = Http::recorded(fn ($request) => $request->method() === 'POST')->values();
        $this->assertCount(1, $posts);
        $this->assertSame(39500, $posts[0][0]['amount']);
        $this->assertSame('0123456789', $posts[0][0]['bank_account_number']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 1);
        $this->assertStringNotContainsString('0123456789', json_encode(DB::table('activity_log')->get()));
    }

    public function test_failed_changed_partial_or_unknown_provider_history_never_unlocks_an_attempt(): void
    {
        [$actor, , , $refund] = $this->fixture();
        $history = $this->history();
        $query = $this->providerQuery();
        foreach (['http_failure', 'envelope_failure', 'partial_balance', 'refund_transaction', 'unknown_transaction', 'changed_query', 'bad_cents'] as $case) {
            $badHistory = $history;
            $badQuery = $query;
            $status = 200;
            if ($case === 'http_failure') $status = 503;
            if ($case === 'envelope_failure') $badHistory['status'] = 'failed';
            if ($case === 'partial_balance') $badHistory['data']['response']['available_balance'] = 61500;
            if ($case === 'refund_transaction') $badHistory['data']['response']['transactions'][] = ['type' => 'Funds Received (Refund)', 'amount' => '-39500', 'date' => now()->toDateTimeString()];
            if ($case === 'unknown_transaction') $badHistory['data']['response']['transactions'][0]['type'] = 'Pending refund';
            if ($case === 'changed_query') $badQuery['amount_available_for_refund'] = 61500;
            if ($case === 'bad_cents') $badHistory['data']['response']['available_balance'] = '101000.99';
            $this->fakeProvider($badHistory, $badQuery, $status);
            $this->reject(fn () => app(ClothingPayfastRefundRecoveryService::class)->preview($refund, $actor));
            $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'uncertain']);
        }
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_expired_confirmation_or_changed_local_snapshot_cannot_apply(): void
    {
        [$actor, , , $refund] = $this->fixture();
        $this->fakeProvider();
        $service = app(ClothingPayfastRefundRecoveryService::class);
        $preview = $service->preview($refund, $actor);
        $this->travel(11)->minutes();
        $this->reject(fn () => $service->apply($refund, $actor, $preview['confirmation_token']));
        $this->travelBack();
        $preview = $service->preview($refund, $actor);
        DB::table('clothing_refunds')->where('id', $refund->id)->update(['refund_gross' => '390.00', 'refund_net' => '390.00']);
        DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $refund->id)->update(['amount' => '390.00']);
        $this->reject(fn () => $service->apply($refund->fresh(), $actor, $preview['confirmation_token']));
        Http::assertSentCount(4);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'uncertain']);
    }

    public function test_new_external_refund_after_recovery_blocks_retry_without_another_post(): void
    {
        [$actor, , , $refund] = $this->fixture();
        $this->fakeProvider();
        $service = app(ClothingPayfastRefundRecoveryService::class);
        $service->apply($refund, $actor, $service->preview($refund, $actor)['confirmation_token']);
        $history = $this->history();
        $history['data']['response']['available_balance'] = 61500;
        $history['data']['response']['transactions'][] = ['amount' => '-39500', 'type' => 'Funds Received (Refund)', 'date' => now()->toDateTimeString()];
        $this->fakeProvider($history);
        $this->reject(fn () => app(ClothingPayfastRefundService::class)->dispatch($refund, $actor, $this->bank()));
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'uncertain']);
        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_last_preflight_balance_change_after_recovery_checks_still_blocks_post(): void
    {
        [$actor, , , $refund] = $this->fixture();
        $this->fakeProvider();
        $service = app(ClothingPayfastRefundRecoveryService::class);
        $service->apply($refund, $actor, $service->preview($refund, $actor)['confirmation_token']);
        $queries = 0;
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use (&$queries) {
            if (str_contains($request->url(), '/query/')) {
                $query = $this->providerQuery();
                if (++$queries > 1) $query['amount_available_for_refund'] = 61500;
                return Http::response($query);
            }
            return Http::response($this->history());
        });
        $this->reject(fn () => app(ClothingPayfastRefundService::class)->dispatch($refund, $actor, $this->bank()));
        $this->assertSame(2, $queries);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_missing_recovery_audit_cannot_be_used_to_dispatch(): void
    {
        [$actor, , , $refund] = $this->fixture();
        $this->fakeProvider();
        DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $refund->id)->update(['status' => 'reconciled_ready']);
        $this->reject(fn () => app(ClothingPayfastRefundService::class)->dispatch($refund, $actor, $this->bank()));
        Http::assertNothingSent();
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'uncertain']);
    }

    public function test_recovered_retry_rejects_changed_attempt_identity_or_sandbox_mode(): void
    {
        [$actor, , , $refund] = $this->fixture();
        $this->fakeProvider();
        $service = app(ClothingPayfastRefundRecoveryService::class);
        $service->apply($refund, $actor, $service->preview($refund, $actor)['confirmation_token']);
        app(Payfast::class)->mode = 'sandbox';
        $this->reject(fn () => app(ClothingPayfastRefundService::class)->dispatch($refund, $actor, $this->bank()));
        app(Payfast::class)->mode = 'live';
        DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $refund->id)->update(['status' => 'reconciled_ready', 'requested_by' => $actor->id + 1]);
        $this->reject(fn () => app(ClothingPayfastRefundService::class)->dispatch($refund, $actor, $this->bank()));
        Http::assertSentCount(4);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_recent_attempt_wallet_credit_or_other_claim_prevents_recovery(): void
    {
        [$actor, $payer, $order, $refund] = $this->fixture();
        Http::fake();
        $service = app(ClothingPayfastRefundRecoveryService::class);
        DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $refund->id)->update(['updated_at' => now()]);
        $this->reject(fn () => $service->preview($refund, $actor));
        DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $refund->id)->update(['updated_at' => now()->subHour()]);
        $wallet = $payer->wallet()->create();
        $credit = DB::table('wallet_transactions')->insertGetId(['wallet_id' => $wallet->id, 'type' => 'credit', 'amount' => '395.00', 'source_type' => 'manual', 'source_id' => 123,
            'created_at' => now(), 'updated_at' => now()]);
        $this->reject(fn () => $service->preview($refund, $actor));
        DB::table('wallet_transactions')->where('id', $credit)->delete();
        // Seed a conflicting historical claim; normal requests already reject it.
        DB::table('clothing_refunds')->insert(['clothing_order_id' => $order->id, 'event_id' => $order->event_id,
            'payer_id' => $payer->id, 'requested_by' => $actor->id, 'request_token' => (string) str()->uuid(),
            'request_fingerprint' => hash('sha256', 'conflicting-history'), 'refund_method' => 'payfast', 'refund_status' => 'pending',
            'refund_gross' => '10.01', 'refund_fee' => '0.00', 'refund_net' => '10.01', 'reason' => 'Another pending claim',
            'created_at' => now(), 'updated_at' => now()]);
        $this->reject(fn () => $service->preview($refund, $actor));
        Http::assertNothingSent();
    }

    public function test_recovery_preview_requires_a_super_user_and_cli_is_read_only_by_default(): void
    {
        [$actor, $payer, $order, $refund] = $this->fixture();
        $this->fakeProvider();
        try {
            app(ClothingPayfastRefundRecoveryService::class)->preview($refund, $payer);
            $this->fail('Non-super-user recovery was permitted.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->artisan('clothing:recover-payfast-refund', ['order' => $order->id, 'refund' => $refund->id, '--actor' => $actor->id])
            ->expectsOutputToContain('Preview only. No records changed.')->assertSuccessful();
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'uncertain']);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    private function reject(callable $action): void
    {
        try {
            $action();
            $this->fail('Unsafe recovery or retry was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
    }

    private function fakeProvider(?array $history = null, ?array $query = null, int $historyStatus = 200): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(fn ($request) => $request->method() === 'POST'
            ? Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => true]])
            : (str_contains($request->url(), '/query/') ? Http::response($query ?? $this->providerQuery()) : Http::response($history ?? $this->history(), $historyStatus)));
    }

    private function history(): array
    {
        return ['code' => 200, 'status' => 'success', 'data' => ['response' => ['available_balance' => 101000,
            'transactions' => [['amount' => 101000, 'date' => '2026-09-18 10:04:58', 'type' => 'Funds Received']]]]];
    }

    private function providerQuery(): array
    {
        return ['status' => 'REFUNDABLE', 'amount_original' => 101000, 'amount_available_for_refund' => 101000,
            'refund_full' => ['method' => 'BANK_PAYOUT'], 'refund_partial' => ['method' => 'BANK_PAYOUT'],
            'bank_names' => [['bank_name' => 'FNB', 'label' => 'FNB']]];
    }

    private function bank(): array
    {
        return ['account_holder' => 'Original Payer', 'bank_name' => 'FNB', 'branch_code' => '250655',
            'account_number' => '0123456789', 'account_type' => 'current'];
    }

    private function fixture(): array
    {
        $actor = User::factory()->create()->assignRole(Role::findOrCreate('super-user', 'web'));
        $payer = User::factory()->create();
        $event = Event::factory()->create();
        $region = TeamRegion::create(['region_name' => 'Recovery test region']);
        DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id]);
        $catalogue = ClothingItemType::create(['region_id' => $region->id, 'item_type_name' => 'Refund shirt', 'price' => 1010]);
        $size = ClothingSize::create(['item_type' => $catalogue->id, 'size' => 'Medium']);
        $order = ClothingOrder::create(['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => Player::factory()->create()->id,
            'user_id' => $payer->id, 'pay_status' => 1, 'status' => 'completed', 'subtotal' => 1010, 'payfast_fee' => 0,
            'total' => 1010, 'amount_paid' => 1010, 'payfast_paid' => true, 'payfast_pf_payment_id' => 'RECOVERY-'.$payer->id,
            'payfast_amount_due' => 1010, 'wallet_reserved' => 0, 'wallet_debited' => false, 'paid_at' => now()->subMonth(), 'request_token' => (string) str()->uuid()]);
        $item = $order->items()->create(['clothing_order_item_id' => $catalogue->id, 'clothing_item_size' => $size->id,
            'qty' => 1, 'price' => 1010, 'line_total' => 1010, 'item_name' => 'Refund shirt', 'size_name' => 'Medium']);
        $refund = app(RefundRequestService::class)->requestClothingRefund($event, $order, $actor, ['request_token' => (string) str()->uuid(),
            'mode' => 'amount', 'item_id' => $item->id, 'amount' => '395.00', 'method' => 'payfast', 'reason' => 'Asked by admin']);
        DB::table('clothing_provider_refund_attempts')->insert(['clothing_refund_id' => $refund->id, 'clothing_order_id' => $order->id,
            'pf_payment_id' => $order->payfast_pf_payment_id, 'amount' => '395.00', 'requested_by' => $actor->id,
            'status' => 'uncertain', 'created_at' => now()->subHour(), 'updated_at' => now()->subHour(), 'confirmed_at' => null]);
        return [$actor, $payer, $order, $refund];
    }
}
