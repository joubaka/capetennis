<?php

namespace Tests\Feature;

use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Http\Middleware\EnsureAgreementAccepted;
use App\Http\Middleware\EnsurePlayerProfileUpdated;
use App\Domain\Payments\Services\RegistrationPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationWalletCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_wallet_reduces_existing_orders_payfast_amount_without_creating_an_order(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => 259.38,
            'source_type' => 'test_seed',
            'source_id' => 0,
            'meta' => [],
        ]);

        $order = RegistrationOrder::create([
            'user_id' => $user->id,
            'wallet_reserved' => 0,
            'wallet_debited' => false,
            'payfast_paid' => false,
            'payfast_amount_due' => 570,
            'total_fee' => 570,
            'pay_status' => false,
        ]);
        $item = new RegistrationOrderItems();
        $item->forceFill(['order_id' => $order->id, 'item_price' => 570])->save();

        $response = $this->actingAs($user)->postJson(route('registration.hybrid.apply-wallet'), [
            'order_id' => $order->id,
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'wallet_applied' => 259.38,
            'payfast_due' => 310.62,
            'wallet_covers_all' => false,
        ]);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseHas('registration_orders', [
            'id' => $order->id,
            'wallet_reserved' => 259.38,
            'payfast_amount_due' => 310.62,
        ]);

        $checkout = $this->withoutMiddleware([
            EnsureAgreementAccepted::class,
            EnsurePlayerProfileUpdated::class,
        ])->actingAs($user)->get(
            route('registration.checkout', ['order' => $order->id])
        );

        $checkout->assertOk()
            ->assertSee('Pay R 310.62 with PayFast', false)
            ->assertSee('name="amount" value="310.62"', false)
            ->assertSee('href="'.route('registration.hybrid.cancel', ['orderId' => $order->id]).'"', false)
            ->assertDontSee('href="'.route('pay.now.payfast').'"', false);
        $this->assertDatabaseCount('registration_orders', 1);
    }

    public function test_existing_order_checkout_rejects_another_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = RegistrationOrder::create([
            'user_id' => $owner->id,
            'payfast_amount_due' => 570,
            'pay_status' => false,
        ]);

        $this->withoutMiddleware([
            EnsureAgreementAccepted::class,
            EnsurePlayerProfileUpdated::class,
        ])->actingAs($otherUser)
            ->get(route('registration.checkout', ['order' => $order->id]))
            ->assertForbidden();
    }

    public function test_wallet_completion_rejects_an_order_with_a_payfast_remainder(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => 50,
            'source_type' => 'test_seed',
            'source_id' => 91,
            'meta' => [],
        ]);
        $order = RegistrationOrder::create([
            'user_id' => $user->id,
            'wallet_reserved' => 50,
            'payfast_amount_due' => 50,
            'wallet_debited' => false,
            'payfast_paid' => false,
            'pay_status' => false,
        ]);
        (new RegistrationOrderItems())->forceFill([
            'order_id' => $order->id,
            'item_price' => 100,
        ])->save();

        $this->actingAs($user)
            ->post(route('registration.hybrid.complete', ['orderId' => $order->id]))
            ->assertRedirect(route('registration.checkout', $order));

        $this->assertFalse((bool) $order->fresh()->pay_status);
        $this->assertFalse((bool) $order->fresh()->wallet_debited);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_wallet_completion_is_idempotent_and_debits_exactly_once(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => 100,
            'source_type' => 'test_seed',
            'source_id' => 92,
            'meta' => [],
        ]);
        $order = RegistrationOrder::create([
            'user_id' => $user->id,
            'wallet_reserved' => 100,
            'payfast_amount_due' => 0,
            'wallet_debited' => false,
            'payfast_paid' => false,
            'pay_status' => false,
        ]);
        (new RegistrationOrderItems())->forceFill([
            'order_id' => $order->id,
            'item_price' => 100,
        ])->save();

        $this->actingAs($user)
            ->post(route('registration.hybrid.complete', ['orderId' => $order->id]))
            ->assertRedirect();
        $this->post(route('registration.hybrid.complete', ['orderId' => $order->id]))
            ->assertRedirect();

        $this->assertTrue((bool) $order->fresh()->pay_status);
        $this->assertTrue((bool) $order->fresh()->wallet_debited);
        $this->assertFalse((bool) $order->fresh()->payfast_paid);
        $this->assertSame('wallet', $order->fresh()->payment_method);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertEquals(0.0, $wallet->fresh()->balance);
    }

    public function test_registration_success_rejects_another_users_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = RegistrationOrder::create(['user_id' => $owner->id, 'pay_status' => true]);

        $this->actingAs($other)
            ->get(route('frontend.registration.success', ['order' => $order->id]))
            ->assertForbidden();
    }

    public function test_payfast_finalization_recovers_missing_legacy_due_from_locked_item_totals(): void
    {
        $user = User::factory()->create();
        $order = RegistrationOrder::create([
            'user_id' => $user->id,
            'wallet_reserved' => 0,
            'payfast_amount_due' => 0,
            'wallet_debited' => false,
            'payfast_paid' => false,
            'pay_status' => false,
        ]);
        (new RegistrationOrderItems())->forceFill([
            'order_id' => $order->id,
            'item_price' => 100,
        ])->save();

        $finalized = app(RegistrationPaymentService::class)->finalizePayfastPayment($order, 100, [
            'pf_payment_id' => 'PF-LEGACY-DUE',
            'payment_method' => 'payfast',
        ]);

        $this->assertTrue((bool) $finalized->pay_status);
        $this->assertTrue((bool) $finalized->payfast_paid);
        $this->assertSame(100.0, (float) $finalized->payfast_amount_due);
        $this->assertSame('PF-LEGACY-DUE', $finalized->payfast_pf_payment_id);
    }

    public function test_legacy_due_recovery_still_rejects_an_amount_not_supported_by_item_totals(): void
    {
        $user = User::factory()->create();
        $order = RegistrationOrder::create([
            'user_id' => $user->id,
            'wallet_reserved' => 0,
            'payfast_amount_due' => 0,
            'wallet_debited' => false,
            'payfast_paid' => false,
            'pay_status' => false,
        ]);
        (new RegistrationOrderItems())->forceFill([
            'order_id' => $order->id,
            'item_price' => 100,
        ])->save();

        try {
            app(RegistrationPaymentService::class)->finalizePayfastPayment($order, 90, [
                'pf_payment_id' => 'PF-WRONG-AMOUNT',
                'payment_method' => 'payfast',
            ]);
            $this->fail('An amount not supported by the order item totals must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Payment amount mismatch', $exception->getMessage());
        }

        $this->assertFalse((bool) $order->fresh()->pay_status);
        $this->assertFalse((bool) $order->fresh()->payfast_paid);
        $this->assertSame(0.0, (float) $order->fresh()->payfast_amount_due);
    }

    public function test_checkout_cancel_returns_to_the_orders_event_and_releases_reservation(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $categoryEvent = CategoryEvent::factory()->for($event)->create();
        $order = RegistrationOrder::create([
            'user_id' => $user->id,
            'wallet_reserved' => 125,
            'payfast_amount_due' => 25,
            'pay_status' => false,
            'payfast_paid' => false,
            'wallet_debited' => false,
        ]);
        $item = new RegistrationOrderItems();
        $item->forceFill([
            'order_id' => $order->id,
            'category_event_id' => $categoryEvent->id,
            'item_price' => 150,
        ])->save();

        $this->actingAs($user)
            ->get(route('registration.hybrid.cancel', ['orderId' => $order->id]))
            ->assertRedirect(route('events.show', ['event' => $event->id]));

        $this->assertDatabaseHas('registration_orders', [
            'id' => $order->id,
            'wallet_reserved' => 0,
            'payfast_amount_due' => 0,
        ]);
    }

    public function test_events_index_redirects_to_the_working_home_page(): void
    {
        $this->get(route('events.index'))
            ->assertRedirect(route('home'));
    }
}
