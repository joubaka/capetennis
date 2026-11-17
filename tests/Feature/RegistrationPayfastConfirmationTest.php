<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\PaymentTransactionService;
use App\Events\PaymentCompleted;
use App\Models\{CategoryEvent, Event, Player, Registration, RegistrationOrder, RegistrationOrderItems, Transaction, User};
use Illuminate\Database\DeadlockException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\{DB, Event as Events};
use Tests\TestCase;

class RegistrationPayfastConfirmationTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        $this->assertTrue($this->usesIsolatedPaymentDatabase(), 'Payment regressions require isolated ct_testing MySQL or in-memory SQLite.');
        // Each test application owns a fresh in-memory SQLite connection.
        if (DB::connection()->getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:') {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        }
    }

    protected function tearDown(): void
    {
        try {
            // Failed setup against an unsafe connection must never truncate it.
            if ($this->app && $this->app->bound('db') && $this->usesIsolatedPaymentDatabase()) {
                try {
                    $this->truncateTablesForAllConnections();
                } finally {
                    if (DB::connection()->getDriverName() === 'sqlite') \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    private function usesIsolatedPaymentDatabase(): bool
    {
        $connection = DB::connection();
        return ($connection->getDriverName() === 'mysql' && $connection->getDatabaseName() === 'ct_testing')
            || ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:');
    }

    public function test_receipt_deadlock_retries_all_settlement_and_replay_is_idempotent(): void
    {
        [$order, $payload] = $this->scenario();
        $recorder = $this->recorder(1);
        $this->sendCallback($payload)->assertOk();
        $this->sendCallback(array_replace($payload, ['amount_gross' => '159.99']))->assertStatus(400);
        $this->sendCallback(array_replace($payload, ['pf_payment_id' => 'PF-SECOND-CHARGE']))->assertStatus(400);
        $this->assertSame(2, $recorder->attempts);
        $this->sendCallback($payload)->assertOk();
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseHas('transactions_pf', ['pf_payment_id' => 'PF-INDIVIDUAL-RETRY', 'amount_gross' => 160, 'custom_int5' => $order->id]);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'debit', 'amount' => 40, 'source_id' => $order->id]);
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseHas('category_event_registrations', ['registration_id' => $order->items->first()->registration_id, 'payment_status_id' => 1]);
        Events::assertDispatchedTimes(PaymentCompleted::class, 1);
    }

    public function test_exhausted_deadlocks_request_retry_without_partial_payment(): void
    {
        [$order, $payload] = $this->scenario();
        $recorder = $this->recorder(3);
        $this->sendCallback($payload)->assertStatus(503);
        $this->assertSame(3, $recorder->attempts);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertFalse($order->fresh()->wallet_debited);
        $this->assertSame(40.0, $order->fresh()->wallet_reserved);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('category_event_registrations', ['registration_id' => $order->items->first()->registration_id, 'payment_status_id' => 0]);
        Events::assertNotDispatched(PaymentCompleted::class);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cartSizes')]
    public function test_signed_multi_item_payment_settles_every_entry_once_and_rolls_back_as_one_unit(int $size): void
    {
        [$order, $payload] = $this->scenario();
        $anchor = $order->items()->first();
        $category = $anchor->category_event;
        $category->event->forceFill(['cape_tennis_fee' => 15])->save();
        for ($index = 1; $index < $size; $index++) {
            $player = Player::factory()->create();
            $registration = Registration::create([]);
            $registration->players()->attach($player);
            $registration->categoryEvents()->attach($category, ['user_id' => $order->user_id, 'payment_status_id' => 0, 'status' => 'active']);
            (new RegistrationOrderItems())->forceFill(['order_id' => $order->id, 'registration_id' => $registration->id,
                'player_id' => $player->id, 'category_event_id' => $category->id, 'item_price' => 200])->save();
        }
        $gross = $size * 200 - 40;
        $order->update(['total_fee' => $size * 200, 'payfast_amount_due' => $gross]);
        $payload = array_replace($payload, ['amount_gross' => number_format($gross, 2, '.', ''),
            'amount_fee' => $size === 2 ? '-20.70' : '-35.42',
            'amount_net' => number_format($gross - ($size === 2 ? 20.70 : 35.42), 2, '.', '')]);
        $this->recorder(3);
        $this->sendCallback($payload)->assertStatus(503);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame($size, \App\Models\CategoryEventRegistration::where('payment_status_id', 0)->count());
        Events::assertNotDispatched(PaymentCompleted::class);

        $this->recorder(0);
        $this->sendCallback($payload)->assertOk();
        $this->sendCallback($payload)->assertOk();
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('registration_order_items', $size);
        $this->assertDatabaseCount('registrations', $size);
        $this->assertDatabaseCount('category_event_registrations', $size);
        $this->assertSame($size, \App\Models\CategoryEventRegistration::where('payment_status_id', 1)->where('status', 'active')->count());
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $receipt = Transaction::sole();
        $this->assertSame((float) $gross, (float) $receipt->amount_gross);
        $this->assertSame((float) (15 * $size), (float) $receipt->cape_tennis_fee);
        // Canonical receipts currently use the configured fee estimate, independently of the signed provider fee.
        $estimatedFee = \App\Models\SiteSetting::calculatePayfastFee($gross);
        $this->assertSame((float) $estimatedFee, (float) $receipt->amount_fee);
        $this->assertSame(round($gross - $estimatedFee, 2), (float) $receipt->amount_net);
        Events::assertDispatchedTimes(PaymentCompleted::class, 1);
    }

    public static function cartSizes(): array
    {
        return [[2], [3]];
    }

    public function test_transport_records_critical_audit_failure_without_settling_a_valid_callback(): void
    {
        [$order, $payload] = $this->scenario();
        config(['audit.enabled' => true]);
        $observer = new class extends \App\Http\Middleware\PayfastCallbackTelemetry {
            public array $records = [];
            protected function write(string $message, array $context): void { $this->records[] = [$message, $context]; }
        };
        $this->app->instance(\App\Http\Middleware\PayfastCallbackTelemetry::class, $observer);
        $this->mock(\App\Support\Audit\AuditWriter::class, function ($mock) {
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('audit unavailable'));
        });
        $this->sendCallback($payload)->assertStatus(500);
        $this->assertCount(2, $observer->records);
        $this->assertSame('payfast.callback.ingress', $observer->records[0][0]);
        $this->assertSame(500, $observer->records[1][1]['http_status']);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('category_event_registrations', ['registration_id' => $order->items->first()->registration_id, 'payment_status_id' => 0]);
        Events::assertNotDispatched(PaymentCompleted::class);
    }

    public function test_rendered_payment_form_signs_the_canonical_callback_url(): void
    {
        [$order] = $this->scenario();
        config(['app.url' => 'https://canonical.example', 'services.payfast.notify_url' => '/notify']);
        $this->actingAs($order->user);
        $payfast = new \App\Services\Payfast();
        $html = view('frontend.payfast.pay_now', [
            'payfast' => $payfast, 'amount' => $order->payfast_amount_due, 'orderId' => $order->id,
            'return_url' => 'https://browser.example/return', 'cancel_url' => 'https://browser.example/cancel',
            'notify_url' => \App\Support\PayfastCallbackUrl::for('notify'),
        ])->render();
        preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)">/', $html, $matches, PREG_SET_ORDER);
        $fields = [];
        foreach ($matches as $match) $fields[$match[1]] = html_entity_decode($match[2], ENT_QUOTES);
        $signature = $fields['signature'];
        unset($fields['signature']);
        $this->assertSame('https://canonical.example/notify', $fields['notify_url']);
        $this->assertSame($payfast->generateFormSignature($fields), $signature);
        $fields['notify_url'] = 'https://browser.example/notify';
        $this->assertNotSame($payfast->generateFormSignature($fields), $signature);
    }

    public function test_invalid_signature_status_identity_and_amount_do_not_settle(): void
    {
        [$order, $payload] = $this->scenario();
        $this->call('POST', route('notify'), $payload)->assertStatus(400);
        $this->sendCallback(array_replace($payload, ['payment_status' => 'PENDING']))->assertOk();
        foreach (['custom_int4' => $order->user_id.'garbage', 'custom_int3' => $payload['custom_int3'] + 1,
            'custom_int2' => $payload['custom_int2'] + 1, 'custom_int1' => $payload['custom_int1'] + 1,
            'amount_gross' => '159.99'] as $key => $value) {
            $this->sendCallback(array_replace($payload, [$key => $value]))->assertStatus(400);
        }
        $this->sendCallback(array_replace($payload, ['amount_gross' => '160.00junk']))->assertStatus(400);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_metadata_cannot_mix_players_and_categories_from_different_order_items(): void
    {
        [$order, $payload] = $this->scenario();
        $category = CategoryEvent::factory()->create();
        $player = Player::factory()->create();
        (new RegistrationOrderItems())->forceFill(['order_id' => $order->id,
            'player_id' => $player->id, 'category_event_id' => $category->id, 'item_price' => 0])->save();
        $this->sendCallback(array_replace($payload, ['custom_int2' => $player->id]))->assertStatus(400);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_provider_receipt_cannot_be_reassigned_to_another_order(): void
    {
        [$order, $payload] = $this->scenario();
        $this->sendCallback($payload)->assertOk();
        [$other, $otherPayload] = $this->scenario();
        $this->sendCallback($otherPayload)->assertStatus(400);
        $this->assertFalse($other->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseHas('transactions_pf', ['custom_int5' => $order->id]);
    }

    public function test_return_only_claims_success_for_paid_orders_and_never_changes_money(): void
    {
        [$order] = $this->scenario();
        $route = route('frontend.registration.success', $order);
        $this->actingAs(User::factory()->create())->get($route)->assertForbidden();
        $this->actingAs($order->user)->get($route)->assertRedirect(route('events.show', $order->items->first()->category_event->event_id))
            ->assertSessionHas('warning', fn ($message) => str_contains($message, 'not confirmed yet'))
            ->assertSessionMissing('success');
        $order->update(['status' => 'cancelled']);
        $this->get($route)->assertSessionHas('warning', fn ($message) => str_contains($message, 'before paying again'))
            ->assertSessionMissing('success');
        $order->update(['status' => 'completed', 'pay_status' => true]);
        $entry = \App\Models\CategoryEventRegistration::where('registration_id', $order->items->first()->registration_id)->sole();
        $entry->update(['payment_status_id' => 1]);
        $this->get($route)->assertSessionHas('success', 'Registration completed successfully!');
        $entry->update(['status' => 'withdrawn_refunded', 'withdrawn_at' => now(), 'refund_status' => 'completed']);
        $this->get($route)->assertSessionHas('info', fn ($message) => str_starts_with($message, 'Payment recorded.'))
            ->assertSessionMissing('success');
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    private function recorder(int $failures): PaymentTransactionService
    {
        $recorder = new class($failures) extends PaymentTransactionService {
            public int $attempts = 0;
            public function __construct(private int $failures) {}
            protected function recordPayfastTransaction(array $data): Transaction
            {
                $receipt = parent::recordPayfastTransaction($data);
                if (++$this->attempts <= $this->failures) {
                    throw new DeadlockException('Deadlock found when trying to get lock; try restarting transaction');
                }
                return $receipt;
            }
        };
        $this->app->instance(PaymentTransactionService::class, $recorder);
        return $recorder;
    }

    private function sendCallback(array $payload): \Illuminate\Testing\TestResponse
    {
        $payload['signature'] = md5(http_build_query($payload).'&passphrase=test-passphrase');
        return $this->call('POST', route('notify'), $payload, [], [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($payload));
    }

    private function scenario(): array
    {
        $connection = DB::connection();
        $this->assertTrue(
            ($connection->getDriverName() === 'mysql' && $connection->getDatabaseName() === 'ct_testing')
            || ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:'),
            'Payment regressions require isolated ct_testing MySQL or in-memory SQLite.'
        );
        Events::fake([PaymentCompleted::class]);
        config(['services.payfast.sandbox' => false, 'services.payfast.merchant_id' => 'test-merchant',
            'services.payfast.passphrase_live' => 'test-passphrase', 'services.payment_failure_alert.enabled' => false]);
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $player = Player::factory()->create();
        $payer = User::factory()->create();
        $payer->wallet()->create()->transactions()->create(['type' => 'credit', 'amount' => 40, 'source_type' => 'test']);
        $registration = Registration::create([]);
        $registration->players()->attach($player);
        $registration->categoryEvents()->attach($category, ['user_id' => $payer->id, 'payment_status_id' => 0, 'status' => 'active']);
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'status' => 'pending', 'total_fee' => 200,
            'wallet_reserved' => 40, 'payfast_amount_due' => 160, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);
        (new RegistrationOrderItems())->forceFill(['order_id' => $order->id, 'registration_id' => $registration->id,
            'player_id' => $player->id, 'category_event_id' => $category->id, 'item_price' => 200])->save();
        return [$order, ['merchant_id' => 'test-merchant', 'payment_status' => 'COMPLETE', 'pf_payment_id' => 'PF-INDIVIDUAL-RETRY',
            'amount_gross' => '160.00', 'custom_int5' => $order->id, 'custom_int4' => $payer->id,
            'custom_int3' => $event->id, 'custom_int2' => $player->id, 'custom_int1' => $category->id]];
    }
}
