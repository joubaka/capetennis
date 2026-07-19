<?php

namespace Tests\Feature\HeadOffice;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Authorization tests for EventAdminController.
 *
 * Routes under test:
 *   GET   eventAdmin/{id}                          (show / resource)
 *   GET   eventAdmin/main/{id}                     (main)
 *   GET   event/{event}/overview                   (overview)
 *   GET   event/{event}/fixtures                   (individual hq)
 *   POST  headOffice/createFixtures/{event}        (generateFixtures)
 *   POST  event/{event}/create-individual-draw     (createIndividualDraw)
 *
 * Expected access:
 *   - Guest                → redirect (auth middleware)
 *   - Ordinary user        → 403
 *   - Admin / convenor     → permitted
 *   - Super-user           → permitted (Gate::before bypass)
 */
class EventAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private User $admin;
    private User $ordinaryUser;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor',   'guard_name' => 'web']);

        DB::table('eventtypes')->insert([
            ['id' => 1, 'name' => 'Individual', 'type' => EventType::INDIVIDUAL],
            ['id' => 3, 'name' => 'Team',        'type' => EventType::TEAM],
        ]);

        $this->event = Event::factory()->create(['eventType' => 1]);

        $this->superUser    = User::factory()->create()->assignRole('super-user');
        $this->ordinaryUser = User::factory()->create();

        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $this->event->id,
            'user_id'  => $this->admin->id,
        ]);
    }

    // ── show (eventAdmin resource) ───────────────────────────────────────────

    public function test_guest_is_redirected_from_show(): void
    {
        $this->get(route('eventAdmin.show', $this->event->id))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_view_event_show(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson(route('eventAdmin.show', $this->event->id))
            ->assertForbidden();
    }

    public function test_admin_can_view_event_show(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('eventAdmin.show', $this->event->id));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    public function test_super_user_can_view_event_show(): void
    {
        $response = $this->actingAs($this->superUser)
            ->getJson(route('eventAdmin.show', $this->event->id));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    // ── main ─────────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_main(): void
    {
        $this->get(route('event.admin.main', $this->event->id))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_view_event_main(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson(route('event.admin.main', $this->event->id))
            ->assertForbidden();
    }

    public function test_admin_can_view_event_main(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('event.admin.main', $this->event->id));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    // ── overview ─────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_overview(): void
    {
        $this->get(route('admin.events.overview', $this->event))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_view_overview(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson(route('admin.events.overview', $this->event))
            ->assertForbidden();
    }

    public function test_admin_can_view_overview(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.events.overview', $this->event));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    public function test_super_user_can_view_overview(): void
    {
        $response = $this->actingAs($this->superUser)
            ->getJson(route('admin.events.overview', $this->event));

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    // ── generateFixtures ────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_generate_fixtures(): void
    {
        $this->post(route('headoffice.createFixtures', $this->event))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_generate_fixtures(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('headoffice.createFixtures', $this->event), [])
            ->assertForbidden();
    }

    public function test_admin_can_generate_fixtures(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('headoffice.createFixtures', $this->event), ['mode' => 'perType']);

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    public function test_super_user_can_generate_fixtures(): void
    {
        $response = $this->actingAs($this->superUser)
            ->postJson(route('headoffice.createFixtures', $this->event), ['mode' => 'perType']);

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    // ── createIndividualDraw ────────────────────────────────────────────────

    public function test_guest_is_redirected_from_create_individual_draw(): void
    {
        $this->post(route('headoffice.createSingleDraw', $this->event))
            ->assertRedirect();
    }

    public function test_ordinary_user_cannot_create_individual_draw(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('headoffice.createSingleDraw', $this->event), [])
            ->assertForbidden();
    }

    public function test_admin_can_create_individual_draw(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('headoffice.createSingleDraw', $this->event), [
                'drawName'      => 'Test Draw',
                'draw_type_id'  => 1,
                'category_id'   => null,
                'category_ids'  => [],
            ]);

        // May fail validation (422) but must not be 401/403
        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }
}
