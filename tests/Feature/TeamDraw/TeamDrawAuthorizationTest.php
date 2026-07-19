<?php

namespace Tests\Feature\TeamDraw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamEventFormatRubber;
use App\Models\TeamTie;
use App\Models\User;
use App\Services\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * TeamDraw Authorization & Isolation Tests
 *
 * Covers:
 *  A.  Authentication — guest receives redirect/401 on every endpoint.
 *  B.  Roles — super-user allowed; permitted admin allowed; ordinary user denied; convenor denied.
 *  C.  Admin event scope — admin for event A cannot act on event B.
 *  D.  Event-type isolation — team-draw endpoints reject individual events.
 *  E.  Cross-event object isolation — format/team/tie from wrong event is rejected.
 *  F.  Object integrity — missing/deleted records return expected status.
 */
class TeamDrawAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private User $admin;
    private User $ordinaryUser;
    private User $convenor;

    private Event $teamEvent;
    private Event $teamEventB;   // second team event (cross-event isolation)
    private Event $individualEvent;

    private Draw $teamDraw;
    private Draw $teamDrawB;
    private Draw $individualDraw;

    private TeamEventFormat $format;
    private TeamEventFormat $formatB;   // format for event B

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor',   'guard_name' => 'web']);

        // Seed minimal eventtypes reference data
        DB::table('eventtypes')->insert([
            ['id' => 1, 'name' => 'Individual', 'type' => EventType::INDIVIDUAL],
            ['id' => 3, 'name' => 'team event', 'type' => EventType::TEAM],
        ]);

        // Users
        $this->superUser    = User::factory()->create()->assignRole('super-user');
        $this->admin        = User::factory()->create()->assignRole('admin');
        $this->ordinaryUser = User::factory()->create();
        $this->convenor     = User::factory()->create()->assignRole('convenor');

        // Events
        $this->teamEvent       = Event::factory()->create(['eventType' => 3]); // team
        $this->teamEventB      = Event::factory()->create(['eventType' => 3]); // team (event B)
        $this->individualEvent = Event::factory()->create(['eventType' => 1]); // individual

        // Grant admin access to teamEvent only (not teamEventB or individualEvent)
        DB::table('event_admins')->insert(['event_id' => $this->teamEvent->id, 'user_id' => $this->admin->id]);

        // Draws
        $this->teamDraw       = Draw::factory()->create(['event_id' => $this->teamEvent->id]);
        $this->teamDrawB      = Draw::factory()->create(['event_id' => $this->teamEventB->id]);
        $this->individualDraw = Draw::factory()->create(['event_id' => $this->individualEvent->id]);

        // Formats
        $this->format  = $this->makeFormat($this->teamEvent);
        $this->formatB = $this->makeFormat($this->teamEventB);

        FeatureFlags::enable(FeatureFlags::TEAM_DRAW_V2);
    }

    protected function tearDown(): void
    {
        FeatureFlags::clearOverride(FeatureFlags::TEAM_DRAW_V2);
        parent::tearDown();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function makeFormat(Event $event): TeamEventFormat
    {
        $format = TeamEventFormat::factory()->create([
            'event_id' => $event->id,
            'name'     => 'Format for ' . $event->id,
        ]);

        TeamEventFormatRubber::create([
            'format_id'             => $format->id,
            'sequence'              => 1,
            'rubber_code'           => 'singles',
            'name'                  => 'Singles 1',
            'player_count_per_team' => 1,
            'is_required'           => true,
        ]);

        return $format->fresh('rubbers');
    }

    private function makeTie(Draw $draw, string $status = TeamTie::STATUS_DRAFT): TeamTie
    {
        $teams = Team::factory()->count(2)->create();
        return TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => $status,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // A. Authentication — guest receives redirect or 401
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function guest_cannot_list_formats(): void
    {
        $this->getJson("/backend/team-draw/{$this->teamEvent->id}/formats")
            ->assertUnauthorized();
    }

    /** @test */
    public function guest_cannot_store_format(): void
    {
        $this->postJson("/backend/team-draw/{$this->teamEvent->id}/formats", [])
            ->assertUnauthorized();
    }

    /** @test */
    public function guest_cannot_attach_format(): void
    {
        $this->postJson("/backend/team-draw/{$this->teamDraw->id}/attach-format", [])
            ->assertUnauthorized();
    }

    /** @test */
    public function guest_cannot_sync_teams(): void
    {
        $this->postJson("/backend/team-draw/{$this->teamDraw->id}/sync-teams", [])
            ->assertUnauthorized();
    }

    /** @test */
    public function guest_cannot_generate_ties(): void
    {
        $this->postJson("/backend/team-draw/{$this->teamDraw->id}/generate-ties", [])
            ->assertUnauthorized();
    }

    /** @test */
    public function guest_cannot_generate_rubbers(): void
    {
        $this->postJson("/backend/team-draw/{$this->teamDraw->id}/generate-rubbers", [])
            ->assertUnauthorized();
    }

    /** @test */
    public function guest_cannot_regenerate(): void
    {
        $this->postJson("/backend/team-draw/{$this->teamDraw->id}/regenerate", [])
            ->assertUnauthorized();
    }

    /** @test */
    public function guest_cannot_generate_rubbers_for_tie(): void
    {
        $tie = $this->makeTie($this->teamDraw);
        $this->postJson("/backend/team-draw/ties/{$tie->id}/generate-rubbers", [])
            ->assertUnauthorized();
    }

    /** @test */
    public function guest_cannot_validate_tie(): void
    {
        $tie = $this->makeTie($this->teamDraw);
        $this->postJson("/backend/team-draw/ties/{$tie->id}/validate")
            ->assertUnauthorized();
    }

    /** @test */
    public function guest_cannot_publish_tie(): void
    {
        $tie = $this->makeTie($this->teamDraw, TeamTie::STATUS_VALIDATED);
        $this->postJson("/backend/team-draw/ties/{$tie->id}/publish")
            ->assertUnauthorized();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // B. Roles — super-user / admin / ordinary / convenor
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function super_user_can_list_formats(): void
    {
        $this->actingAs($this->superUser)
            ->getJson("/backend/team-draw/{$this->teamEvent->id}/formats")
            ->assertOk();
    }

    /** @test */
    public function permitted_admin_can_list_formats(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/backend/team-draw/{$this->teamEvent->id}/formats")
            ->assertOk();
    }

    /** @test */
    public function ordinary_user_receives_403_on_list_formats(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->getJson("/backend/team-draw/{$this->teamEvent->id}/formats")
            ->assertForbidden();
    }

    /** @test */
    public function convenor_receives_403_on_list_formats(): void
    {
        $this->actingAs($this->convenor)
            ->getJson("/backend/team-draw/{$this->teamEvent->id}/formats")
            ->assertForbidden();
    }

    /** @test */
    public function super_user_can_store_format(): void
    {
        $payload = [
            'name'            => 'Su Format',
            'min_roster_size' => 2,
            'max_roster_size' => 10,
            'rubbers' => [
                ['sequence' => 1, 'rubber_code' => 'singles', 'name' => 'S1', 'player_count_per_team' => 1],
            ],
        ];

        $this->actingAs($this->superUser)
            ->postJson("/backend/team-draw/{$this->teamEvent->id}/formats", $payload)
            ->assertStatus(201);
    }

    /** @test */
    public function ordinary_user_receives_403_on_store_format(): void
    {
        $this->actingAs($this->ordinaryUser)
            ->postJson("/backend/team-draw/{$this->teamEvent->id}/formats", [
                'name' => 'X', 'min_roster_size' => 1, 'max_roster_size' => 12,
                'rubbers' => [['sequence' => 1, 'rubber_code' => 'singles', 'name' => 'S', 'player_count_per_team' => 1]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('team_event_formats', ['name' => 'X', 'event_id' => $this->teamEvent->id]);
    }

    /** @test */
    public function permitted_admin_can_generate_ties(): void
    {
        $teams = Team::factory()->count(4)->create();

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->teamDraw->id}/generate-ties", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function ordinary_user_receives_403_on_generate_ties(): void
    {
        $teams = Team::factory()->count(2)->create();

        $this->actingAs($this->ordinaryUser)
            ->postJson("/backend/team-draw/{$this->teamDraw->id}/generate-ties", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('team_ties', ['draw_id' => $this->teamDraw->id]);
    }

    /** @test */
    public function convenor_receives_403_on_generate_ties(): void
    {
        $teams = Team::factory()->count(2)->create();

        $this->actingAs($this->convenor)
            ->postJson("/backend/team-draw/{$this->teamDraw->id}/generate-ties", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('team_ties', ['draw_id' => $this->teamDraw->id]);
    }

    /** @test */
    public function super_user_can_validate_tie(): void
    {
        $this->teamDraw->team_event_format_id = $this->format->id;
        $this->teamDraw->save();

        $tie = $this->makeTie($this->teamDraw);

        $this->actingAs($this->superUser)
            ->postJson("/backend/team-draw/ties/{$tie->id}/validate")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function ordinary_user_receives_403_on_validate_tie(): void
    {
        $tie = $this->makeTie($this->teamDraw);

        $this->actingAs($this->ordinaryUser)
            ->postJson("/backend/team-draw/ties/{$tie->id}/validate")
            ->assertForbidden();

        $this->assertDatabaseHas('team_ties', ['id' => $tie->id, 'status' => TeamTie::STATUS_DRAFT]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C. Admin event scope — admin for event A cannot act on event B
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_for_event_a_cannot_list_formats_for_event_b(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/backend/team-draw/{$this->teamEventB->id}/formats")
            ->assertForbidden();
    }

    /** @test */
    public function admin_for_event_a_cannot_create_format_for_event_b(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->teamEventB->id}/formats", [
                'name' => 'X', 'min_roster_size' => 1, 'max_roster_size' => 12,
                'rubbers' => [['sequence' => 1, 'rubber_code' => 'singles', 'name' => 'S', 'player_count_per_team' => 1]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('team_event_formats', ['name' => 'X', 'event_id' => $this->teamEventB->id]);
    }

    /** @test */
    public function admin_for_event_a_cannot_attach_format_to_draw_in_event_b(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->teamDrawB->id}/attach-format", [
                'format_id' => $this->format->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('draws', [
            'id' => $this->teamDrawB->id,
            'team_event_format_id' => $this->format->id,
        ]);
    }

    /** @test */
    public function admin_for_event_a_cannot_generate_ties_for_draw_in_event_b(): void
    {
        $teams = Team::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->teamDrawB->id}/generate-ties", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('team_ties', ['draw_id' => $this->teamDrawB->id]);
    }

    /** @test */
    public function admin_for_event_a_cannot_validate_tie_in_event_b(): void
    {
        $tie = $this->makeTie($this->teamDrawB);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/ties/{$tie->id}/validate")
            ->assertForbidden();

        $this->assertDatabaseHas('team_ties', ['id' => $tie->id, 'status' => TeamTie::STATUS_DRAFT]);
    }

    /** @test */
    public function admin_for_event_a_cannot_publish_tie_in_event_b(): void
    {
        $tie = $this->makeTie($this->teamDrawB, TeamTie::STATUS_VALIDATED);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/ties/{$tie->id}/publish")
            ->assertForbidden();

        $this->assertDatabaseHas('team_ties', ['id' => $tie->id, 'status' => TeamTie::STATUS_VALIDATED]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // D. Event-type isolation — team-draw endpoints reject individual events
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function list_formats_rejected_for_individual_event(): void
    {
        DB::table('event_admins')->insert(['event_id' => $this->individualEvent->id, 'user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->getJson("/backend/team-draw/{$this->individualEvent->id}/formats")
            ->assertForbidden();
    }

    /** @test */
    public function create_format_rejected_for_individual_event(): void
    {
        DB::table('event_admins')->insert(['event_id' => $this->individualEvent->id, 'user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->individualEvent->id}/formats", [
                'name' => 'Bad', 'min_roster_size' => 1, 'max_roster_size' => 12,
                'rubbers' => [['sequence' => 1, 'rubber_code' => 'singles', 'name' => 'S', 'player_count_per_team' => 1]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('team_event_formats', ['event_id' => $this->individualEvent->id]);
    }

    /** @test */
    public function generate_ties_rejected_for_individual_event_draw(): void
    {
        DB::table('event_admins')->insert(['event_id' => $this->individualEvent->id, 'user_id' => $this->admin->id]);
        $teams = Team::factory()->count(2)->create();

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->individualDraw->id}/generate-ties", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('team_ties', ['draw_id' => $this->individualDraw->id]);
    }

    /** @test */
    public function generate_rubbers_rejected_for_individual_event_draw(): void
    {
        DB::table('event_admins')->insert(['event_id' => $this->individualEvent->id, 'user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->individualDraw->id}/generate-rubbers")
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // E. Cross-event object isolation
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_cannot_attach_format_from_different_event(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->teamDraw->id}/attach-format", [
                'format_id' => $this->formatB->id,  // belongs to event B
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('draws', [
            'id' => $this->teamDraw->id,
            'team_event_format_id' => $this->formatB->id,
        ]);
    }

    /** @test */
    public function validate_tie_belonging_to_another_draw_event_is_rejected(): void
    {
        // admin for event A tries to validate tie in event B draw
        $tieB = $this->makeTie($this->teamDrawB);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/ties/{$tieB->id}/validate")
            ->assertForbidden();

        $this->assertDatabaseHas('team_ties', ['id' => $tieB->id, 'status' => TeamTie::STATUS_DRAFT]);
    }

    /** @test */
    public function generate_rubbers_for_tie_in_another_event_is_rejected(): void
    {
        $tieB = $this->makeTie($this->teamDrawB);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/ties/{$tieB->id}/generate-rubbers")
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F. Object integrity — missing records
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function list_formats_returns_404_for_unknown_event(): void
    {
        $this->actingAs($this->superUser)
            ->getJson('/backend/team-draw/99999/formats')
            ->assertNotFound();
    }

    /** @test */
    public function generate_ties_returns_404_for_unknown_draw(): void
    {
        $this->actingAs($this->superUser)
            ->postJson('/backend/team-draw/99999/generate-ties', ['team_ids' => [1, 2]])
            ->assertNotFound();
    }

    /** @test */
    public function validate_tie_returns_404_for_unknown_tie(): void
    {
        $this->actingAs($this->superUser)
            ->postJson('/backend/team-draw/ties/99999/validate')
            ->assertNotFound();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // G. Cross-event team submission
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function sync_teams_rejects_teams_from_a_different_event(): void
    {
        // Create a category event that belongs to teamEventB (not teamEvent)
        $catEventB = \App\Models\CategoryEvent::factory()->create(['event_id' => $this->teamEventB->id]);
        $foreignTeam = Team::factory()->create(['category_event_id' => $catEventB->id]);

        $localTeam = Team::factory()->create(['category_event_id' => null]); // unscoped

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->teamDraw->id}/sync-teams", [
                'team_ids' => [$foreignTeam->id, $localTeam->id],
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        // Confirm no teams were synced to the draw
        $this->assertDatabaseMissing('draw_teams', [
            'draw_id' => $this->teamDraw->id,
            'team_id' => $foreignTeam->id,
        ]);
    }

    /** @test */
    public function generate_ties_rejects_teams_from_a_different_event(): void
    {
        $catEventB  = \App\Models\CategoryEvent::factory()->create(['event_id' => $this->teamEventB->id]);
        $foreignTeam = Team::factory()->create(['category_event_id' => $catEventB->id]);
        $localTeam  = Team::factory()->create(['category_event_id' => null]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->teamDraw->id}/generate-ties", [
                'team_ids' => [$foreignTeam->id, $localTeam->id],
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('team_ties', ['draw_id' => $this->teamDraw->id]);
    }

    /** @test */
    public function sync_teams_allows_unscoped_teams_with_null_category_event(): void
    {
        $teams = Team::factory()->count(2)->create(['category_event_id' => null]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->teamDraw->id}/sync-teams", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function sync_teams_allows_teams_belonging_to_the_correct_event(): void
    {
        $catEvent = \App\Models\CategoryEvent::factory()->create(['event_id' => $this->teamEvent->id]);
        $teams = Team::factory()->count(2)->create(['category_event_id' => $catEvent->id]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$this->teamDraw->id}/sync-teams", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
