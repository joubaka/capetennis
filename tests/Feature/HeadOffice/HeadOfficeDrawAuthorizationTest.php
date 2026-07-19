<?php

namespace Tests\Feature\HeadOffice;

use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Authorization tests for draw-creation and draw-print routes that are
 * not covered by HeadOfficeTeamDrawAuthorizationTest.
 *
 * Routes under test:
 *   POST headOffice/create/team/fixtures          (createFormatFixturesTeam)
 *   POST backend/event/{event}/create-individual-draw  (createIndividualDraw)
 *   GET  backend/event/{event}/print-draws-data   (printDrawsData)
 *   GET  backend/event/{event}/print-draws-pdf    (printDrawsPdf)
 *
 * Expected access:
 *   - Guest                                    → 401
 *   - Ordinary authenticated user              → 403
 *   - Admin of a DIFFERENT event               → 403 (team routes only)
 *   - Admin / convenor of the correct event    → permitted
 *   - Super-user                               → permitted
 */
class HeadOfficeDrawAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private User $admin;
    private User $convenor;
    private User $adminOther;
    private User $ordinaryUser;

    private Event $teamEvent;
    private Event $individualEvent;
    private Event $otherEvent;

    private int $drawTypeId;
    private int $categoryEventId;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor',   'guard_name' => 'web']);

        // Reference data
        DB::table('eventtypes')->insert([
            ['id' => 1, 'name' => 'Individual', 'type' => EventType::INDIVIDUAL],
            ['id' => 3, 'name' => 'team event', 'type' => EventType::TEAM],
        ]);

        $this->drawTypeId = DB::table('draw_types')->insertGetId([
            'drawTypeName' => 'Round Robin',
            'btn_color'    => 'primary',
            'type'         => 'team',
        ]);

        // Events
        $this->teamEvent       = Event::factory()->create(['eventType' => 3]);
        $this->individualEvent = Event::factory()->create(['eventType' => 1]);
        $this->otherEvent      = Event::factory()->create(['eventType' => 3]);

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
        DB::table('event_admins')->insert([
            'event_id' => $this->individualEvent->id,
            'user_id'  => $this->admin->id,
        ]);

        $this->convenor = User::factory()->create()->assignRole('convenor');
        DB::table('event_admins')->insert([
            'event_id' => $this->individualEvent->id,
            'user_id'  => $this->convenor->id,
        ]);

        $this->adminOther = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $this->otherEvent->id,
            'user_id'  => $this->adminOther->id,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // A. createFormatFixturesTeam  (POST headOffice/create/team/fixtures)
    // ────────────────────────────────────────────────────────────────────────

    private function legacyTeamPayload(): array
    {
        return [
            'category'  => [$this->categoryEventId],
            'event_id'  => $this->teamEvent->id,
            'drawType'  => $this->drawTypeId,
        ];
    }

    public function test_guest_cannot_create_format_fixtures_team(): void
    {
        $this->postJson(route('create.team.fixtures'), $this->legacyTeamPayload())
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_create_format_fixtures_team(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson(route('create.team.fixtures'), $this->legacyTeamPayload())
            ->assertForbidden();
    }

    public function test_admin_of_other_event_cannot_create_format_fixtures_team(): void
    {
        $this->actingAs($this->adminOther)
            ->postJson(route('create.team.fixtures'), $this->legacyTeamPayload())
            ->assertForbidden();
    }

    public function test_event_admin_can_create_format_fixtures_team(): void
    {
        // Authorization passes; fixture creation may return an empty-ish result
        // because no regions are seeded — that is acceptable (2xx or 422 on deeper
        // business validation, not 401/403).
        $response = $this->actingAs($this->admin)
            ->postJson(route('create.team.fixtures'), $this->legacyTeamPayload());

        $this->assertNotEquals(401, $response->status(), 'Should not be 401');
        $this->assertNotEquals(403, $response->status(), 'Should not be 403');
    }

    public function test_super_user_can_create_format_fixtures_team(): void
    {
        $response = $this->actingAs($this->superUser)
            ->postJson(route('create.team.fixtures'), $this->legacyTeamPayload());

        $this->assertNotEquals(401, $response->status(), 'Should not be 401');
        $this->assertNotEquals(403, $response->status(), 'Should not be 403');
    }

    public function test_create_format_fixtures_team_rejected_for_individual_event(): void
    {
        $catId = CategoryEvent::factory()->create(['event_id' => $this->individualEvent->id])->id;

        $this->actingAs($this->admin)
            ->postJson(route('create.team.fixtures'), [
                'category'  => [$catId],
                'event_id'  => $this->individualEvent->id,
                'drawType'  => $this->drawTypeId,
            ])
            ->assertForbidden();
    }

    // ────────────────────────────────────────────────────────────────────────
    // B. createIndividualDraw  (POST backend/event/{event}/create-individual-draw)
    // ────────────────────────────────────────────────────────────────────────

    private function individualDrawUrl(): string
    {
        return route('headoffice.createSingleDraw', $this->individualEvent);
    }

    public function test_guest_cannot_create_individual_draw(): void
    {
        $this->postJson($this->individualDrawUrl(), ['drawName' => 'Test'])
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_create_individual_draw(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson($this->individualDrawUrl(), ['drawName' => 'Test'])
            ->assertForbidden();
    }

    public function test_event_admin_can_create_individual_draw(): void
    {
        $this->actingAs($this->admin)
            ->postJson($this->individualDrawUrl(), ['drawName' => 'Test Draw'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_convenor_can_create_individual_draw(): void
    {
        $this->actingAs($this->convenor)
            ->postJson($this->individualDrawUrl(), ['drawName' => 'Conv Draw'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_super_user_can_create_individual_draw(): void
    {
        $this->actingAs($this->superUser)
            ->postJson($this->individualDrawUrl(), ['drawName' => 'SU Draw'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // ────────────────────────────────────────────────────────────────────────
    // C. printDrawsData  (GET backend/event/{event}/print-draws-data)
    // ────────────────────────────────────────────────────────────────────────

    private function printDataUrl(): string
    {
        return route('headoffice.printDrawsData', $this->individualEvent);
    }

    public function test_guest_cannot_access_print_draws_data(): void
    {
        $this->getJson($this->printDataUrl())
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_access_print_draws_data(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson($this->printDataUrl())
            ->assertForbidden();
    }

    public function test_event_admin_can_access_print_draws_data(): void
    {
        $this->actingAs($this->admin)
            ->getJson($this->printDataUrl())
            ->assertOk()
            ->assertJsonStructure(['draw']);
    }

    public function test_convenor_can_access_print_draws_data(): void
    {
        $this->actingAs($this->convenor)
            ->getJson($this->printDataUrl())
            ->assertOk()
            ->assertJsonStructure(['draw']);
    }

    public function test_super_user_can_access_print_draws_data(): void
    {
        $this->actingAs($this->superUser)
            ->getJson($this->printDataUrl())
            ->assertOk();
    }

    // ────────────────────────────────────────────────────────────────────────
    // D. printDrawsPdf  (GET backend/event/{event}/print-draws-pdf)
    // ────────────────────────────────────────────────────────────────────────

    private function printPdfUrl(): string
    {
        return route('headoffice.printDrawsPdf', $this->individualEvent);
    }

    public function test_guest_cannot_access_print_draws_pdf(): void
    {
        $this->getJson($this->printPdfUrl())
            ->assertUnauthorized();
    }

    public function test_ordinary_user_cannot_access_print_draws_pdf(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson($this->printPdfUrl())
            ->assertForbidden();
    }

    public function test_event_admin_can_access_print_draws_pdf(): void
    {
        // No draw_ids → empty result; we only verify authorization passes (not 401/403).
        $response = $this->actingAs($this->admin)
            ->getJson($this->printPdfUrl());

        $this->assertNotEquals(401, $response->status(), 'Should not be 401');
        $this->assertNotEquals(403, $response->status(), 'Should not be 403');
    }

    public function test_convenor_can_access_print_draws_pdf(): void
    {
        $response = $this->actingAs($this->convenor)
            ->getJson($this->printPdfUrl());

        $this->assertNotEquals(401, $response->status(), 'Should not be 401');
        $this->assertNotEquals(403, $response->status(), 'Should not be 403');
    }

    public function test_super_user_can_access_print_draws_pdf(): void
    {
        $response = $this->actingAs($this->superUser)
            ->getJson($this->printPdfUrl());

        $this->assertNotEquals(401, $response->status(), 'Should not be 401');
        $this->assertNotEquals(403, $response->status(), 'Should not be 403');
    }
}
