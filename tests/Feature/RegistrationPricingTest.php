<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAgreementAccepted;
use App\Http\Middleware\EnsurePlayerProfileUpdated;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Player;
use App\Models\RegistrationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RegistrationPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_category_fee_inherits_event_fee_and_requires_payment(): void
    {
        [, $categoryEvent] = $this->individualEvent(300, null);
        $user = User::factory()->create();
        $player = Player::factory()->create();

        $response = $this->register($user, [$player], [$categoryEvent]);

        $response->assertOk()->assertViewIs('frontend.payfast.check_out');
        $order = RegistrationOrder::query()->sole();
        $this->assertSame(300.0, (float) $order->total_fee);
        $this->assertSame(300.0, (float) $order->payfast_amount_due);
        $this->assertFalse((bool) $order->pay_status);
        $this->assertDatabaseHas('registration_order_items', [
            'order_id' => $order->id,
            'category_event_id' => $categoryEvent->id,
            'item_price' => 300,
        ]);
        $this->assertDatabaseHas('category_event_registrations', [
            'category_event_id' => $categoryEvent->id,
            'payment_status_id' => 0,
        ]);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('registration_order_items', 1);
    }

    public function test_explicit_zero_category_fee_remains_free(): void
    {
        [$event, $categoryEvent] = $this->individualEvent(300, 0);
        $user = User::factory()->create();
        $player = Player::factory()->create();
        Mail::fake();

        $this->register($user, [$player], [$categoryEvent])
            ->assertRedirect(route('frontend.registration.success', RegistrationOrder::query()->sole()));

        $order = RegistrationOrder::query()->sole();
        $this->assertSame(0.0, (float) $order->total_fee);
        $this->assertSame('free', $order->payment_method);
        $this->assertTrue((bool) $order->pay_status);
        $this->assertDatabaseHas('category_event_registrations', [
            'category_event_id' => $categoryEvent->id,
            'payment_status_id' => 1,
        ]);
    }

    public function test_positive_category_fee_overrides_event_fee(): void
    {
        [, $categoryEvent] = $this->individualEvent(300, 175);
        $user = User::factory()->create();
        $player = Player::factory()->create();

        $this->register($user, [$player], [$categoryEvent])
            ->assertOk()
            ->assertViewIs('frontend.payfast.check_out');

        $order = RegistrationOrder::query()->sole();
        $this->assertSame(175.0, (float) $order->total_fee);
        $this->assertSame(175.0, (float) $order->payfast_amount_due);
        $this->assertFalse((bool) $order->pay_status);
    }

    public function test_multiple_players_use_exact_resolved_item_total(): void
    {
        [, $inheritedCategory] = $this->individualEvent(300, null);
        $overrideCategory = CategoryEvent::factory()->create([
            'event_id' => $inheritedCategory->event_id,
            'entry_fee' => 125.50,
        ]);
        $user = User::factory()->create();
        $players = Player::factory()->count(2)->create();

        $this->register($user, $players->all(), [$inheritedCategory, $overrideCategory])
            ->assertOk()
            ->assertViewIs('frontend.payfast.check_out');

        $order = RegistrationOrder::query()->sole();
        $this->assertSame(425.50, (float) $order->total_fee);
        $this->assertSame(425.50, (float) $order->payfast_amount_due);
        $this->assertFalse((bool) $order->pay_status);
        $this->assertSame(
            [125.50, 300.0],
            $order->items()->orderBy('item_price')->pluck('item_price')->map(fn ($price) => (float) $price)->all()
        );
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('registration_order_items', 2);
    }

    private function register(User $user, array $players, array $categoryEvents): TestResponse
    {
        return $this->withoutMiddleware([
            EnsureAgreementAccepted::class,
            EnsurePlayerProfileUpdated::class,
        ])->actingAs($user)->post(route('pay.now.payfast'), [
            'terms_accepted' => '1',
            'player' => array_map(fn (Player $player) => $player->id, $players),
            'category' => array_map(fn (CategoryEvent $categoryEvent) => $categoryEvent->id, $categoryEvents),
        ]);
    }

    private function individualEvent(float $eventFee, ?float $categoryFee): array
    {
        $eventTypeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Individual',
            'type' => EventType::INDIVIDUAL,
            'code' => 'registration-pricing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $event = Event::factory()->create([
            'eventType' => $eventTypeId,
            'entryFee' => $eventFee,
            'status' => 'open',
            'published' => true,
            'signUp' => true,
        ]);
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'entry_fee' => $categoryFee,
        ]);

        return [$event, $categoryEvent];
    }
}
