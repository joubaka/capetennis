<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, CategoryEventRegistration, Event, Player, Registration, RegistrationOrder, RegistrationOrderItems, TrialPaymentProof, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventEntryCheckoutExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-29 12:00:00'));
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
    }

    public function test_entries_view_expires_at_five_minutes_using_order_clock_and_is_idempotent(): void
    {
        [$order, $entry, $event] = $this->checkout();
        $order->forceFill(['created_at' => now()->subMinutes(4)->subSeconds(59)])->save();
        DB::table('category_event_registrations')->where('id', $entry->id)->update(['created_at' => null]);
        $this->get(route('admin.events.entries.new', $event))->assertOk()
            ->assertSee('data-pending-checkout-id="'.$entry->id.'"', false)
            ->assertSee('29 Oct 2026 11:55')->assertSee('Checkout created');
        $this->assertSame('pending', $order->fresh()->status);
        $this->travel(1)->seconds();
        foreach ([1, 2] as $request) {
            $this->get(route('admin.events.entries.new', $event))->assertOk()
                ->assertDontSee('data-pending-checkout-id="'.$entry->id.'"', false);
        }
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        $this->assertFalse($order->fresh()->wallet_debited);
        $this->assertSoftDeleted('category_event_registrations', ['id' => $entry->id]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('registration_order_items', 1);
    }

    public function test_only_viewed_event_is_swept(): void
    {
        [$order, $entry, $event] = $this->checkout();
        [$foreignOrder, $foreignEntry] = $this->checkout();
        $this->get(route('admin.events.entries.new', $event))->assertOk();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('pending', $foreignOrder->fresh()->status);
        $this->assertNull($foreignEntry->fresh()->deleted_at);
        $this->assertSame(25.0, $foreignOrder->fresh()->wallet_reserved);
    }

    public function test_stale_unpaid_attempt_expires_without_touching_same_players_paid_entry(): void
    {
        [$order, $entry, $event] = $this->checkout();
        $originalItem = $order->items()->sole();
        $paidRegistration = Registration::create([]);
        $paidRegistration->players()->sync([$originalItem->player_id]);
        $paidEntry = CategoryEventRegistration::create(['registration_id' => $paidRegistration->id,
            'category_event_id' => $entry->category_event_id, 'user_id' => $order->user_id,
            'payment_status_id' => 1, 'status' => 'active']);
        $paidOrder = RegistrationOrder::create(['user_id' => $order->user_id, 'total_fee' => 100,
            'payfast_amount_due' => 0, 'wallet_reserved' => 0, 'wallet_debited' => false,
            'payfast_paid' => true, 'pay_status' => true, 'status' => 'paid']);
        $paidItem = $originalItem->replicate();
        $paidItem->forceFill(['order_id' => $paidOrder->id, 'registration_id' => $paidRegistration->id])->save();
        $before = $paidOrder->fresh()->getAttributes();
        $this->get(route('admin.events.entries.new', $event))->assertOk()
            ->assertDontSee('data-pending-checkout-id="'.$entry->id.'"', false)
            ->assertSee('1</span> confirmed entries', false);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSoftDeleted('category_event_registrations', ['id' => $entry->id]);
        $this->assertSame($before, $paidOrder->fresh()->getAttributes());
        $this->assertSame(1, $paidEntry->fresh()->payment_status_id);
        $this->assertNull($paidEntry->fresh()->deleted_at);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('registration_orders', 2);
        $this->assertDatabaseCount('registration_order_items', 2);
    }

    public function test_authorization_and_super_user_gate_precede_expiry(): void
    {
        [$order, $entry, $event] = $this->checkout();
        $this->actingAs(User::factory()->create())->get(route('admin.events.entries.new', $event))->assertForbidden();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin)->get(route('admin.events.entries.new', $event))->assertOk()->assertDontSee('Pending checkouts');
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($entry->fresh()->deleted_at);
        $this->assertSame(25.0, $order->fresh()->wallet_reserved);
    }

    public function test_fresh_handoff_paid_and_pending_or_verified_proof_are_preserved(): void
    {
        foreach (['handoff', 'paid', 'pending', 'verified'] as $protection) {
            [$order, $entry, $event] = $this->checkout();
            if ($protection === 'handoff') $order->forceFill(['payfast_handed_off_at' => now()->subMinutes(4)->subSeconds(59)])->save();
            elseif ($protection === 'paid') $order->forceFill(['pay_status' => true])->save();
            else TrialPaymentProof::create(['order_id' => $order->id, 'event_id' => $event->id, 'payer_id' => $order->user_id,
                'path' => 'private-proof.pdf', 'mime_type' => 'application/pdf', 'size' => 10, 'status' => $protection]);
            $before = $order->fresh()->getAttributes();
            $this->get(route('admin.events.entries.new', $event))->assertOk();
            $this->assertSame($before, $order->fresh()->getAttributes(), $protection);
            $this->assertNull($entry->fresh()->deleted_at, $protection);
        }
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_missing_order_or_clock_stays_visible_for_review(): void
    {
        [$order, $entry, $event] = $this->checkout();
        DB::table('registration_orders')->where('id', $order->id)->update(['created_at' => null]);
        $this->get(route('admin.events.entries.new', $event))->assertOk()
            ->assertSee('data-pending-checkout-id="'.$entry->id.'"', false)
            ->assertSee('Checkout time missing — review required');
        DB::table('registration_order_items')->where('order_id', $order->id)->delete();
        $this->get(route('admin.events.entries.new', $event))->assertOk()
            ->assertSee('data-pending-checkout-id="'.$entry->id.'"', false)
            ->assertSee('No matching checkout — review required');
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($entry->fresh()->deleted_at);
    }

    public function test_paid_order_with_unpaid_entry_remains_visible_as_review_without_mutation(): void
    {
        [$order, $entry, $event] = $this->checkout();
        $order->forceFill(['pay_status' => true, 'payfast_paid' => true])->save();
        $before = $order->fresh()->getAttributes();
        $this->get(route('admin.events.entries.new', $event))->assertOk()
            ->assertSee('data-pending-checkout-id="'.$entry->id.'"', false)
            ->assertSee('Paid order — entry needs review')
            ->assertDontSee('>Pending</span>', false);
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertSame(0, $entry->fresh()->payment_status_id);
        $this->assertNull($entry->fresh()->deleted_at);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_recent_expired_attempt_is_not_starved_by_inconsistent_old_backlog(): void
    {
        [$old, $entry, $event] = $this->checkout();
        $old->forceFill(['created_at' => now()->subDay()])->save();
        $originalItem = $old->items()->sole();
        for ($index = 0; $index < 250; $index++) {
            $order = $old->replicate(); $order->save();
            $item = $originalItem->replicate(); $item->order_id = $order->id; $item->save();
        }
        [$recent, $recentEntry] = $this->checkout();
        $recentEntry->categoryEvent->update(['event_id' => $event->id]);
        $this->get(route('admin.events.entries.new', $event))->assertOk();
        $this->assertSame('cancelled', $recent->fresh()->status);
        $this->assertSoftDeleted('category_event_registrations', ['id' => $recentEntry->id]);
        $this->assertSame('pending', $old->fresh()->status);
        $this->assertNull($entry->fresh()->deleted_at);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_cancelled_order_with_surviving_entry_is_reviewed_without_mutation(): void
    {
        [$order, $entry, $event] = $this->checkout();
        $order->forceFill(['status' => 'cancelled'])->save();
        $before = $order->fresh()->getAttributes();
        $this->get(route('admin.events.entries.new', $event))->assertOk()
            ->assertSee('Cancelled checkout — entry needs review')
            ->assertDontSee('>Pending</span>', false);
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertNull($entry->fresh()->deleted_at);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_multiple_orders_and_mixed_event_order_remain_for_review(): void
    {
        [$order, $entry, $event] = $this->checkout();
        $duplicate = $order->replicate(); $duplicate->save();
        $item = $order->items()->sole()->replicate(); $item->order_id = $duplicate->id; $item->save();
        $this->get(route('admin.events.entries.new', $event))->assertOk()->assertSee('Multiple checkouts — review required');
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('pending', $duplicate->fresh()->status);
        [$foreignOrder, $foreignEntry] = $this->checkout();
        $foreignOrder->items()->update(['order_id' => $order->id]);
        $this->get(route('admin.events.entries.new', $event))->assertOk();
        $this->assertNull($entry->fresh()->deleted_at);
        $this->assertNull($foreignEntry->fresh()->deleted_at);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function checkout(): array
    {
        $payer = User::factory()->create();
        $event = Event::factory()->create(['published' => true, 'signUp' => true, 'start_date' => now()->addMonth(), 'deadline' => 1]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $player = Player::factory()->create();
        $registration = Registration::create([]); $registration->players()->sync([$player->id]);
        $entry = CategoryEventRegistration::create(['registration_id' => $registration->id, 'category_event_id' => $category->id,
            'user_id' => $payer->id, 'payment_status_id' => 0, 'status' => 'active']);
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 100, 'wallet_reserved' => 25,
            'payfast_amount_due' => 75, 'wallet_debited' => false, 'payfast_paid' => false, 'pay_status' => false, 'status' => 'pending']);
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        (new RegistrationOrderItems)->forceFill(['order_id' => $order->id, 'registration_id' => $registration->id,
            'category_event_id' => $category->id, 'player_id' => $player->id, 'user_id' => $payer->id, 'item_price' => 100])->save();
        return [$order, $entry, $event];
    }
}
