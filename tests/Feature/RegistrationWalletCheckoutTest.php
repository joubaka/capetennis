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
