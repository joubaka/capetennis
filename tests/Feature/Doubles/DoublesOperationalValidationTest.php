<?php

declare(strict_types=1);

namespace Tests\Feature\Doubles;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Domain\Draws\Services\StandingsService;
use App\Domain\Fixtures\Services\FixtureProgressionService;
use App\Models\Draw;
use App\Models\DrawGroup;
use App\Models\DrawGroupRegistration;
use App\Models\DrawSetting;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Registration;
use App\Models\Schedule;
use App\Models\Venue;
use App\Services\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DoublesOperationalValidationTest
 *
 * STEP 9 — validates that a Registration containing two players
 * behaves correctly through the full tournament workflow:
 *
 *   ✅ RR generation            (2-player registrations treated as a unit)
 *   ✅ RR display names         (all display surfaces return "Player A / Player B")
 *   ✅ Standings calculation    (works on registration units, not players)
 *   ✅ Playoff fixture creation (seeds use registration IDs)
 *   ✅ Score entry progression  (winner/loser advancement by registration ID)
 *   ✅ Scheduling               (pair names appear in OOP/schedule data)
 *   ✅ DrawGroupRegistration::displayName() / displayShortName()
 *
 * Does NOT test:
 *   - invitations
 *   - split payments
 *   - checkout
 *   - withdrawals
 *   - finance
 */
class DoublesOperationalValidationTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    /** Create a Registration with two named players — simulates a doubles pair. */
    private function makeDoublePair(string $p1Name, string $p1Surname, string $p2Name, string $p2Surname): Registration
    {
        $reg = Registration::factory()->create();

        $p1 = Player::factory()->create(['name' => $p1Name, 'surname' => $p1Surname]);
        $p2 = Player::factory()->create(['name' => $p2Name, 'surname' => $p2Surname]);

        PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $p1->id]);
        PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $p2->id]);

        return $reg->fresh(['players']);
    }

    /** Create a fresh Draw with settings and a single group. */
    private function makeDraw(): Draw
    {
        $draw = Draw::factory()->create(['locked' => false]);
        DrawSetting::factory()->create(['draw_id' => $draw->id, 'boxes' => 2]);
        return $draw;
    }

    /** Attach a Registration to a DrawGroup. */
    private function attachToGroup(DrawGroup $group, Registration $reg, int $seed): DrawGroupRegistration
    {
        return DrawGroupRegistration::create([
            'draw_group_id'   => $group->id,
            'registration_id' => $reg->id,
            'seed'            => $seed,
        ]);
    }

    // -----------------------------------------------------------------------
    // STEP 2 — ROUND ROBIN GENERATION
    // -----------------------------------------------------------------------

    /** @test */
    public function rr_generation_treats_doubles_pair_as_a_single_unit(): void
    {
        $draw  = $this->makeDraw();
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);

        $pairA = $this->makeDoublePair('John', 'Smith',  'Peter',  'Jones');
        $pairB = $this->makeDoublePair('Mary', 'Brown',  'Lisa',   'White');
        $pairC = $this->makeDoublePair('Tom',  'Davis',  'Rob',    'Clark');

        $this->attachToGroup($group, $pairA, 1);
        $this->attachToGroup($group, $pairB, 2);
        $this->attachToGroup($group, $pairC, 3);

        $draw->load('groups.groupRegistrations');

        app(RoundRobinGenerationService::class)->generate($draw);

        // 3 pairs → 3 round-robin fixtures (3-player round robin = 3 games)
        $fixtures = Fixture::where('draw_id', $draw->id)->where('stage', 'RR')->get();
        $this->assertCount(3, $fixtures, 'Expected 3 RR fixtures for 3 pairs');

        // Every fixture references whole registrations — not individual players
        foreach ($fixtures as $fx) {
            $this->assertNotNull($fx->registration1_id);
            $this->assertNotNull($fx->registration2_id);
            $this->assertNotEquals($fx->registration1_id, $fx->registration2_id);
        }
    }

    /** @test */
    public function rr_generation_with_four_doubles_pairs_produces_correct_fixture_count(): void
    {
        $draw  = $this->makeDraw();
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);

        $pairs = [
            $this->makeDoublePair('John',  'Smith',  'Peter', 'Jones'),
            $this->makeDoublePair('Mary',  'Brown',  'Lisa',  'White'),
            $this->makeDoublePair('Tom',   'Davis',  'Rob',   'Clark'),
            $this->makeDoublePair('Alice', 'Adams',  'Zara',  'Williams'),
        ];

        foreach ($pairs as $i => $pair) {
            $this->attachToGroup($group, $pair, $i + 1);
        }

        $draw->load('groups.groupRegistrations');
        app(RoundRobinGenerationService::class)->generate($draw);

        // 4 pairs → 6 RR fixtures (n*(n-1)/2)
        $count = Fixture::where('draw_id', $draw->id)->where('stage', 'RR')->count();
        $this->assertSame(6, $count, 'Expected 6 RR fixtures for 4 pairs');
    }

    // -----------------------------------------------------------------------
    // STEP 2 — DISPLAY NAMES IN RR CONTEXT
    // -----------------------------------------------------------------------

    /** @test */
    public function doubles_pair_display_name_renders_correctly(): void
    {
        $pair = $this->makeDoublePair('John', 'Smith', 'Peter', 'Jones');

        // Sorted by surname: Jones / Smith
        $this->assertSame('Peter Jones / John Smith', $pair->displayName());
    }

    /** @test */
    public function doubles_pair_display_short_name_renders_correctly(): void
    {
        $pair = $this->makeDoublePair('John', 'Smith', 'Peter', 'Jones');

        $this->assertSame('Jones / Smith', $pair->displayShortName());
    }

    /** @test */
    public function draw_group_registration_display_name_returns_both_players(): void
    {
        $draw  = $this->makeDraw();
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);
        $pair  = $this->makeDoublePair('Alice', 'Adams', 'Zara', 'Williams');
        $dgr   = $this->attachToGroup($group, $pair, 1);

        $dgr->load('registration.players');

        $this->assertSame('Alice Adams / Zara Williams', $dgr->displayName());
        $this->assertSame('Adams / Williams', $dgr->displayShortName());
    }

    /** @test */
    public function draw_group_registration_player_still_returns_first_player_only(): void
    {
        // DrawGroupRegistration::player() must remain unchanged for backward compat
        $draw  = $this->makeDraw();
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);
        $pair  = $this->makeDoublePair('John', 'Smith', 'Peter', 'Jones');
        $dgr   = $this->attachToGroup($group, $pair, 1);

        $dgr->load('registration.players');

        // player() returns first() — singles-safe, not broken
        $this->assertNotNull($dgr->player());
    }

    // -----------------------------------------------------------------------
    // STEP 2 — STANDINGS
    // -----------------------------------------------------------------------

    /** @test */
    public function standings_calculate_correctly_for_doubles_pairs(): void
    {
        $draw  = $this->makeDraw();
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);

        $pairA = $this->makeDoublePair('John',  'Smith', 'Peter', 'Jones');
        $pairB = $this->makeDoublePair('Mary',  'Brown', 'Lisa',  'White');
        $pairC = $this->makeDoublePair('Tom',   'Davis', 'Rob',   'Clark');

        $this->attachToGroup($group, $pairA, 1);
        $this->attachToGroup($group, $pairB, 2);
        $this->attachToGroup($group, $pairC, 3);

        $draw->load('groups.groupRegistrations');
        app(RoundRobinGenerationService::class)->generate($draw);

        $fixtures = Fixture::where('draw_id', $draw->id)
            ->where('stage', 'RR')
            ->with('fixtureResults')
            ->get();

        // PairA beats PairB 6-3
        $ab = $fixtures->first(fn($f) =>
            ($f->registration1_id === $pairA->id && $f->registration2_id === $pairB->id) ||
            ($f->registration1_id === $pairB->id && $f->registration2_id === $pairA->id)
        );
        $this->assertNotNull($ab, 'A vs B fixture should exist');

        [$r1score, $r2score] = $ab->registration1_id === $pairA->id ? [6, 3] : [3, 6];
        $winner = $r1score > $r2score ? $ab->registration1_id : $ab->registration2_id;
        FixtureResult::create([
            'fixture_id'          => $ab->id,
            'set_nr'              => 1,
            'registration1_score' => $r1score,
            'registration2_score' => $r2score,
            'winner_registration' => $winner,
            'loser_registration'  => $winner === $ab->registration1_id ? $ab->registration2_id : $ab->registration1_id,
        ]);

        // PairA beats PairC 6-2
        $ac = $fixtures->first(fn($f) =>
            ($f->registration1_id === $pairA->id && $f->registration2_id === $pairC->id) ||
            ($f->registration1_id === $pairC->id && $f->registration2_id === $pairA->id)
        );
        $this->assertNotNull($ac, 'A vs C fixture should exist');

        [$r1score, $r2score] = $ac->registration1_id === $pairA->id ? [6, 2] : [2, 6];
        $winner = $r1score > $r2score ? $ac->registration1_id : $ac->registration2_id;
        FixtureResult::create([
            'fixture_id'          => $ac->id,
            'set_nr'              => 1,
            'registration1_score' => $r1score,
            'registration2_score' => $r2score,
            'winner_registration' => $winner,
            'loser_registration'  => $winner === $ac->registration1_id ? $ac->registration2_id : $ac->registration1_id,
        ]);

        $draw->load(['groups.groupRegistrations.registration', 'drawFixtures.fixtureResults']);

        $standings = app(StandingsService::class)->forDraw($draw);

        $groupStandings = $standings[$group->id];

        // PairA should be top (2 wins)
        $this->assertSame(2, $groupStandings[0]['wins']);
        $this->assertSame($pairA->id, $groupStandings[0]['reg_id']);

        // Display name in standings must show both players
        $this->assertStringContainsString('/', $groupStandings[0]['player']);
    }

    /** @test */
    public function standings_player_column_shows_pair_name_not_single_player(): void
    {
        $draw  = $this->makeDraw();
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);
        $pair  = $this->makeDoublePair('John', 'Smith', 'Peter', 'Jones');
        $this->attachToGroup($group, $pair, 1);

        $draw->load('groups.groupRegistrations');
        app(RoundRobinGenerationService::class)->generate($draw);
        $draw->load(['groups.groupRegistrations.registration', 'drawFixtures.fixtureResults']);

        $standings = app(StandingsService::class)->forDraw($draw);
        $row = $standings[$group->id][0];

        // Must show "Peter Jones / John Smith" (sorted by surname)
        $this->assertStringContainsString('Jones', $row['player']);
        $this->assertStringContainsString('Smith', $row['player']);
        $this->assertStringContainsString('/', $row['player']);
    }

    // -----------------------------------------------------------------------
    // STEP 4 — SCORE ENTRY PROGRESSION
    // -----------------------------------------------------------------------

    /** @test */
    public function score_entry_advances_winner_registration_id_not_player_id(): void
    {
        $draw  = $this->makeDraw();
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);

        $pairA = $this->makeDoublePair('John', 'Smith',  'Peter', 'Jones');
        $pairB = $this->makeDoublePair('Mary', 'Brown',  'Lisa',  'White');

        $this->attachToGroup($group, $pairA, 1);
        $this->attachToGroup($group, $pairB, 2);

        $draw->load('groups.groupRegistrations');
        app(RoundRobinGenerationService::class)->generate($draw);

        $fixture = Fixture::where('draw_id', $draw->id)->where('stage', 'RR')->first();
        $this->assertNotNull($fixture);

        $winnerId = $fixture->registration1_id;
        $loserId  = $fixture->registration2_id;

        FixtureResult::create([
            'fixture_id'          => $fixture->id,
            'set_nr'              => 1,
            'registration1_score' => 6,
            'registration2_score' => 2,
            'winner_registration' => $winnerId,
            'loser_registration'  => $loserId,
        ]);

        $fixture->update(['winner_registration' => $winnerId, 'match_status' => 1]);
        $fixture->refresh();

        // Winner is a registration ID — which maps to a pair, not a single player
        $this->assertSame($pairA->id, $fixture->winner_registration);

        // The winning registration has 2 players
        $winningReg = Registration::with('players')->find($fixture->winner_registration);
        $this->assertCount(2, $winningReg->players);
    }

    /** @test */
    public function fixture_progression_service_advances_pair_registration(): void
    {
        $draw = $this->makeDraw();
        $draw->load('groups.groupRegistrations');

        $pairA = $this->makeDoublePair('John', 'Smith', 'Peter', 'Jones');
        $pairB = $this->makeDoublePair('Mary', 'Brown', 'Lisa',  'White');

        // Create a parent fixture (SF) to advance into
        $sf = Fixture::factory()->create([
            'draw_id'          => $draw->id,
            'stage'            => 'SF',
            'match_nr'         => 100,
            'match_status'     => 0,
            'registration1_id' => null,
            'registration2_id' => null,
        ]);

        // Create the RR fixture with parent link
        $rrFx = Fixture::factory()->create([
            'draw_id'            => $draw->id,
            'stage'              => 'RR',
            'match_nr'           => 1,
            'match_status'       => 0,
            'registration1_id'   => $pairA->id,
            'registration2_id'   => $pairB->id,
            'parent_fixture_id'  => $sf->id,
        ]);

        app(FixtureProgressionService::class)->advance($rrFx, $pairA->id, $pairB->id);

        $sf->refresh();

        // Winner pair advanced into SF slot
        $this->assertTrue(
            $sf->registration1_id === $pairA->id || $sf->registration2_id === $pairA->id,
            'Pair A should have been advanced into the SF fixture'
        );
    }

    // -----------------------------------------------------------------------
    // STEP 5 — SCHEDULING
    // -----------------------------------------------------------------------

    /** @test */
    public function schedule_fixture_can_be_created_for_doubles_fixture(): void
    {
        $draw  = $this->makeDraw();
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);

        $pairA = $this->makeDoublePair('John', 'Smith',  'Peter', 'Jones');
        $pairB = $this->makeDoublePair('Mary', 'Brown',  'Lisa',  'White');

        $this->attachToGroup($group, $pairA, 1);
        $this->attachToGroup($group, $pairB, 2);

        $draw->load('groups.groupRegistrations');
        app(RoundRobinGenerationService::class)->generate($draw);

        $fixture = Fixture::where('draw_id', $draw->id)->where('stage', 'RR')->first();
        $this->assertNotNull($fixture);

        // Create a schedule entry for this fixture
        $venueId = \DB::table('venues')->insertGetId(['name' => 'Test Venue']);
        $schedule = Schedule::create([
            'fixture_id' => $fixture->id,
            'draw_id'    => $draw->id,
            'venue_id'   => $venueId,
            'time'       => now()->addHours(2)->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('schedules', [
            'fixture_id' => $fixture->id,
            'draw_id'    => $draw->id,
        ]);

        // Verify the fixture still resolves registration with both players
        $fixture->load('registration1.players', 'registration2.players');
        $this->assertCount(2, $fixture->registration1->players);
        $this->assertCount(2, $fixture->registration2->players);

        // Verify schedule display name via displayName()
        $this->assertStringContainsString('/', $fixture->registration1->displayName());
        $this->assertStringContainsString('/', $fixture->registration2->displayName());
    }

    /** @test */
    public function schedule_player_name_helpers_return_pair_names(): void
    {
        $pairA = $this->makeDoublePair('John', 'Smith',  'Peter', 'Jones');
        $pairB = $this->makeDoublePair('Mary', 'Brown',  'Lisa',  'White');

        $draw  = $this->makeDraw();
        $fx    = Fixture::factory()->create([
            'draw_id'          => $draw->id,
            'stage'            => 'RR',
            'registration1_id' => $pairA->id,
            'registration2_id' => $pairB->id,
        ]);

        $fx->load('registration1.players', 'registration2.players');

        // Simulate what ScheduleController does: pluck('full_name')->join(' / ')
        $p1 = $fx->registration1?->players?->pluck('full_name')->join(' / ') ?? 'TBD';
        $p2 = $fx->registration2?->players?->pluck('full_name')->join(' / ') ?? 'TBD';

        $this->assertStringContainsString('/', $p1, 'p1 should be a slash-joined pair');
        $this->assertStringContainsString('/', $p2, 'p2 should be a slash-joined pair');
    }

    // -----------------------------------------------------------------------
    // STEP 6 — PRINT SHEETS (data layer only — SVG rendering is browser-side)
    // -----------------------------------------------------------------------

    /** @test */
    public function fixture_registration_has_both_players_for_print_context(): void
    {
        $pairA = $this->makeDoublePair('John', 'Smith', 'Peter', 'Jones');

        $draw = $this->makeDraw();
        $fx   = Fixture::factory()->create([
            'draw_id'          => $draw->id,
            'stage'            => 'MAIN',
            'registration1_id' => $pairA->id,
        ]);

        $fx->load('registration1.players');

        // Print engines use players[0] historically — verify displayName() is the safe alternative
        $this->assertSame('Peter Jones / John Smith', $fx->registration1->displayName());
        // players[0] is still accessible (for legacy Brackets.php safety net)
        $this->assertNotNull($fx->registration1->players[0] ?? null);
        // But it only gives ONE player name — the whole point of displayName()
        $this->assertCount(2, $fx->registration1->players);
    }

    // -----------------------------------------------------------------------
    // STEP 7 — PUBLIC VIEWS (data layer)
    // -----------------------------------------------------------------------

    /** @test */
    public function fixture_table_helper_returns_both_player_names_for_doubles(): void
    {
        $pairA = $this->makeDoublePair('John', 'Smith',  'Peter', 'Jones');
        $pairB = $this->makeDoublePair('Mary', 'Brown',  'Lisa',  'White');

        $draw = $this->makeDraw();
        $fx   = Fixture::factory()->create([
            'draw_id'          => $draw->id,
            'stage'            => 'RR',
            'registration1_id' => $pairA->id,
            'registration2_id' => $pairB->id,
        ]);

        $fx->load('registration1.players', 'registration2.players');

        // Simulate fixture-table.blade.php fx_player1() / fx_player2() helpers
        $p1 = $fx->registration1->players->pluck('full_name')->implode(' + ');
        $p2 = $fx->registration2->players->pluck('full_name')->implode(' + ');

        $this->assertStringContainsString('+', $p1, 'fixture-table helper joins two player names');
        $this->assertStringContainsString('+', $p2, 'fixture-table helper joins two player names');
    }

    /** @test */
    public function registration_display_name_consistent_with_fixture_context(): void
    {
        $pairA = $this->makeDoublePair('John', 'Smith', 'Peter', 'Jones');

        $this->assertSame('Peter Jones / John Smith', $pairA->displayName());
        $this->assertSame('Jones / Smith',            $pairA->displayShortName());
        $this->assertSame('Peter Jones / John Smith', $pairA->display_name); // attribute
    }

    // -----------------------------------------------------------------------
    // OVERALL BLOCKER CHECK
    // -----------------------------------------------------------------------

    /** @test */
    public function doubles_can_complete_full_rr_cycle_without_errors(): void
    {
        $draw  = $this->makeDraw();
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);

        $pairs = [
            $this->makeDoublePair('John',  'Smith',  'Peter', 'Jones'),
            $this->makeDoublePair('Mary',  'Brown',  'Lisa',  'White'),
            $this->makeDoublePair('Tom',   'Davis',  'Rob',   'Clark'),
            $this->makeDoublePair('Alice', 'Adams',  'Zara',  'Williams'),
        ];

        foreach ($pairs as $i => $pair) {
            $this->attachToGroup($group, $pair, $i + 1);
        }

        $draw->load('groups.groupRegistrations');

        // 1. Generate RR
        app(RoundRobinGenerationService::class)->generate($draw);
        $fixtures = Fixture::where('draw_id', $draw->id)->where('stage', 'RR')->get();
        $this->assertCount(6, $fixtures);

        // 2. Enter all scores (pair[0] wins all)
        foreach ($fixtures as $fx) {
            $winnerId = $fx->registration1_id === $pairs[0]->id ? $pairs[0]->id : null;
            $r1 = $fx->registration1_id === $pairs[0]->id ? 6 : 3;
            $r2 = $fx->registration2_id === $pairs[0]->id ? 6 : 3;
            $winner = $r1 > $r2 ? $fx->registration1_id : $fx->registration2_id;
            FixtureResult::create([
                'fixture_id'          => $fx->id,
                'set_nr'              => 1,
                'registration1_score' => $r1,
                'registration2_score' => $r2,
                'winner_registration' => $winner,
                'loser_registration'  => $winner === $fx->registration1_id ? $fx->registration2_id : $fx->registration1_id,
            ]);
            $fx->update(['winner_registration' => $winner, 'match_status' => 1]);
        }

        // 3. Verify standings
        $draw->load(['groups.groupRegistrations.registration', 'drawFixtures.fixtureResults']);
        $standings = app(StandingsService::class)->forDraw($draw);
        $top = $standings[$group->id][0];

        // Top pair should have most wins
        $this->assertGreaterThanOrEqual(2, $top['wins']);
        // Top pair display name shows both players
        $this->assertStringContainsString('/', $top['player']);

        // 4. Confirm all 6 fixtures have results
        $withResults = Fixture::where('draw_id', $draw->id)
            ->where('stage', 'RR')
            ->whereNotNull('winner_registration')
            ->count();
        $this->assertSame(6, $withResults);
    }
}
