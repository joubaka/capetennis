<?php

namespace Tests\Feature\TeamDraw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Authorization tests for TeamScheduleController.
 *
 * Routes under test:
 *   GET   backend/team-schedule/{draw}/data             (scheduleData)
 *   POST  backend/team-schedule/{draw}/save             (saveFixture)
 *   POST  backend/team-schedule/{draw}/auto             (autoSchedule)
 *   GET   backend/team-schedule/all/{event}             (indexAll)
 *   GET   backend/team-schedule/all-data/{event}        (dataAll)
 *   POST  backend/team-schedule/all-auto/{event}        (autoAll)
 *
 * Expected access:
 *   - Guest                               → 401 (auth middleware)
 *   - Ordinary user                       → 403
 *   - Admin of correct team event         → permitted
 *   - Admin of a DIFFERENT event          → 403
 *   - Super-user                          → permitted (Gate::before bypass)
 */
class TeamScheduleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private User $admin;
    private User $adminOther;
    private User $ordinaryUser;

    private Event $teamEvent;
    private Event $otherEvent;
    private Draw  $draw;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);

        DB::table('eventtypes')->insert([
            ['id' => 1, 'name' => 'Individual', 'type' => EventType::INDIVIDUAL],
            ['id' => 3, 'name' => 'team event',  'type' => EventType::TEAM],
        ]);

        $this->teamEvent  = Event::factory()->create(['eventType' => 3]);
        $this->otherEvent = Event::factory()->create(['eventType' => 3]);

        $this->draw = Draw::factory()->create([
            'event_id'  => $this->teamEvent->id,
            'published' => false,
            'locked'    => false,
        ]);

        $this->superUser    = User::factory()->create()->assignRole('super-user');
        $this->ordinaryUser = User::factory()->create();

        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $this->teamEvent->id,
            'user_id'  => $this->admin->id,
        ]);

        $this->adminOther = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $this->otherEvent->id,
            'user_id'  => $this->adminOther->id,
        ]);
    }

    // ── scheduleData ────────────────────────────────────────────────────────

    public function test_guest_cannot_view_schedule_data(): void
    {
        $this->getJson(route('backend.team-schedule.data', $this->draw))
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_view_schedule_data(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson(route('backend.team-schedule.data', $this->draw))
            ->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_view_schedule_data(): void
    {
        $this->actingAs($this->adminOther)
            ->getJson(route('backend.team-schedule.data', $this->draw))
            ->assertForbidden();
    }

    public function test_event_admin_can_view_schedule_data(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('backend.team-schedule.data', $this->draw));

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_super_user_can_view_schedule_data(): void
    {
        $response = $this->actingAs($this->superUser)
            ->getJson(route('backend.team-schedule.data', $this->draw));

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    // ── saveFixture ─────────────────────────────────────────────────────────

    public function test_guest_cannot_save_fixture(): void
    {
        $this->postJson(route('backend.team-schedule.save', $this->draw), [])
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_save_fixture(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('backend.team-schedule.save', $this->draw), [])
            ->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_save_fixture(): void
    {
        $this->actingAs($this->adminOther)
            ->postJson(route('backend.team-schedule.save', $this->draw), [])
            ->assertForbidden();
    }

    // ── autoSchedule ────────────────────────────────────────────────────────

    public function test_guest_cannot_auto_schedule(): void
    {
        $this->postJson(route('backend.team-schedule.auto', $this->draw), [])
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_auto_schedule(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('backend.team-schedule.auto', $this->draw), [])
            ->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_auto_schedule(): void
    {
        $this->actingAs($this->adminOther)
            ->postJson(route('backend.team-schedule.auto', $this->draw), [])
            ->assertForbidden();
    }

    // ── indexAll (event-scoped) ──────────────────────────────────────────────

    public function test_guest_cannot_view_all_schedule(): void
    {
        // Route has no auth middleware; gate denies unauthenticated user with 403
        $this->getJson(route('backend.team-schedule.all', $this->teamEvent))
            ->assertForbidden();
    }

    public function test_ordinary_user_cannot_view_all_schedule(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson(route('backend.team-schedule.all', $this->teamEvent))
            ->assertForbidden();
    }

    public function test_event_admin_can_view_all_schedule(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('backend.team-schedule.all', $this->teamEvent));

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_super_user_can_view_all_schedule(): void
    {
        $response = $this->actingAs($this->superUser)
            ->getJson(route('backend.team-schedule.all', $this->teamEvent));

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    // ── autoAll (event-scoped mutation) ─────────────────────────────────────
    // NOTE: autoAll depends on FixtureService::autoScheduleDraw which is not yet
    // implemented. Once that method exists, add guest/ordinary/admin/super-user
    // tests here following the same pattern as saveFixture above.
}
