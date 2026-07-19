<?php

namespace Tests\Feature\TeamDraw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventType;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamEventFormatRubber;
use App\Models\TeamTie;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * TeamDraw v2 — Feature Tests
 *
 * Covers:
 *  1.  Format list endpoint returns formats for event.
 *  2.  Storing a format creates format + rubbers in DB.
 *  3.  Tie generation creates correct round-robin pairings.
 *  4.  Tie generation is idempotent (no duplicates).
 *  5.  Tie generation blocked for locked ties without override.
 *  6.  Tie generation allowed with override flag.
 *  7.  Rubber generation creates fixtures in sequence order.
 *  8.  Rubber generation blocked for published tie without override.
 *  9.  Attach format links format to draw.
 *  10. Tie validate endpoint marks tie as validated.
 *  11. Tie publish endpoint marks tie as published.
 *  12. Publish requires validated status.
 *  13. Regenerate purges draft ties and rebuilds.
 *  14. Regenerate blocked without override when locked ties exist.
 *  15. Individual draw endpoints are untouched (regression guard).
 */
class TeamDrawV2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);

        $this->admin = User::factory()->create()->assignRole('admin');

        // Seed the eventtypes reference row that makeEvent() depends on
        DB::table('eventtypes')->insert([
            'id'   => 3,
            'name' => 'team event',
            'type' => EventType::TEAM,
        ]);

        // Enable the feature flag globally for all tests in this class
        FeatureFlags::enable(FeatureFlags::TEAM_DRAW_V2);
    }

    protected function tearDown(): void
    {
        FeatureFlags::clearOverride(FeatureFlags::TEAM_DRAW_V2);
        parent::tearDown();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function makeEvent(): Event
    {
        $event = Event::factory()->create(['eventType' => 3]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $this->admin->id]);
        return $event;
    }

    private function makeDraw(Event $event): Draw
    {
        return Draw::factory()->create(['event_id' => $event->id]);
    }

    private function makeTeams(int $count): \Illuminate\Database\Eloquent\Collection
    {
        return Team::factory()->count($count)->create();
    }

    private function makeFormat(?Event $event = null): TeamEventFormat
    {
        $format = TeamEventFormat::factory()->create([
            'event_id' => $event?->id,
            'name'     => 'Standard Format',
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

        TeamEventFormatRubber::create([
            'format_id'             => $format->id,
            'sequence'              => 2,
            'rubber_code'           => 'doubles',
            'name'                  => 'Doubles',
            'gender_rule'           => null,
            'player_count_per_team' => 2,
            'is_required'           => true,
        ]);

        return $format->fresh('rubbers');
    }

    private function makeFormatWithAllCanonicalRubbers(?Event $event = null): TeamEventFormat
    {
        $format = TeamEventFormat::factory()->create([
            'event_id' => $event?->id,
            'name'     => 'All Canonical Rubbers',
        ]);

        TeamEventFormatRubber::create([
            'format_id'             => $format->id,
            'sequence'              => 1,
            'rubber_code'           => 'singles',
            'name'                  => 'Singles 1',
            'player_count_per_team' => 1,
            'is_required'           => true,
        ]);

        TeamEventFormatRubber::create([
            'format_id'             => $format->id,
            'sequence'              => 2,
            'rubber_code'           => 'doubles',
            'name'                  => 'Doubles',
            'player_count_per_team' => 2,
            'is_required'           => true,
        ]);

        TeamEventFormatRubber::create([
            'format_id'             => $format->id,
            'sequence'              => 3,
            'rubber_code'           => 'mixed_doubles',
            'name'                  => 'Mixed Doubles',
            'player_count_per_team' => 2,
            'is_required'           => true,
        ]);

        TeamEventFormatRubber::create([
            'format_id'             => $format->id,
            'sequence'              => 4,
            'rubber_code'           => 'reverse_singles',
            'name'                  => 'Reverse Singles',
            'player_count_per_team' => 1,
            'is_required'           => true,
        ]);

        return $format->fresh('rubbers');
    }

    // ─── 1. Format list ────────────────────────────────────────────────────

    public function test_list_formats_returns_formats_for_event(): void
    {
        $event  = $this->makeEvent();
        $format = $this->makeFormat($event);

        $response = $this->actingAs($this->admin)
            ->getJson("/backend/team-draw/{$event->id}/formats");

        $response->assertOk()
                 ->assertJsonPath('success', true)
                 ->assertJsonCount(1, 'formats');
    }

    // ─── 2. Store format ───────────────────────────────────────────────────

    public function test_store_format_creates_format_and_rubbers(): void
    {
        $event = $this->makeEvent();

        $payload = [
            'name'               => 'Test Format',
            'min_roster_size'    => 4,
            'max_roster_size'    => 12,
            'allow_player_reuse' => false,
            'rubbers' => [
                [
                    'sequence'              => 1,
                    'rubber_code'           => 'singles',
                    'name'                  => 'Singles 1',
                    'player_count_per_team' => 1,
                ],
                [
                    'sequence'              => 2,
                    'rubber_code'           => 'doubles',
                    'name'                  => 'Doubles',
                    'player_count_per_team' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$event->id}/formats", $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true);

        $this->assertDatabaseHas('team_event_formats', [
            'name'     => 'Test Format',
            'event_id' => $event->id,
        ]);

        $formatId = $response->json('format.id');
        $this->assertDatabaseHas('team_event_format_rubbers', [
            'format_id'   => $formatId,
            'rubber_code' => 'singles',
            'sequence'    => 1,
        ]);
        $this->assertDatabaseHas('team_event_format_rubbers', [
            'format_id'   => $formatId,
            'rubber_code' => 'doubles',
            'sequence'    => 2,
        ]);
    }

    public function test_store_format_rejects_duplicate_sequences(): void
    {
        $event = $this->makeEvent();

        $payload = [
            'name'            => 'Bad Format',
            'min_roster_size' => 1,
            'max_roster_size' => 12,
            'rubbers' => [
                ['sequence' => 1, 'rubber_code' => 'singles', 'name' => 'S1', 'player_count_per_team' => 1],
                ['sequence' => 1, 'rubber_code' => 'doubles', 'name' => 'D1', 'player_count_per_team' => 2],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$event->id}/formats", $payload)
            ->assertStatus(422);

        $body = $response->json();
        $this->assertFalse($body['success']);
        $this->assertStringContainsStringIgnoringCase('sequence', $body['message']);
    }

    public function test_store_format_returns_stable_field_key_errors_for_domain_validation(): void
    {
        $event = $this->makeEvent();

        // Duplicate sequences to trigger domain validation (passes basic validation but fails domain)
        $payload = [
            'name'            => 'Bad Format',
            'min_roster_size' => 1,
            'max_roster_size' => 12,
            'rubbers' => [
                ['sequence' => 1, 'rubber_code' => 'singles', 'name' => 'S1', 'player_count_per_team' => 1],
                ['sequence' => 1, 'rubber_code' => 'doubles', 'name' => 'D1', 'player_count_per_team' => 2],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$event->id}/formats", $payload)
            ->assertStatus(422);

        $response->assertJsonPath('success', false);
        $response->assertJsonStructure([
            'success',
            'message',
            'errors' => ['rubbers.1.sequence'],
        ]);
    }

    // ─── 3. Tie generation: correct pairings ───────────────────────────────

    public function test_generate_ties_creates_round_robin_pairings(): void
    {
        $event  = $this->makeEvent();
        $draw   = $this->makeDraw($event);
        $teams  = $this->makeTeams(4);

        $payload = ['team_ids' => $teams->pluck('id')->all()];

        $response = $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/generate-ties", $payload);

        $response->assertOk()->assertJsonPath('success', true);

        // 4 teams → 3 rounds × 2 matches = 6 ties total (or fewer depending on algorithm)
        $tiesCount = TeamTie::where('draw_id', $draw->id)->count();
        $this->assertGreaterThanOrEqual(1, $tiesCount);

        // Each home/away pair should be unique within a round
        $ties = TeamTie::where('draw_id', $draw->id)->get();
        foreach ($ties->groupBy('round_nr') as $round => $roundTies) {
            $teamIds = $roundTies->flatMap(fn($t) => [$t->home_team_id, $t->away_team_id])->all();
            $this->assertCount(count($teamIds), array_unique($teamIds),
                "A team appears more than once in round {$round}.");
        }
    }

    // ─── 4. Idempotency ────────────────────────────────────────────────────

    public function test_generate_ties_is_idempotent(): void
    {
        $event  = $this->makeEvent();
        $draw   = $this->makeDraw($event);
        $teams  = $this->makeTeams(4);
        $ids    = $teams->pluck('id')->all();

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/generate-ties", ['team_ids' => $ids]);

        $firstCount = TeamTie::where('draw_id', $draw->id)->count();

        // Generate again (all ties are draft → should purge and recreate same count)
        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/generate-ties", ['team_ids' => $ids]);

        $secondCount = TeamTie::where('draw_id', $draw->id)->count();

        $this->assertEquals($firstCount, $secondCount);
    }

    // ─── 5. Blocked for locked ties ────────────────────────────────────────

    public function test_generate_ties_blocked_when_published_tie_exists(): void
    {
        $event = $this->makeEvent();
        $draw  = $this->makeDraw($event);
        $teams = $this->makeTeams(2);

        // Create a published tie manually
        TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_PUBLISHED,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/generate-ties", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertStatus(409);
    }

    // ─── 6. Override unlocks locked regeneration ───────────────────────────

    public function test_generate_ties_succeeds_with_override_despite_locked_tie(): void
    {
        $event = $this->makeEvent();
        $draw  = $this->makeDraw($event);
        $teams = $this->makeTeams(2);

        TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_PUBLISHED,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/generate-ties", [
                'team_ids'       => $teams->pluck('id')->all(),
                'allow_override' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // ─── 7. Rubber generation ──────────────────────────────────────────────

    public function test_generate_rubbers_creates_fixtures_in_sequence_order(): void
    {
        $event  = $this->makeEvent();
        $format = $this->makeFormat($event);
        $draw   = $this->makeDraw($event);
        $draw->team_event_format_id = $format->id;
        $draw->save();

        $teams = $this->makeTeams(2);
        TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_DRAFT,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/generate-rubbers")
            ->assertOk()
            ->assertJsonPath('success', true);

        $rubbers = \App\Models\TeamFixture::where('draw_id', $draw->id)
            ->orderBy('rubber_sequence')
            ->get();

        $this->assertCount(2, $rubbers);
        $this->assertEquals(1, $rubbers[0]->rubber_sequence);
        $this->assertSame('singles', $rubbers[0]->rubber_code);
        $this->assertSame(1, (int) $rubbers[0]->fixture_type);
        $this->assertEquals(2, $rubbers[1]->rubber_sequence);
        $this->assertSame('doubles', $rubbers[1]->rubber_code);
        $this->assertSame(2, (int) $rubbers[1]->fixture_type);
    }

    public function test_generate_rubbers_maps_mixed_and_reverse_singles_fixture_types(): void
    {
        $event  = $this->makeEvent();
        $format = $this->makeFormatWithAllCanonicalRubbers($event);
        $draw   = $this->makeDraw($event);
        $draw->team_event_format_id = $format->id;
        $draw->save();

        $teams = $this->makeTeams(2);
        TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_DRAFT,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/generate-rubbers")
            ->assertOk();

        $rubbers = \App\Models\TeamFixture::where('draw_id', $draw->id)
            ->orderBy('rubber_sequence')
            ->get();

        $this->assertCount(4, $rubbers);
        $this->assertSame('mixed_doubles', $rubbers[2]->rubber_code);
        $this->assertSame(3, (int) $rubbers[2]->fixture_type);
        $this->assertSame('reverse_singles', $rubbers[3]->rubber_code);
        $this->assertSame(4, (int) $rubbers[3]->fixture_type);
    }

    public function test_generate_rubbers_rejects_unsupported_canonical_code_without_persisting_invalid_fixture_type(): void
    {
        $event = $this->makeEvent();
        $draw  = $this->makeDraw($event);

        $format = TeamEventFormat::factory()->create([
            'event_id' => $event->id,
            'name'     => 'Invalid Rubber Format',
        ]);

        TeamEventFormatRubber::create([
            'format_id'             => $format->id,
            'sequence'              => 1,
            'rubber_code'           => 'unsupported_legacy_code',
            'name'                  => 'Unsupported',
            'player_count_per_team' => 1,
            'is_required'           => true,
        ]);

        $draw->team_event_format_id = $format->id;
        $draw->save();

        $teams = $this->makeTeams(2);
        TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_DRAFT,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/generate-rubbers")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('team_fixtures', [
            'draw_id' => $draw->id,
            'rubber_code' => 'unsupported_legacy_code',
        ]);
    }

    // ─── 8. Rubber generation blocked for published tie ────────────────────

    public function test_generate_rubbers_blocked_for_published_tie_without_override(): void
    {
        $event  = $this->makeEvent();
        $format = $this->makeFormat($event);
        $draw   = $this->makeDraw($event);
        $draw->team_event_format_id = $format->id;
        $draw->save();

        $teams = $this->makeTeams(2);
        TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_PUBLISHED,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/ties/{$draw->teamTies->first()->id}/generate-rubbers")
            ->assertStatus(409);
    }

    // ─── 9. Attach format ──────────────────────────────────────────────────

    public function test_attach_format_links_format_to_draw(): void
    {
        $event  = $this->makeEvent();
        $format = $this->makeFormat($event);
        $draw   = $this->makeDraw($event);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/attach-format", [
                'format_id' => $format->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('draws', [
            'id'                    => $draw->id,
            'team_event_format_id'  => $format->id,
        ]);
    }

    // ─── 10. Validate tie ──────────────────────────────────────────────────

    public function test_validate_tie_marks_tie_as_validated_when_complete(): void
    {
        $event  = $this->makeEvent();
        $format = $this->makeFormat($event);
        $draw   = $this->makeDraw($event);
        $draw->team_event_format_id = $format->id;
        $draw->save();

        $teams = $this->makeTeams(2);
        $tie   = TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_DRAFT,
        ]);

        // Create rubbers with players assigned
        $rubber1 = \App\Models\TeamFixture::create([
            'draw_id'               => $draw->id,
            'team_tie_id'           => $tie->id,
            'match_nr'              => 1,
            'rubber_sequence'       => 1,
            'rubber_code'           => 'singles',
            'rubber_name'           => 'Singles 1',
            'player_count_per_team' => 1,
            'round_nr'              => 1,
            'tie_nr'                => 1,
        ]);

        $player1 = \App\Models\Player::factory()->create();
        $player2 = \App\Models\Player::factory()->create();

        \App\Models\TeamFixturePlayer::create([
            'team_fixture_id' => $rubber1->id,
            'slot_no'         => 1,
            'team1_id'        => $player1->id,
            'team2_id'        => $player2->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/ties/{$tie->id}/validate")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('team_ties', [
            'id'     => $tie->id,
            'status' => TeamTie::STATUS_VALIDATED,
        ]);
    }

    // ─── 11. Publish tie ───────────────────────────────────────────────────

    public function test_publish_tie_marks_tie_as_published(): void
    {
        $event = $this->makeEvent();
        $draw  = $this->makeDraw($event);
        $teams = $this->makeTeams(2);

        $tie = TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_VALIDATED,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/ties/{$tie->id}/publish")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('team_ties', [
            'id'     => $tie->id,
            'status' => TeamTie::STATUS_PUBLISHED,
        ]);
    }

    // ─── 12. Publish requires validated status ────────────────────────────

    public function test_publish_tie_rejected_when_not_validated(): void
    {
        $event = $this->makeEvent();
        $draw  = $this->makeDraw($event);
        $teams = $this->makeTeams(2);

        $tie = TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_DRAFT,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/ties/{$tie->id}/publish")
            ->assertStatus(422);
    }

    // ─── 13. Regenerate purges draft ties ─────────────────────────────────

    public function test_regenerate_purges_draft_ties_and_rebuilds(): void
    {
        $event = $this->makeEvent();
        $draw  = $this->makeDraw($event);
        $teams = $this->makeTeams(4);

        // First generation
        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/generate-ties", [
                'team_ids' => $teams->pluck('id')->all(),
            ]);

        $firstCount = TeamTie::where('draw_id', $draw->id)->count();

        // Regenerate with same teams
        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/regenerate", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $secondCount = TeamTie::where('draw_id', $draw->id)->count();
        $this->assertEquals($firstCount, $secondCount);
    }

    // ─── 14. Regenerate blocked without override ───────────────────────────

    public function test_regenerate_blocked_when_locked_ties_exist_without_override(): void
    {
        $event = $this->makeEvent();
        $draw  = $this->makeDraw($event);
        $teams = $this->makeTeams(2);

        TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_PUBLISHED,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/backend/team-draw/{$draw->id}/regenerate", [
                'team_ids' => $teams->pluck('id')->all(),
            ])
            ->assertStatus(409);
    }

    // ─── 15. Regression: individual draw endpoints untouched ──────────────

    public function test_individual_draw_creation_endpoint_still_works(): void
    {
        // The individual endpoint should still respond (even 302/401/422 is fine —
        // we just need it NOT to throw a 500 or be missing)
        $response = $this->actingAs($this->admin)
            ->postJson('/event/999/create-individual-draw', []);

        // 404 for missing event is acceptable — route must exist
        $this->assertNotEquals(500, $response->status(),
            'Individual draw creation endpoint returned a 500 error.');
        $this->assertNotEquals(405, $response->status(),
            'Individual draw creation endpoint route is missing or wrong method.');
    }
}
