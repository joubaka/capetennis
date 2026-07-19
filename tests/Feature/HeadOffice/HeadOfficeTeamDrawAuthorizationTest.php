<?php

namespace Tests\Feature\HeadOffice;

use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use App\Services\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * HeadOffice team-draw route authorization tests.
 *
 * Routes under test:
 *   POST backend/event/{event}/create-team-draw
 *   POST backend/event/{event}/preview-team-draw
 *
 * Expected access:
 *   - Guest                          → 401/redirect
 *   - Authenticated ordinary user    → 403
 *   - Admin of a DIFFERENT event     → 403
 *   - Admin of the correct event     → 200/201
 *   - Super-user                     → 200/201
 */
class HeadOfficeTeamDrawAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private User $admin;
    private User $adminOther;
    private User $ordinaryUser;

    private Event $teamEvent;
    private Event $otherTeamEvent;

    private int $drawTypeId;
    private int $categoryEventId;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);

        // Seed eventtypes reference data
        DB::table('eventtypes')->insert([
            ['id' => 1, 'name' => 'Individual', 'type' => EventType::INDIVIDUAL],
            ['id' => 3, 'name' => 'team event', 'type' => EventType::TEAM],
        ]);

        // Seed a draw type
        $this->drawTypeId = DB::table('draw_types')->insertGetId([
            'drawTypeName' => 'Round Robin',
            'btn_color'    => 'primary',
            'type'         => 'team',
        ]);

        // Events
        $this->teamEvent      = Event::factory()->create(['eventType' => 3]);
        $this->otherTeamEvent = Event::factory()->create(['eventType' => 3]);

        // Category for the team event
        $this->categoryEventId = CategoryEvent::factory()->create([
            'event_id' => $this->teamEvent->id,
        ])->id;

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
            'event_id' => $this->otherTeamEvent->id,
            'user_id'  => $this->adminOther->id,
        ]);

        FeatureFlags::enable(FeatureFlags::TEAM_DRAW_V2);
    }

    protected function tearDown(): void
    {
        FeatureFlags::clearOverride(FeatureFlags::TEAM_DRAW_V2);
        parent::tearDown();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function createPayload(): array
    {
        return [
            'draw_type_id' => $this->drawTypeId,
            'drawName'     => 'Test Draw',
            'category_ids' => [$this->categoryEventId],
        ];
    }

    private function createDrawUrl(): string
    {
        return route('headoffice.createSingleDraw.team', $this->teamEvent);
    }

    private function previewDrawUrl(): string
    {
        return route('headoffice.previewTeamDraw', $this->teamEvent);
    }

    // ─── A. Authentication — guest ────────────────────────────────────────

    public function test_guest_cannot_create_team_draw(): void
    {
        $response = $this->postJson($this->createDrawUrl(), $this->createPayload());

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_preview_team_draw(): void
    {
        $response = $this->postJson($this->previewDrawUrl(), $this->createPayload());

        $response->assertUnauthorized();
    }

    // ─── B. Ordinary user ─────────────────────────────────────────────────

    public function test_ordinary_user_cannot_create_team_draw(): void
    {
        $response = $this->actingAs($this->ordinaryUser)
            ->postJson($this->createDrawUrl(), $this->createPayload());

        $response->assertForbidden();
    }

    public function test_ordinary_user_cannot_preview_team_draw(): void
    {
        $response = $this->actingAs($this->ordinaryUser)
            ->postJson($this->previewDrawUrl(), $this->createPayload());

        $response->assertForbidden();
    }

    // ─── C. Admin of a different event ────────────────────────────────────

    public function test_admin_of_other_event_cannot_create_team_draw(): void
    {
        $response = $this->actingAs($this->adminOther)
            ->postJson($this->createDrawUrl(), $this->createPayload());

        $response->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_preview_team_draw(): void
    {
        $response = $this->actingAs($this->adminOther)
            ->postJson($this->previewDrawUrl(), $this->createPayload());

        $response->assertForbidden();
    }

    // ─── D. Admin of the correct event ────────────────────────────────────

    public function test_event_admin_can_create_team_draw(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson($this->createDrawUrl(), $this->createPayload());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['draw' => ['id', 'name']]);
    }

    public function test_event_admin_can_preview_team_draw(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson($this->previewDrawUrl(), $this->createPayload());

        $response->assertOk()
            ->assertJsonPath('preview', true)
            ->assertJsonPath('drawName', 'Test Draw');
    }

    // ─── E. Super-user ────────────────────────────────────────────────────

    public function test_super_user_can_create_team_draw(): void
    {
        $response = $this->actingAs($this->superUser)
            ->postJson($this->createDrawUrl(), $this->createPayload());

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_super_user_can_preview_team_draw(): void
    {
        $response = $this->actingAs($this->superUser)
            ->postJson($this->previewDrawUrl(), $this->createPayload());

        $response->assertOk()
            ->assertJsonPath('preview', true);
    }

    // ─── F. Non-team event is rejected ────────────────────────────────────

    public function test_admin_cannot_create_team_draw_on_individual_event(): void
    {
        $individualEvent = Event::factory()->create(['eventType' => 1]);

        DB::table('event_admins')->insert([
            'event_id' => $individualEvent->id,
            'user_id'  => $this->admin->id,
        ]);

        // Need a category_event for this event for validation to pass
        $catId = CategoryEvent::factory()->create(['event_id' => $individualEvent->id])->id;
        $drawTypeId = $this->drawTypeId;

        $response = $this->actingAs($this->admin)
            ->postJson(
                route('headoffice.createSingleDraw.team', $individualEvent),
                [
                    'draw_type_id' => $drawTypeId,
                    'drawName'     => 'Ind Draw',
                    'category_ids' => [$catId],
                ]
            );

        $response->assertForbidden();
    }

    public function test_admin_cannot_preview_team_draw_on_individual_event(): void
    {
        $individualEvent = Event::factory()->create(['eventType' => 1]);

        DB::table('event_admins')->insert([
            'event_id' => $individualEvent->id,
            'user_id'  => $this->admin->id,
        ]);

        $catId = CategoryEvent::factory()->create(['event_id' => $individualEvent->id])->id;

        $response = $this->actingAs($this->admin)
            ->postJson(
                route('headoffice.previewTeamDraw', $individualEvent),
                [
                    'draw_type_id' => $this->drawTypeId,
                    'drawName'     => 'Ind Preview',
                    'category_ids' => [$catId],
                ]
            );

        $response->assertForbidden();
    }
}
