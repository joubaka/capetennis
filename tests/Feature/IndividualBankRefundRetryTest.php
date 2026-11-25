<?php

namespace Tests\Feature;

use App\Models\{CategoryEventRegistration, IndividualProviderRefundAttempt, Player, RegistrationOrder, RegistrationOrderItems, Transaction, User, WalletTransaction};
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\{DB, Http, Mail};
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IndividualBankRefundRetryTest extends TestCase
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

    public static function retryCases(): array
    {
        $cases = [];
        foreach (['backend', 'frontend', 'bulk'] as $route) {
            foreach ([0, 37.50] as $fee) $cases[$route.'-'.$fee] = [$route, $fee];
        }
        return $cases;
    }

    #[DataProvider('retryCases')]
    public function test_retry_sends_approved_cents_once_and_preserves_original_payment_and_other_event(string $route, float $fee): void
    {
        [$actor, $entry, $order] = $this->scenario($fee);
        $other = CategoryEventRegistration::factory()->pendingRefund()->create(['refund_net' => 80]);
        $original = [$order->getAttributes(), $order->items()->sole()->getAttributes(), Transaction::sole()->getAttributes(), $other->fresh()->getAttributes()];
        $this->provider();
        $this->actingAs($actor);
        $this->retry($route, $entry)->assertRedirect()->assertSessionHasNoErrors();
        $this->retry($route, $entry)->assertRedirect();
        $this->assertSame('completed', $entry->fresh()->refund_status);
        $this->assertEquals(375 - $fee, $entry->fresh()->refund_net);
        $this->assertEquals($fee, $entry->fresh()->refund_fee);
        $this->assertEquals(375, $entry->fresh()->refund_gross);
        $this->assertSame($original, [$order->fresh()->getAttributes(), $order->items()->sole()->getAttributes(), Transaction::sole()->getAttributes(), $other->fresh()->getAttributes()]);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['amount'] === (int) round((375 - $fee) * 100));
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST'));
        $this->assertDatabaseCount('individual_provider_refund_attempts', 1);
        $this->assertSame('confirmed', IndividualProviderRefundAttempt::sole()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('category_event_registrations', 2);
    }

    public function test_failed_query_preserves_approved_full_amount_for_later_retry(): void
    {
        [$actor, $entry] = $this->scenario();
        Http::fake(['api.payfast.co.za/*' => Http::response('', 502)]);
        $this->actingAs($actor);
        $this->retry('backend', $entry)->assertSessionHasErrors();
        $this->assertSame('pending', $entry->fresh()->refund_status);
        $this->assertEquals(375, $entry->fresh()->refund_net);
        $this->assertDatabaseCount('individual_provider_refund_attempts', 0);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_malformed_or_unfunded_snapshot_never_queries_or_dispatches(): void
    {
        [$actor, $entry] = $this->scenario();
        $this->actingAs($actor);
        foreach ([['refund_net' => 400], ['refund_gross' => 374], ['refund_fee' => -1], ['refund_net' => 0]] as $change) {
            $entry->update(array_replace(['refund_gross' => 375, 'refund_fee' => 0, 'refund_net' => 375], $change));
            $this->retry('backend', $entry)->assertSessionHasErrors();
            $this->assertSame('pending', $entry->fresh()->refund_status);
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('individual_provider_refund_attempts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_guest_and_payer_cannot_execute_administrator_retry(): void
    {
        [, $entry] = $this->scenario();
        $this->retry('backend', $entry)->assertRedirect(route('login'));
        $this->actingAs($entry->user);
        foreach (['backend', 'frontend', 'bulk'] as $route) $this->retry($route, $entry)->assertForbidden();
        Http::assertNothingSent();
        $this->assertDatabaseCount('individual_provider_refund_attempts', 0);
    }

    public static function hybridCases(): array
    {
        return [['backend', 0], ['frontend', 37.50], ['bulk', 37.50]];
    }

    #[DataProvider('hybridCases')]
    public function test_hybrid_retry_provider_plus_wallet_equals_approved_net(string $route, float $fee): void
    {
        [$actor, $entry] = $this->scenario($fee, 100);
        $entry->user->wallet()->create();
        $this->provider(27500);
        $this->actingAs($actor);
        $this->retry($route, $entry)->assertRedirect()->assertSessionHasNoErrors();
        $this->retry($route, $entry)->assertRedirect();
        $this->assertSame('completed', $entry->fresh()->refund_status);
        $this->assertEquals(375 - $fee, $entry->fresh()->refund_net);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertEquals(100, WalletTransaction::sole()->amount);
        $this->assertSame($entry->user->wallet->id, WalletTransaction::sole()->wallet_id);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['amount'] === (int) round((275 - $fee) * 100));
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }

    public function test_hybrid_without_payer_wallet_fails_before_provider_and_manual_bulk_remains_supported(): void
    {
        [$actor, $entry] = $this->scenario(0, 100);
        $this->actingAs($actor);
        $this->retry('backend', $entry)->assertSessionHasErrors();
        Http::assertNothingSent();
        $manual = CategoryEventRegistration::factory()->withdrawn()->create(['refund_method' => 'bank', 'refund_status' => 'pending', 'refund_gross' => 50, 'refund_fee' => 0, 'refund_net' => 50]);
        $this->retry('bulk', $manual)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $manual->fresh()->refund_status);
        $this->assertSame('pending', $entry->fresh()->refund_status);
        Http::assertNothingSent();
    }

    private function retry(string $route, CategoryEventRegistration $entry)
    {
        return match ($route) {
            'backend' => $this->post(route('admin.refunds.bank.complete', $entry), ['amount' => 1]),
            'frontend' => $this->post(route('admin.registration.refunds.bank.complete', $entry), ['amount' => 1]),
            'bulk' => $this->post(route('admin.refunds.bank.bulk-complete'), ['registration_ids' => [$entry->id], 'amount' => 1]),
        };
    }

    private function provider(int $available = 37500): void
    {
        Http::fake(['api.payfast.co.za/refunds/*' => fn ($request) => $request->method() === 'GET'
            ? Http::response(['data' => ['response' => ['status' => 'REFUNDABLE', 'amount_available_for_refund' => $available,
                'refund_full' => ['method' => 'PAYMENT_SOURCE'], 'refund_partial' => ['method' => 'PAYMENT_SOURCE']]]], 200)
            : Http::response(['status' => 'success'], 200)]);
    }

    private function scenario(float $fee = 0, float $wallet = 0): array
    {
        Mail::fake(); Http::preventStrayRequests(); config(['services.payment_failure_alert.enabled' => false]);
        Role::findOrCreate('super-user', 'web');
        $actor = User::factory()->create()->assignRole('super-user');
        $payer = User::factory()->create();
        $entry = CategoryEventRegistration::factory()->pendingRefund()->create(['user_id' => $payer->id,
            'payment_method' => $wallet ? 'hybrid' : 'payfast', 'pf_transaction_id' => 'PF-RETRY',
            'refund_gross' => 375, 'refund_fee' => $fee, 'refund_net' => 375 - $fee]);
        $player = Player::factory()->create(); $entry->registration->players()->attach($player->id);
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 375, 'pay_status' => true, 'payfast_paid' => true,
            'payfast_amount_due' => 375 - $wallet, 'payfast_pf_payment_id' => 'PF-RETRY', 'payment_method' => $wallet ? 'hybrid' : 'payfast',
            'wallet_reserved' => $wallet, 'wallet_debited' => $wallet > 0, 'status' => 'completed']);
        RegistrationOrderItems::forceCreate(['order_id' => $order->id, 'registration_id' => $entry->registration_id, 'category_event_id' => $entry->category_event_id,
            'player_id' => $player->id, 'user_id' => $payer->id, 'item_price' => 375]);
        Transaction::forceCreate(['pf_payment_id' => 'PF-RETRY', 'custom_int5' => $order->id, 'custom_int4' => $payer->id,
            'custom_int1' => $entry->category_event_id, 'custom_int2' => $player->id, 'custom_int3' => $entry->categoryEvent->event_id,
            'amount_gross' => 375 - $wallet, 'amount_fee' => 16.10, 'amount_net' => 358.90 - $wallet]);
        return [$actor, $entry->fresh(), $order->fresh()];
    }
}
