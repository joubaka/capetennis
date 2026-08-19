<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Authorization tests for FixtureController.
 *
 * Routes under test:
 *   POST  fixture/deleteResult/{id}           (deleteResult)
 *   POST  fixture/deleteIndResult/{id}        (deleteIndResult)
 *   POST  fixture/update/player/names/{id}    (updatePlayersNames)
 *   GET   fixture/pdf/create                  (fixtures_create_pdf)
 *   POST  fixtures/create/auto/{draw_id}      (autoScheduleFixtures)
 *
 * Expected access:
 *   - Guest                               → 401/403 depending on route middleware
 *   - Ordinary user                       → 403
 *   - Admin of correct event              → permitted
 *   - Admin of a DIFFERENT event          → 403
 *   - Super-user                          → permitted (Gate::before bypass)
 */
class FixtureControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private User $admin;
    private User $adminOther;
    private User $ordinaryUser;

    private Event $event;
    private Event $otherEvent;
    private Draw  $draw;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor',   'guard_name' => 'web']);

        DB::table('eventtypes')->insert([
            ['id' => 1, 'name' => 'Individual', 'type' => EventType::INDIVIDUAL],
            ['id' => 3, 'name' => 'team event',  'type' => EventType::TEAM],
        ]);

        $this->event      = Event::factory()->create(['eventType' => 1]);
        $this->otherEvent = Event::factory()->create(['eventType' => 1]);

        $this->draw = Draw::factory()->create([
            'event_id'  => $this->event->id,
            'published' => false,
            'locked'    => false,
        ]);

        $this->superUser    = User::factory()->create()->assignRole('super-user');
        $this->ordinaryUser = User::factory()->create();

        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $this->event->id,
            'user_id'  => $this->admin->id,
        ]);

        $this->adminOther = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $this->otherEvent->id,
            'user_id'  => $this->adminOther->id,
        ]);
    }

    // ── deleteResult (team fixture result) ──────────────────────────────────

    public function test_guest_cannot_delete_result(): void
    {
        $this->postJson(route('draw.delete.result', 999))
            ->assertStatus(401);
    }

    public function test_ordinary_user_cannot_delete_result(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('draw.delete.result', 999))
            ->assertForbidden();
    }

    // ── deleteIndResult ──────────────────────────────────────────────────────

    public function test_guest_cannot_delete_ind_result(): void
    {
        $this->postJson(route('draw.delete.ind.result', 999))
            ->assertStatus(401);
    }

    public function test_ordinary_user_cannot_delete_ind_result(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('draw.delete.ind.result', 999))
            ->assertForbidden();
    }

    // ── updatePlayersNames ───────────────────────────────────────────────────

    public function test_guest_cannot_update_player_names(): void
    {
        $this->postJson(route('update-player-names', $this->draw->id))
            ->assertStatus(401);
    }

    public function test_ordinary_user_cannot_update_player_names(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('update-player-names', $this->draw->id))
            ->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_update_player_names(): void
    {
        $this->actingAs($this->adminOther)
            ->postJson(route('update-player-names', $this->draw->id))
            ->assertForbidden();
    }

    public function test_event_admin_can_update_player_names(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('update-player-names', $this->draw->id));

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_super_user_can_update_player_names(): void
    {
        $response = $this->actingAs($this->superUser)
            ->postJson(route('update-player-names', $this->draw->id));

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    // ── autoScheduleFixtures ─────────────────────────────────────────────────

    public function test_guest_cannot_auto_schedule_fixtures(): void
    {
        $this->postJson(route('fixtures.auto.schedule', $this->draw->id), [
            'drawId' => $this->draw->id,
        ])->assertStatus(401);
    }

    public function test_ordinary_user_cannot_auto_schedule_fixtures(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('fixtures.auto.schedule', $this->draw->id), [
                'drawId' => $this->draw->id,
            ])->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_auto_schedule_fixtures(): void
    {
        $this->actingAs($this->adminOther)
            ->postJson(route('fixtures.auto.schedule', $this->draw->id), [
                'drawId' => $this->draw->id,
            ])->assertForbidden();
    }
}
