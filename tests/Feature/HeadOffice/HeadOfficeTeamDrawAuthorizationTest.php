<?php

namespace Tests\Feature\HeadOffice;

use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamEventFormatRubber;
use App\Models\TeamFixture;
use App\Models\TeamPlayer;
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

    public function test_event_admin_can_create_dummy_fixtures_when_no_teams_exist(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson($this->createDrawUrl(), $this->createPayload());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('draw.placeholder_fixtures', 1);

        $this->assertDatabaseCount('draws', 1);
        $this->assertDatabaseCount('team_fixtures', 1);
    }

    public function test_event_admin_can_create_placeholder_fixture_with_team_members_when_only_one_team_exists(): void
    {
        $team = Team::factory()->create(['category_event_id' => $this->categoryEventId]);
        $player = Player::factory()->create();

        TeamPlayer::create([
            'team_id'   => $team->id,
            'player_id' => $player->id,
            'rank'      => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson($this->createDrawUrl(), $this->createPayload());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('draw.placeholder_fixtures', 1);

        $fixtureId = TeamFixture::query()->latest('id')->value('id');

        $this->assertDatabaseHas('team_fixture_players', [
            'team_fixture_id' => $fixtureId,
            'team1_id'        => $player->id,
            'team2_id'        => null,
        ]);
    }

    public function test_event_admin_can_create_placeholder_doubles_fixtures_for_all_team_players_when_only_one_team_exists(): void
    {
        $doublesDrawTypeId = DB::table('draw_types')->insertGetId([
            'drawTypeName' => 'Doubles',
            'btn_color'    => 'primary',
            'type'         => 'team',
        ]);

        $team = Team::factory()->create(['category_event_id' => $this->categoryEventId]);
        $players = collect([
            Player::factory()->create(),
            Player::factory()->create(),
            Player::factory()->create(),
            Player::factory()->create(),
        ]);

        $players->values()->each(function (Player $player, int $index) use ($team) {
            TeamPlayer::create([
                'team_id'   => $team->id,
                'player_id' => $player->id,
                'rank'      => $index + 1,
            ]);
        });

        $response = $this->actingAs($this->admin)->postJson($this->createDrawUrl(), [
            'draw_type_id' => $doublesDrawTypeId,
            'drawName'     => 'Doubles Draw',
            'category_ids' => [$this->categoryEventId],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $drawId = Draw::query()->latest('id')->value('id');
        $fixtureCount = TeamFixture::query()->where('draw_id', $drawId)->count();

        $this->assertGreaterThanOrEqual(2, $fixtureCount);
        $this->assertDatabaseHas('team_fixtures', [
            'draw_id' => $drawId,
            'match_nr' => 1,
        ]);
        $this->assertDatabaseHas('team_fixtures', [
            'draw_id' => $drawId,
            'match_nr' => 2,
        ]);

        $assignedPlayerIds = DB::table('team_fixture_players')
            ->whereIn('team_fixture_id', TeamFixture::query()->where('draw_id', $drawId)->pluck('id'))
            ->pluck('team1_id')
            ->filter()
            ->values();

        $this->assertTrue($players->pluck('id')->intersect($assignedPlayerIds)->count() >= 2);
    }

    public function test_event_admin_can_create_team_draw_with_team_members_and_auto_assigned_fixture(): void
    {
        $format = TeamEventFormat::create([
            'event_id'           => $this->teamEvent->id,
            'name'               => 'Default Team Format',
            'min_roster_size'    => 1,
            'max_roster_size'    => 12,
            'allow_player_reuse' => false,
            'is_default'         => true,
        ]);

        TeamEventFormatRubber::create([
            'format_id'             => $format->id,
            'sequence'              => 1,
            'rubber_code'           => 'singles',
            'name'                  => 'Singles 1',
            'gender_rule'           => null,
            'player_count_per_team' => 1,
            'is_required'           => true,
        ]);

        $team1 = Team::factory()->create(['category_event_id' => $this->categoryEventId]);
        $team2 = Team::factory()->create(['category_event_id' => $this->categoryEventId]);

        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        TeamPlayer::create([
            'team_id' => $team1->id,
            'player_id' => $p1->id,
            'rank' => 1,
        ]);
        TeamPlayer::create([
            'team_id' => $team2->id,
            'player_id' => $p2->id,
            'rank' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson($this->createDrawUrl(), $this->createPayload());

        $response->assertOk()
            ->assertJsonPath('success', true);

        $drawId = Draw::query()->latest('id')->value('id');

        $this->assertDatabaseHas('team_ties', [
            'draw_id' => $drawId,
            'round_nr' => 1,
            'tie_nr' => 1,
        ]);

        $this->assertDatabaseHas('team_fixtures', [
            'draw_id' => $drawId,
            'match_nr' => 1,
            'numSets' => 3,
        ]);

        $this->assertDatabaseHas('team_fixture_players', [
            'team_fixture_id' => TeamFixture::query()->where('draw_id', $drawId)->value('id'),
            'team1_id' => $p1->id,
            'team2_id' => $p2->id,
        ]);
    }
}
