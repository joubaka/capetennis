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
    }

    public function test_admin_can_lock_category(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.category.lock', $this->categoryEvent));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    public function test_super_user_can_lock_category(): void
    {
        $response = $this->actingAs($this->superUser)
            ->postJson(route('admin.category.lock', $this->categoryEvent));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    // ── unlock ───────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_unlock(): void
    {
        $this->post(route('admin.category.unlock', $this->categoryEvent))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_unlock_category(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('admin.category.unlock', $this->categoryEvent))
            ->assertForbidden();
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
