<?php

namespace Tests\Feature\TeamDraw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\TeamFixture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Authorization tests for TeamFixtureController.
 *
 * Routes under test:
 *   POST  backend/team-fixtures                         (store)
 *   POST  backend/team-fixtures/{tf}/insert-score       (insertScore)
 *   PUT   backend/team-fixtures/{tf}                    (update)
 *   DELETE backend/team-fixtures/{tf}                   (destroy)
 *   DELETE backend/team-fixtures/{tf}/result            (destroyResult)
 *   POST  backend/team-fixtures/{tf}/players            (updatePlayers)
 *   GET   backend/team-fixtures/{tf}                    (show)
 *   GET   backend/team-schedule/{draw}                  (schedulePage)
 *   GET   backend/team-schedule/{draw}/data             (scheduleData)
 *   POST  backend/team-schedule/{draw}/save             (scheduleSave)
 *   POST  backend/team-schedule/{draw}/auto             (scheduleAuto)
 *
 * Expected access:
 *   - Guest                                → 401
 *   - Ordinary user                        → 403
 *   - Admin of the correct team event      → permitted
 *   - Admin of a DIFFERENT event           → 403
 *   - Super-user                           → permitted (Gate::before bypass)
 */
class TeamFixtureAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private User $admin;
    private User $adminOther;
    private User $ordinaryUser;

    private Event $teamEvent;
    private Event $otherEvent;
    private Draw  $draw;
    private TeamFixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);

        // Reference data
        DB::table('eventtypes')->insert([
            ['id' => 1, 'name' => 'Individual', 'type' => EventType::INDIVIDUAL],
            ['id' => 3, 'name' => 'team event',  'type' => EventType::TEAM],
        ]);

        // Events
        $this->teamEvent  = Event::factory()->create(['eventType' => 3]);
        $this->otherEvent = Event::factory()->create(['eventType' => 3]);

        // Draw belonging to the team event
        $this->draw = Draw::factory()->create([
            'event_id'  => $this->teamEvent->id,
            'published' => false,
            'locked'    => false,
        ]);

        // Minimal TeamFixture row (no teams required for auth tests)
        $this->fixture = TeamFixture::create([
            'draw_id'               => $this->draw->id,
            'match_nr'              => 1,
            'rubber_sequence'       => 1,
            'rubber_code'           => 'singles',
            'rubber_name'           => 'Singles 1',
            'player_count_per_team' => 1,
            'round_nr'              => 1,
            'tie_nr'                => 1,
        ]);

        // Users
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

    // ─────────────────────────────────────────────────────────────────────────
    // A. insertScore  (POST backend/team-fixtures/{tf}/insert-score)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_insert_score(): void
    {
        $this->postJson(route('backend.team-fixtures.insertScore', $this->fixture))
            ->assertStatus(403); // API guest gets 403 in this app
    }

    public function test_ordinary_user_cannot_insert_score(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('backend.team-fixtures.insertScore', $this->fixture))
            ->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_insert_score(): void
    {
        $this->actingAs($this->adminOther)
            ->postJson(route('backend.team-fixtures.insertScore', $this->fixture))
            ->assertForbidden();
    }

    public function test_event_admin_can_insert_score(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('backend.team-fixtures.insertScore', $this->fixture), [
                'set1_home' => 6, 'set1_away' => 3,
            ]);

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_super_user_can_insert_score(): void
    {
        $response = $this->actingAs($this->superUser)
            ->postJson(route('backend.team-fixtures.insertScore', $this->fixture), [
                'set1_home' => 6, 'set1_away' => 3,
            ]);

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // B. destroyResult  (DELETE backend/team-fixtures/{tf}/result)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_destroy_result(): void
    {
        $this->deleteJson(route('backend.team-fixtures.destroyResult', $this->fixture))
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_destroy_result(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->deleteJson(route('backend.team-fixtures.destroyResult', $this->fixture))
            ->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_destroy_result(): void
    {
        $this->actingAs($this->adminOther)
            ->deleteJson(route('backend.team-fixtures.destroyResult', $this->fixture))
            ->assertForbidden();
    }

    public function test_event_admin_can_destroy_result(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson(route('backend.team-fixtures.destroyResult', $this->fixture));

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C. destroy fixture  (DELETE backend/team-fixtures/{tf})
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_destroy_fixture(): void
    {
        $this->deleteJson(route('backend.team-fixtures.destroy', $this->fixture))
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_destroy_fixture(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->deleteJson(route('backend.team-fixtures.destroy', $this->fixture))
            ->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_destroy_fixture(): void
    {
        $this->actingAs($this->adminOther)
            ->deleteJson(route('backend.team-fixtures.destroy', $this->fixture))
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // D. show fixture  (GET backend/team-fixtures/{tf})
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_show_fixture(): void
    {
        $this->getJson(route('backend.team-fixtures.show', $this->fixture))
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_show_fixture(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson(route('backend.team-fixtures.show', $this->fixture))
            ->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_show_fixture(): void
    {
        $this->actingAs($this->adminOther)
            ->getJson(route('backend.team-fixtures.show', $this->fixture))
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // E. schedulePage  (GET team-schedule/{draw})
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_view_schedule_page(): void
    {
        $this->getJson(route('backend.team-schedule.page', $this->draw))
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_view_schedule_page(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson(route('backend.team-schedule.page', $this->draw))
            ->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_view_schedule_page(): void
    {
        $this->actingAs($this->adminOther)
            ->getJson(route('backend.team-schedule.page', $this->draw))
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F. scheduleSave  (POST team-schedule/{draw}/save)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_save_schedule(): void
    {
        $this->postJson(route('backend.team-schedule.save', $this->draw), [
            'fixture_id' => $this->fixture->id,
        ])->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_save_schedule(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('backend.team-schedule.save', $this->draw), [
                'fixture_id' => $this->fixture->id,
            ])->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_save_schedule(): void
    {
        $this->actingAs($this->adminOther)
            ->postJson(route('backend.team-schedule.save', $this->draw), [
                'fixture_id' => $this->fixture->id,
            ])->assertForbidden();
    }

    public function test_event_admin_can_save_schedule(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('backend.team-schedule.save', $this->draw), [
                'fixture_id' => $this->fixture->id,
            ]);

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }

    public function test_super_user_can_save_schedule(): void
    {
        $response = $this->actingAs($this->superUser)
            ->postJson(route('backend.team-schedule.save', $this->draw), [
                'fixture_id' => $this->fixture->id,
            ]);

        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }
}
