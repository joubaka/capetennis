<?php

namespace Tests\Feature\HeadOffice;

use App\Exports\EventEntriesExport;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Authorization tests for EventEntryController.
 *
 * Routes under test:
 *   GET   event/{event}/entries                            (index)
 *   POST  event/category/{categoryEvent}/lock             (lock)
 *   POST  event/category/{categoryEvent}/unlock           (unlock)
 *   POST  event/category/{categoryEvent}/add-player       (addPlayer)
 *   DELETE event/category/{categoryEvent}/remove-player/{registration} (removePlayer)
 *   GET   /category/{categoryEvent}/available-registrations (availableRegistrations)
 *
 * Expected access:
 *   - Guest        → redirect (auth middleware)
 *   - Ordinary     → 403
 *   - Admin/convenor → permitted
 *   - Super-user   → permitted (Gate::before bypass)
 */
class EventEntryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private User $admin;
    private User $ordinaryUser;

    private Event $event;
    private CategoryEvent $categoryEvent;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor',   'guard_name' => 'web']);

        DB::table('eventtypes')->insert([
            ['id' => 1, 'name' => 'Individual', 'type' => EventType::INDIVIDUAL],
        ]);

        $this->event = Event::factory()->create(['eventType' => 1]);

        $this->categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $this->event->id,
        ]);

        $this->superUser    = User::factory()->create()->assignRole('super-user');
        $this->ordinaryUser = User::factory()->create();

        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $this->event->id,
            'user_id'  => $this->admin->id,
        ]);
    }

    // ── index ────────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_entries_index(): void
    {
        $this->get(route('admin.events.entries.new', $this->event))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_view_entries(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson(route('admin.events.entries.new', $this->event))
            ->assertForbidden();
    }

    public function test_admin_can_view_entries(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.events.entries.new', $this->event));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    public function test_super_user_can_view_entries(): void
    {
        $response = $this->actingAs($this->superUser)
            ->getJson(route('admin.events.entries.new', $this->event));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    // ── category management ────────────────────────────────────────────────────

    public function test_assigned_event_admin_can_manage_category(): void
    {
        $this->actingAs($this->admin)
            ->get(route('category.manage', $this->categoryEvent))
            ->assertOk()
            ->assertViewIs('backend.categoryEvent.manage');
    }

    public function test_ordinary_user_cannot_manage_category(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->get(route('category.manage', $this->categoryEvent))
            ->assertForbidden();
    }

    public function test_unassigned_admin_cannot_manage_category(): void
    {
        $unassignedAdmin = User::factory()->create()->assignRole('admin');

        $this->actingAs($unassignedAdmin)
            ->get(route('category.manage', $this->categoryEvent))
            ->assertForbidden();
    }

    public function test_admin_assigned_to_another_event_cannot_manage_category(): void
    {
        $otherEvent = Event::factory()->create(['eventType' => 1]);
        $crossEventAdmin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $otherEvent->id,
            'user_id' => $crossEventAdmin->id,
        ]);

        $this->actingAs($crossEventAdmin)
            ->get(route('category.manage', $this->categoryEvent))
            ->assertForbidden();
    }

    public function test_super_user_can_manage_category(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('category.manage', $this->categoryEvent))
            ->assertOk()
            ->assertViewIs('backend.categoryEvent.manage');
    }

    public function test_admin_can_export_event_entries(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)
            ->get(route('admin.events.entries.export', $this->event))
            ->assertOk();

        Excel::assertDownloaded(
            "event_{$this->event->id}_entries.xlsx",
            fn ($export) => $export instanceof EventEntriesExport
                && $export->event->is($this->event)
        );
    }

    // ── lock ─────────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_lock(): void
    {
        $this->post(route('admin.category.lock', $this->categoryEvent))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_lock_category(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('admin.category.lock', $this->categoryEvent))
            ->assertForbidden();

        $this->assertNull($this->categoryEvent->refresh()->locked_at);
    }

    public function test_unassigned_and_cross_event_admins_cannot_lock_category(): void
    {
        $unassignedAdmin = User::factory()->create()->assignRole('admin');
        $otherEvent = Event::factory()->create(['eventType' => 1]);
        $crossEventAdmin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $otherEvent->id,
            'user_id' => $crossEventAdmin->id,
        ]);

        foreach ([$unassignedAdmin, $crossEventAdmin] as $actor) {
            $this->actingAs($actor)
                ->postJson(route('admin.category.lock', $this->categoryEvent))
                ->assertForbidden();

            $this->assertNull($this->categoryEvent->refresh()->locked_at);
        }
    }

    public function test_admin_can_lock_category(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.category.lock', $this->categoryEvent))
            ->assertOk()
            ->assertJsonPath('locked', true);

        $this->assertNotNull($this->categoryEvent->refresh()->locked_at);
    }

    public function test_super_user_can_lock_category(): void
    {
        $this->actingAs($this->superUser)
            ->postJson(route('admin.category.lock', $this->categoryEvent))
            ->assertOk()
            ->assertJsonPath('locked', true);

        $this->assertNotNull($this->categoryEvent->refresh()->locked_at);
    }

    // ── unlock ───────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_unlock(): void
    {
        $this->post(route('admin.category.unlock', $this->categoryEvent))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_unlock_category(): void
    {
        $this->categoryEvent->update(['locked_at' => now()]);

        $this->actingAs($this->ordinaryUser)
            ->postJson(route('admin.category.unlock', $this->categoryEvent))
            ->assertForbidden();

        $this->assertNotNull($this->categoryEvent->refresh()->locked_at);
    }

    public function test_unassigned_and_cross_event_admins_cannot_unlock_category(): void
    {
        $this->categoryEvent->update(['locked_at' => now()]);
        $unassignedAdmin = User::factory()->create()->assignRole('admin');
        $otherEvent = Event::factory()->create(['eventType' => 1]);
        $crossEventAdmin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $otherEvent->id,
            'user_id' => $crossEventAdmin->id,
        ]);

        foreach ([$unassignedAdmin, $crossEventAdmin] as $actor) {
            $this->actingAs($actor)
                ->postJson(route('admin.category.unlock', $this->categoryEvent))
                ->assertForbidden();

            $this->assertNotNull($this->categoryEvent->refresh()->locked_at);
        }
    }

    public function test_assigned_event_admin_can_unlock_category(): void
    {
        $this->categoryEvent->update(['locked_at' => now()]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.category.unlock', $this->categoryEvent))
            ->assertOk()
            ->assertJsonPath('locked', false);

        $this->assertNull($this->categoryEvent->refresh()->locked_at);
    }

    public function test_super_user_can_unlock_category(): void
    {
        $this->categoryEvent->update(['locked_at' => now()]);

        $this->actingAs($this->superUser)
            ->postJson(route('admin.category.unlock', $this->categoryEvent))
            ->assertOk()
            ->assertJsonPath('locked', false);

        $this->assertNull($this->categoryEvent->refresh()->locked_at);
    }

    // ── availableRegistrations ───────────────────────────────────────────────

    public function test_guest_is_redirected_from_available_registrations(): void
    {
        $this->get(route('admin.category.availableRegistrations', $this->categoryEvent))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_view_available_registrations(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson(route('admin.category.availableRegistrations', $this->categoryEvent))
            ->assertForbidden();
    }

    public function test_admin_can_view_available_registrations(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.category.availableRegistrations', $this->categoryEvent));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    public function test_available_registration_search_is_bounded_private_and_excludes_active_entries(): void
    {
        Player::factory()->count(24)->create([
            'name' => 'Searchable',
            'surname' => 'Player',
        ]);
        $enteredPlayer = Player::factory()->create([
            'name' => 'Searchable',
            'surname' => 'Entered',
            'email' => 'private@example.test',
        ]);
        $registration = Registration::factory()->create();
        PlayerRegistration::create([
            'registration_id' => $registration->id,
            'player_id' => $enteredPlayer->id,
        ]);
        CategoryEventRegistration::factory()->create([
            'category_event_id' => $this->categoryEvent->id,
            'registration_id' => $registration->id,
            'payment_status_id' => 1,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.category.availableRegistrations', [
                'categoryEvent' => $this->categoryEvent,
                'q' => 'Searchable',
                'page' => 1,
            ]))
            ->assertOk()
            ->assertJsonCount(20, 'results')
            ->assertJsonPath('pagination.more', true);

        $results = collect($response->json('results'));
        $this->assertFalse($results->contains('id', $enteredPlayer->id));
        $this->assertTrue($results->every(fn (array $result) => array_keys($result) === ['id', 'text']));
        $this->assertFalse(str_contains($response->getContent(), 'private@example.test'));
    }

    // ── addPlayer ────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_add_player(): void
    {
        $this->post(route('admin.category.addPlayer', $this->categoryEvent))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_add_player(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('admin.category.addPlayer', $this->categoryEvent), [])
            ->assertForbidden();
    }

    public function test_event_admin_can_register_player_offline_as_unpaid(): void
    {
        $player = Player::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.category.addPlayer', $this->categoryEvent), [
                'registration_id' => $player->id,
                'collection_status' => 'unpaid',
            ])
            ->assertOk()
            ->assertJsonPath('admin_payment_status', 'unpaid');

        $this->assertDatabaseHas('category_event_registrations', [
            'category_event_id' => $this->categoryEvent->id,
            'payment_status_id' => 1,
            'status' => 'active',
            'admin_payment_status' => 'unpaid',
        ]);
    }

    public function test_legacy_add_player_request_without_collection_status_defaults_to_unpaid(): void
    {
        $player = Player::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.category.addPlayer', $this->categoryEvent), [
                'registration_id' => $player->id,
            ])
            ->assertOk()
            ->assertJsonPath('admin_payment_status', 'unpaid');

        $this->assertDatabaseHas('category_event_registrations', [
            'category_event_id' => $this->categoryEvent->id,
            'payment_status_id' => 1,
            'status' => 'active',
            'admin_payment_status' => 'unpaid',
        ]);
    }

    public function test_event_admin_can_register_player_paid_privately(): void
    {
        $player = Player::factory()->create();
        DB::table('events')->where('id', $this->event->id)->update(['cape_tennis_fee' => 23.75]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.category.addPlayer', $this->categoryEvent), [
                'registration_id' => $player->id,
                'collection_status' => 'paid_privately',
            ])
            ->assertOk()
            ->assertJsonPath('admin_payment_status', 'paid');

        $this->assertDatabaseHas('category_event_registrations', [
            'category_event_id' => $this->categoryEvent->id,
            'payment_status_id' => 1,
            'status' => 'active',
            'admin_payment_status' => 'paid',
            'pf_transaction_id' => null,
        ]);
        $this->assertDatabaseHas('transactions_pf', [
            'event_id' => $this->event->id,
            'category_event_id' => $this->categoryEvent->id,
            'amount_gross' => 0,
            'amount_net' => 0,
            'amount_fee' => 0,
            'cape_tennis_fee' => 23.75,
            'pf_payment_id' => null,
        ]);
    }

    public function test_offline_registration_rejects_invalid_collection_status_without_partial_records(): void
    {
        $player = Player::factory()->create();
        $registrationCount = Registration::count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.category.addPlayer', $this->categoryEvent), [
                'registration_id' => $player->id,
                'collection_status' => 'payfast',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('collection_status');

        $this->assertSame($registrationCount, Registration::count());
        $this->assertDatabaseCount('category_event_registrations', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_event_admin_cannot_list_or_add_players_for_another_event(): void
    {
        $otherEvent = Event::factory()->create(['eventType' => 1]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $otherEvent->id]);
        $player = Player::factory()->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.category.availableRegistrations', $otherCategory))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->postJson(route('admin.category.addPlayer', $otherCategory), [
                'registration_id' => $player->id,
                'collection_status' => 'unpaid',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('category_event_registrations', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    // ── admin private payment note ──────────────────────────────────────────

    public function test_event_admin_can_toggle_private_payment_note_without_changing_paid_entry(): void
    {
        $entry = $this->createEntryWithAdminPaymentStatus('unpaid');

        $this->actingAs($this->admin)
            ->patchJson(route('admin.entry.admin-payment-status', $entry), ['paid' => true])
            ->assertOk()
            ->assertJsonPath('admin_payment_status', 'paid')
            ->assertJsonPath('canonical_payment_status', 'paid');

        $entry->refresh();
        $this->assertSame('paid', $entry->admin_payment_status);
        $this->assertSame(1, (int) $entry->payment_status_id);
    }

    public function test_ordinary_user_cannot_toggle_admin_private_payment_note(): void
    {
        $entry = $this->createEntryWithAdminPaymentStatus('unpaid');

        $this->actingAs($this->ordinaryUser)
            ->patchJson(route('admin.entry.admin-payment-status', $entry), ['paid' => true])
            ->assertForbidden();

        $this->assertSame('unpaid', $entry->refresh()->admin_payment_status);
    }

    public function test_event_admin_cannot_toggle_another_events_private_payment_note(): void
    {
        $otherEvent = Event::factory()->create(['eventType' => 1]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $otherEvent->id]);
        $entry = $this->createEntryWithAdminPaymentStatus('unpaid', $otherCategory);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.entry.admin-payment-status', $entry), ['paid' => true])
            ->assertForbidden();

        $this->assertSame('unpaid', $entry->refresh()->admin_payment_status);
    }

    public function test_non_admin_entry_cannot_receive_private_payment_note(): void
    {
        $entry = $this->createEntryWithAdminPaymentStatus(null);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.entry.admin-payment-status', $entry), ['paid' => true])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertNull($entry->refresh()->admin_payment_status);
        $this->assertSame(1, (int) $entry->payment_status_id);
    }

    // ── removePlayer ─────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_remove_player(): void
    {
        $registration = \App\Models\Registration::factory()->create();

        $this->delete(route('admin.category.removePlayer', [
            'categoryEvent' => $this->categoryEvent->id,
            'registration'  => $registration->id,
        ]))->assertRedirect();
    }

    public function test_ordinary_user_cannot_remove_player(): void
    {
        $registration = \App\Models\Registration::factory()->create();

        $this->actingAs($this->ordinaryUser)
            ->deleteJson(route('admin.category.removePlayer', [
                'categoryEvent' => $this->categoryEvent->id,
                'registration'  => $registration->id,
            ]))->assertForbidden();
    }

    private function createEntryWithAdminPaymentStatus(
        ?string $adminPaymentStatus,
        ?CategoryEvent $categoryEvent = null
    ): CategoryEventRegistration {
        $registration = Registration::factory()->create();
        $player = Player::factory()->create();

        PlayerRegistration::create([
            'registration_id' => $registration->id,
            'player_id' => $player->id,
        ]);

        return CategoryEventRegistration::factory()->create([
            'category_event_id' => ($categoryEvent ?? $this->categoryEvent)->id,
            'registration_id' => $registration->id,
            'user_id' => $this->admin->id,
            'status' => 'active',
            'payment_status_id' => 1,
            'admin_payment_status' => $adminPaymentStatus,
        ]);
    }
}
