<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAgreementAccepted;
use App\Http\Middleware\EnsurePlayerProfileUpdated;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventType;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MastersRegistrationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_event_page_does_not_offer_generic_signup_for_masters(): void
    {
        [$event] = $this->mastersEvent();

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertDontSee(route('register.register', $event), false);
    }

    public function test_generic_masters_checkout_page_redirects_to_invitation_list(): void
    {
        [$event, , $user] = $this->mastersEvent();

        $this->withoutMiddleware([
                EnsureAgreementAccepted::class,
                EnsurePlayerProfileUpdated::class,
            ])
            ->actingAs($user)
            ->get(route('register.register', $event))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors('msg');
    }

    public function test_forged_generic_masters_registration_cannot_create_an_order(): void
    {
        [$event, $categoryEvent, $user, $player] = $this->mastersEvent();

        $this->withoutMiddleware([
                EnsureAgreementAccepted::class,
                EnsurePlayerProfileUpdated::class,
            ])
            ->actingAs($user)
            ->from(route('events.show', $event))
            ->post(route('pay.now.payfast'), $this->registrationPayload($player, $categoryEvent))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors('msg');

        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertDatabaseCount('registration_order_items', 0);
    }

    public function test_user_cannot_register_an_unowned_player_for_a_normal_event(): void
    {
        [$event, $categoryEvent] = $this->individualEvent();
        $user = User::factory()->create();
        $player = Player::factory()->create();

        $this->withoutMiddleware([
                EnsureAgreementAccepted::class,
                EnsurePlayerProfileUpdated::class,
            ])
            ->actingAs($user)
            ->post(route('pay.now.payfast'), $this->registrationPayload($player, $categoryEvent))
            ->assertSessionHasErrors('msg');

        $this->assertDatabaseMissing('registration_order_items', [
            'player_id' => $player->id,
            'category_event_id' => $categoryEvent->id,
        ]);
    }

    public function test_user_can_register_an_owned_player_for_a_normal_event(): void
    {
        [$event, $categoryEvent] = $this->individualEvent();
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $user->players()->attach($player->id);

        $this->withoutMiddleware([
                EnsureAgreementAccepted::class,
                EnsurePlayerProfileUpdated::class,
            ])
            ->actingAs($user)
            ->post(route('pay.now.payfast'), $this->registrationPayload($player, $categoryEvent))
            ->assertOk();

        $this->assertDatabaseHas('registration_order_items', [
            'player_id' => $player->id,
            'category_event_id' => $categoryEvent->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_unlinked_legacy_masters_order_cannot_start_payment(): void
    {
        [$event, $categoryEvent, $user, $player] = $this->mastersEvent();
        [$order] = $this->orderFor($user, $player, $categoryEvent);

        $this->withoutMiddleware([
                EnsureAgreementAccepted::class,
                EnsurePlayerProfileUpdated::class,
            ])
            ->actingAs($user)
            ->from(route('events.show', $event))
            ->get(route('registration.checkout', $order))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors('registration');
    }

    public function test_unlinked_legacy_masters_order_is_blocked_at_every_payment_entry_point(): void
    {
        [$event, $categoryEvent, $user, $player] = $this->mastersEvent();
        [$order] = $this->orderFor($user, $player, $categoryEvent);
        Wallet::factory()->forUser($user)->create();
        $this->withoutMiddleware([
            EnsureAgreementAccepted::class,
            EnsurePlayerProfileUpdated::class,
        ]);
        $this->actingAs($user);

        $this->post(route('registration.payfast-only', $order))
            ->assertRedirect()
            ->assertSessionHasErrors('registration');
        $this->post(route('registration.hybrid.pay'), [
            'type' => 'registration',
            'custom_int5' => $order->id,
        ])->assertRedirect()->assertSessionHasErrors('registration');
        $this->postJson(route('registration.hybrid.apply-wallet'), [
            'order_id' => $order->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('registration');
        $this->post(route('registration.hybrid.complete', ['orderId' => $order->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('registration');

        $this->assertFalse((bool) $order->fresh()->pay_status);
        $this->assertFalse((bool) $order->fresh()->wallet_debited);
    }

    public function test_invitation_linked_masters_order_can_open_checkout(): void
    {
        [$event, $categoryEvent, $user, $player] = $this->mastersEvent();
        [$order, $registration] = $this->orderFor($user, $player, $categoryEvent);
        $batch = MastersInvitationBatch::create([
            'event_id' => $event->id,
            'series_id' => 1,
            'ranking_run_id' => 'registration-boundary',
            'top_x' => 8,
            'status' => 'sent',
            'registration_open' => true,
        ]);
        MastersInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $event->id,
            'category_event_id' => $categoryEvent->id,
            'player_id' => $player->id,
            'registration_id' => $registration->id,
            'order_id' => $order->id,
            'ranking_position' => 1,
            'queue_position' => 1,
            'status' => MastersInvitation::ACCEPTED_PENDING_PAYMENT,
        ]);

        $this->withoutMiddleware([
                EnsureAgreementAccepted::class,
                EnsurePlayerProfileUpdated::class,
            ])
            ->actingAs($user)
            ->get(route('registration.checkout', $order))
            ->assertOk();
    }

    private function mastersEvent(): array
    {
        $typeId = $this->eventType('masters', EventType::INDIVIDUAL);
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'eventType' => $typeId,
            'status' => 'open',
            'published' => 1,
            'signUp' => 1,
        ]);
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'entry_fee' => 285,
        ]);
        $player = Player::factory()->create(['userId' => $user->id]);

        return [$event, $categoryEvent, $user, $player];
    }

    private function individualEvent(): array
    {
        $typeId = $this->eventType('individual-test', EventType::INDIVIDUAL);
        $event = Event::factory()->create([
            'eventType' => $typeId,
            'status' => 'open',
            'published' => 1,
            'signUp' => 1,
        ]);
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'entry_fee' => 285,
        ]);

        return [$event, $categoryEvent];
    }

    private function eventType(string $code, int $type): int
    {
        return (int) DB::table('eventtypes')->insertGetId([
            'name' => ucfirst($code),
            'type' => $type,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function registrationPayload(Player $player, CategoryEvent $categoryEvent): array
    {
        return [
            'terms_accepted' => '1',
            'player' => [$player->id],
            'category' => [$categoryEvent->id],
        ];
    }

    private function orderFor(User $user, Player $player, CategoryEvent $categoryEvent): array
    {
        $registration = Registration::create([]);
        $registration->players()->sync([$player->id]);
        $order = RegistrationOrder::create([
            'user_id' => $user->id,
            'payfast_amount_due' => 285,
            'total_fee' => 285,
            'wallet_reserved' => 0,
            'wallet_debited' => false,
            'payfast_paid' => false,
            'pay_status' => false,
            'payment_method' => 'payfast',
            'status' => 'pending',
        ]);
        $item = new RegistrationOrderItems();
        $item->order_id = $order->id;
        $item->category_event_id = $categoryEvent->id;
        $item->registration_id = $registration->id;
        $item->player_id = $player->id;
        $item->user_id = $user->id;
        $item->item_price = 285;
        $item->save();

        return [$order, $registration];
    }
}
