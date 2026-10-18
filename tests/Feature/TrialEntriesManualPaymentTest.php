<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, EventNomination, EventType, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, Registration, RegistrationOrder, RegistrationOrderItems, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrialEntriesManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $payer;
    private Event $event;
    private CategoryEvent $category;
    private Player $player;
    private RegistrationOrder $order;
    private CategoryEventRegistration $entry;
    private InterprovincialTrialInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->payer = User::factory()->create();
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $this->event = Event::factory()->create(['eventType' => $type, 'entryFee' => 123.45]);
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        $this->category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->player = Player::factory()->create();
        $registration = Registration::factory()->create();
        $registration->players()->attach($this->player);
        $this->order = RegistrationOrder::create(['user_id' => $this->payer->id, 'total_fee' => 123.45, 'wallet_reserved' => 0, 'payfast_amount_due' => 123.45, 'pay_status' => false, 'status' => 'pending']);
        RegistrationOrderItems::forceCreate(['order_id' => $this->order->id, 'registration_id' => $registration->id, 'category_event_id' => $this->category->id, 'player_id' => $this->player->id, 'user_id' => $this->payer->id, 'item_price' => 123.45]);
        $this->entry = CategoryEventRegistration::factory()->create(['registration_id' => $registration->id, 'category_event_id' => $this->category->id, 'user_id' => $this->payer->id, 'payment_status_id' => null, 'status' => 'pending_checkout']);
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $this->player->id]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $this->event->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $this->admin->id]);
        $this->invitation = InterprovincialTrialInvitation::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'nomination_id' => $nomination->id, 'batch_id' => $batch->id, 'player_id' => $this->player->id, 'registration_id' => $registration->id, 'order_id' => $this->order->id, 'status' => 'accepted_pending_payment']);
        $this->actingAs($this->admin);
    }

    private function payload(bool $confirm = true): array
    {
        return ['registration_id' => $this->player->id, 'collection_status' => 'paid_privately'] + ($confirm ? [
            'existing_order_id' => $this->order->id, 'confirmed_amount' => '123.45', 'reference' => 'CASH-RECEIPT-123',
        ] : []);
    }

    private function endpoint(?CategoryEvent $category = null): string
    {
        return route('admin.category.addPlayer', $category ?? $this->category);
    }

    public function test_entries_screen_previews_exact_checkout_then_settles_existing_entry_once(): void
    {
        $this->get(route('admin.events.entries.new', $this->event))->assertOk()
            ->assertSee('Cash/EFT receipt or bank reference')->assertSee('Record payment against the existing checkout');
        $this->postJson($this->endpoint(), $this->payload(false))->assertStatus(409)
            ->assertJsonPath('existing_checkout.order_id', $this->order->id)
            ->assertJsonPath('existing_checkout.amount', '123.45');
        $this->assertDatabaseCount('registration_manual_receipts', 0);
        $this->assertFalse($this->entry->fresh()->is_paid);

        foreach ([1, 2] as $attempt) {
            $this->postJson($this->endpoint(), $this->payload())->assertOk()
                ->assertJsonPath('existing_checkout_settled', true)->assertJsonPath('count', 1);
        }
        $this->assertSame('active', $this->entry->fresh()->status);
        $this->assertTrue($this->entry->fresh()->is_paid);
        $this->assertSame($this->payer->id, $this->entry->fresh()->user_id);
        $this->assertSame($this->payer->id, $this->order->fresh()->user_id);
        $this->assertSame('paid_confirmed', $this->invitation->fresh()->status);
        $this->assertDatabaseHas('registration_manual_receipts', ['order_id' => $this->order->id, 'event_id' => $this->event->id, 'amount' => '123.45', 'reference' => 'CASH-RECEIPT-123', 'verified_by_user_id' => $this->admin->id]);
        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('registration_order_items', 1);
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseCount('registration_manual_receipts', 1);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_confirmation_requires_reference_and_exact_preview_amount_and_does_not_replace_receipt(): void
    {
        $this->postJson($this->endpoint(), array_diff_key($this->payload(), ['reference' => true]))->assertUnprocessable();
        foreach (['123.44', '123.46', '0', '123.451'] as $amount) {
            $this->postJson($this->endpoint(), array_replace($this->payload(), ['confirmed_amount' => $amount]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('registration_manual_receipts', 0);
        $this->postJson($this->endpoint(), $this->payload())->assertOk();
        $this->postJson($this->endpoint(), array_replace($this->payload(), ['reference' => 'DIFFERENT-REFERENCE']))->assertUnprocessable();
        $this->assertDatabaseCount('registration_manual_receipts', 1);
        $this->assertDatabaseHas('registration_manual_receipts', ['reference' => 'CASH-RECEIPT-123']);
    }

    public function test_other_player_category_event_or_order_cannot_be_settled(): void
    {
        $this->postJson($this->endpoint(), array_replace($this->payload(), ['registration_id' => Player::factory()->create()->id]))->assertUnprocessable();
        $this->postJson($this->endpoint(CategoryEvent::factory()->create(['event_id' => $this->event->id])), $this->payload())->assertUnprocessable();
        $foreignEvent = Event::factory()->create(['eventType' => $this->event->eventType]);
        DB::table('event_admins')->insert(['event_id' => $foreignEvent->id, 'user_id' => $this->admin->id]);
        $this->postJson($this->endpoint(CategoryEvent::factory()->create(['event_id' => $foreignEvent->id])), $this->payload())->assertUnprocessable();
        $otherOrder = RegistrationOrder::create(['user_id' => $this->payer->id, 'total_fee' => 123.45]);
        $this->postJson($this->endpoint(), array_replace($this->payload(), ['existing_order_id' => $otherOrder->id]))->assertUnprocessable();
        $this->assertDatabaseCount('registration_manual_receipts', 0);
        $this->assertFalse($this->order->fresh()->pay_status);
    }

    public function test_unassigned_admin_and_payer_cannot_preview_or_confirm_payment(): void
    {
        foreach ([User::factory()->create()->assignRole('admin'), $this->payer] as $actor) {
            $this->actingAs($actor);
            $this->postJson($this->endpoint(), $this->payload(false))->assertForbidden();
            $this->postJson($this->endpoint(), $this->payload())->assertForbidden();
        }
        $this->assertDatabaseCount('registration_manual_receipts', 0);
    }

    public function test_restarted_checkout_cannot_be_confirmed_using_stale_order(): void
    {
        $this->postJson($this->endpoint(), $this->payload(false))->assertStatus(409);
        $replacement = RegistrationOrder::create(['user_id' => $this->payer->id, 'total_fee' => 123.45]);
        $this->invitation->update(['order_id' => $replacement->id]);
        $this->postJson($this->endpoint(), $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('registration_manual_receipts', 0);
        $this->assertFalse($this->order->fresh()->pay_status);
    }

    public function test_canonical_handoff_wallet_provider_and_amount_guards_are_preserved(): void
    {
        foreach ([['payfast_handed_off_at' => now()], ['wallet_reserved' => 1], ['payfast_pf_payment_id' => 'PROVIDER-EVIDENCE'], ['total_fee' => 120], ['status' => 'cancelled']] as $changes) {
            $before = $this->order->getAttributes();
            $this->order->update($changes);
            $this->postJson($this->endpoint(), $this->payload())->assertUnprocessable();
            $this->order->setRawAttributes($before)->save();
        }
        $this->assertDatabaseCount('registration_manual_receipts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertFalse($this->entry->fresh()->is_paid);
    }

    public function test_category_draw_and_withdrawn_entry_protections_are_preserved(): void
    {
        $draw = Draw::factory()->create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id]);
        $this->postJson($this->endpoint(), $this->payload())->assertUnprocessable();
        $draw->delete();
        $this->entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $this->postJson($this->endpoint(), $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('registration_manual_receipts', 0);
        $this->assertDatabaseCount('category_event_registrations', 1);
    }
}
