<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\User;
use App\Services\Masters\MastersInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MastersPaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_an_invitation_does_not_create_an_active_entry_before_payment(): void
    {
        [$invitation, $user] = $this->invitationScenario();

        $order = app(MastersInvitationService::class)->accept($invitation, $user);
        $invitation->refresh();

        $this->assertSame(MastersInvitation::ACCEPTED_PENDING_PAYMENT, $invitation->status);
        $this->assertSame($order->id, $invitation->order_id);
        $this->assertDatabaseHas('registration_order_items', [
            'order_id' => $order->id,
            'registration_id' => $invitation->registration_id,
            'player_id' => $invitation->player_id,
            'category_event_id' => $invitation->category_event_id,
        ]);
        $this->assertDatabaseMissing('category_event_registrations', [
            'registration_id' => $invitation->registration_id,
            'category_event_id' => $invitation->category_event_id,
            'deleted_at' => null,
        ]);
    }

    public function test_stale_unpaid_checkout_returns_to_register_and_hides_legacy_draft_entry(): void
    {
        [$invitation, $user] = $this->invitationScenario();
        $service = app(MastersInvitationService::class);
        $order = $service->accept($invitation, $user);
        $invitation->refresh()->update(['accepted_at' => now()->subMinutes(61)]);

        $entry = CategoryEventRegistration::create([
            'registration_id' => $invitation->registration_id,
            'category_event_id' => $invitation->category_event_id,
            'user_id' => $user->id,
            'status' => 'active',
            'payment_status_id' => 0,
        ]);

        $rows = $service->reconcilePaymentStates($invitation->event_id, 60, true);

        $this->assertSame('return_to_register', $rows[0]['action']);
        $this->assertDatabaseHas('masters_invitations', [
            'id' => $invitation->id,
            'status' => MastersInvitation::INVITED,
            'order_id' => null,
            'registration_id' => null,
            'accepted_at' => null,
        ]);
        $this->assertSoftDeleted('category_event_registrations', ['id' => $entry->id]);
        $this->assertSame(0.0, (float) $order->fresh()->payfast_amount_due);
        $this->assertFalse((bool) $order->fresh()->pay_status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->actingAs($user)
            ->get(route('registration.checkout', $order))
            ->assertRedirect()
            ->assertSessionHasErrors();
    }

    public function test_existing_paid_entry_is_linked_and_unpaid_duplicate_is_retired(): void
    {
        [$invitation, $user] = $this->invitationScenario();

        [$paidOrder, $paidRegistration] = $this->orderFor(
            $invitation,
            $user,
            paid: true,
        );
        [$duplicateOrder, $duplicateRegistration] = $this->orderFor(
            $invitation,
            $user,
            paid: false,
        );

        $rows = app(MastersInvitationService::class)
            ->reconcilePaymentStates($invitation->event_id, 60, true);

        $this->assertSame('link_paid_registration', $rows[0]['action']);
        $this->assertDatabaseHas('masters_invitations', [
            'id' => $invitation->id,
            'status' => MastersInvitation::PAID_CONFIRMED,
            'order_id' => $paidOrder->id,
            'registration_id' => $paidRegistration->id,
        ]);
        $this->assertDatabaseHas('category_event_registrations', [
            'registration_id' => $paidRegistration->id,
            'category_event_id' => $invitation->category_event_id,
            'payment_status_id' => 1,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('category_event_registrations', [
            'registration_id' => $duplicateRegistration->id,
            'category_event_id' => $invitation->category_event_id,
        ]);
        $this->assertSame(0.0, (float) $duplicateOrder->fresh()->payfast_amount_due);
    }

    private function invitationScenario(): array
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'entry_fee' => 285,
        ]);
        $player = Player::factory()->create(['userId' => $user->id]);
        $batch = MastersInvitationBatch::create([
            'event_id' => $event->id,
            'series_id' => 1,
            'ranking_run_id' => 'test-run',
            'top_x' => 8,
            'status' => 'sent',
            'registration_open' => true,
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addWeek(),
            'replacement_payment_deadline' => now()->addWeek(),
        ]);
        $invitation = MastersInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $event->id,
            'category_event_id' => $categoryEvent->id,
            'player_id' => $player->id,
            'ranking_position' => 1,
            'queue_position' => 1,
            'status' => MastersInvitation::INVITED,
            'invited_at' => now(),
        ]);

        return [$invitation, $user];
    }

    private function orderFor(
        MastersInvitation $invitation,
        User $user,
        bool $paid,
    ): array {
        $registration = Registration::create([]);
        $registration->players()->sync([$invitation->player_id]);
        $order = RegistrationOrder::create([
            'user_id' => $user->id,
            'payfast_amount_due' => 285,
            'total_fee' => 285,
            'wallet_reserved' => 0,
            'wallet_debited' => false,
            'payfast_paid' => $paid,
            'pay_status' => $paid,
            'payment_method' => 'payfast',
            'status' => $paid ? 'completed' : 'pending',
        ]);
        $item = new RegistrationOrderItems();
        $item->order_id = $order->id;
        $item->category_event_id = $invitation->category_event_id;
        $item->registration_id = $registration->id;
        $item->player_id = $invitation->player_id;
        $item->user_id = $user->id;
        $item->item_price = 285;
        $item->save();
        CategoryEventRegistration::create([
            'registration_id' => $registration->id,
            'category_event_id' => $invitation->category_event_id,
            'user_id' => $user->id,
            'status' => 'active',
            'payment_status_id' => $paid ? 1 : 0,
            'pf_transaction_id' => $paid ? 'PF-PAID' : null,
        ]);

        return [$order, $registration];
    }
}
