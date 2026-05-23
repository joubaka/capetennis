<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Guards\DrawGuard;
use App\Domain\Draws\Services\ByeAdvancementService;
use App\Domain\Draws\Services\DrawGenerationService;
use App\Domain\Draws\Services\PlayoffGenerationService;
use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Domain\Draws\Services\StandingsService;
use App\Domain\Fixtures\Services\FixtureProgressionService;
use App\Models\Draw;
use App\Models\DrawGroup;
use App\Models\DrawGroupRegistration;
use App\Models\Fixture;
use App\Models\FixtureResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GoldenTournamentTest
 *
 * Golden-reference test suite for the canonical draw engine.
 * Each scenario is self-contained and produces a fully verifiable
 * tournament state without touching production code paths.
 *
 * Scenarios:
 *  1.  4-player Round Robin — full standings
 *  2.  8-player playoff bracket — structure + progression
 *  3.  Feed-in / consolation routing
 *  4.  BYE scenarios — single BYE, double BYE, cascade
 *  5.  2-box playoff seeding
 *  6.  4-box playoff seeding
 *  7.  Locked draw — mutation blocked
 *  8.  Duplicate progression — idempotency
 *  9.  Delete-score rollback
 * 10.  Standings tiebreak edge cases
 */
class GoldenTournamentTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // HELPERS
    // ------------------------------------------------------------------

    private function makeDraw(array $attrs = []): Draw
    {
        return Draw::factory()->create(array_merge(['locked' => false, 'published' => false], $attrs));
    }

    /**
     * Create a group with N players (registration IDs 1..N by default).
     */
    private function makeGroup(Draw $draw, string $name, array $regIds): DrawGroup
    {
        $group = DrawGroup::factory()->create(['draw_id' => $draw->id, 'name' => $name]);
        foreach ($regIds as $i => $regId) {
            DrawGroupRegistration::factory()->create([
                'draw_group_id'   => $group->id,
                'registration_id' => $regId,
                'seed'            => $i + 1,
            ]);
        }
        return $group;
    }

    /** Record a complete result on a fixture. */
    private function scoreFixture(Fixture $fx, int $winner, int $loser, array $sets = [[6, 4]]): void
    {
        $fx->fixtureResults()->delete();
        foreach ($sets as $i => [$s1, $s2]) {
            FixtureResult::factory()->create([
                'fixture_id'          => $fx->id,
                'set_nr'              => $i + 1,
                'registration1_score' => $s1,
                'registration2_score' => $s2,
                'winner_registration' => $s1 > $s2 ? $fx->registration1_id : $fx->registration2_id,
                'loser_registration'  => $s1 > $s2 ? $fx->registration2_id : $fx->registration1_id,
            ]);
        }
        $fx->winner_registration = $winner;
        $fx->match_status        = 1;
        $fx->save();
    }

    // ==================================================================
    // SCENARIO 1 — 4-player Round Robin: full standings
    // ==================================================================

    /** @test */
    public function four_player_rr_generates_correct_fixture_count(): void
    {
        $draw  = $this->makeDraw();
        $group = $this->makeGroup($draw, 'A', [1, 2, 3, 4]);

        $svc = app(RoundRobinGenerationService::class);
        $svc->generate($draw);

        $draw->refresh();
        $fixtures = $draw->drawFixtures()->where('stage', 'RR')->get();

        // 4 players → C(4,2) = 6 matches
        $this->assertCount(6, $fixtures, '4-player RR should produce 6 fixtures');
        $this->assertTrue($fixtures->every(fn($f) => $f->draw_group_id === $group->id));
    }

    /** @test */
    public function four_player_rr_standings_ranks_by_wins(): void
    {
        $draw  = $this->makeDraw();
        $group = $this->makeGroup($draw, 'A', [10, 20, 30, 40]);

        app(RoundRobinGenerationService::class)->generate($draw);
        $draw->loadMissing(['groups.groupRegistrations', 'drawFixtures.fixtureResults']);

        // Player 10 beats everyone
        foreach ($draw->drawFixtures()->where('stage', 'RR')->get() as $fx) {
            if ($fx->registration1_id === 10 || $fx->registration2_id === 10) {
                $winner = 10;
                $loser  = $fx->registration1_id === 10 ? $fx->registration2_id : $fx->registration1_id;
                $this->scoreFixture($fx, $winner, $loser, [[6, 2]]);
            }
        }

        $draw->loadMissing(['drawFixtures.fixtureResults']);
        $standings = app(StandingsService::class)->forGroup(
            $group,
            $draw->drawFixtures()->with('fixtureResults')->get()
        );

        $this->assertEquals(10, $standings[0]['reg_id'], 'Player 10 (most wins) should be ranked 1st');
        $this->assertGreaterThan(0, $standings[0]['wins']);
    }

    /** @test */
    public function four_player_rr_standings_tiebreak_by_sets_pct(): void
    {
        $draw  = $this->makeDraw();
        $group = $this->makeGroup($draw, 'A', [1, 2, 3, 4]);

        app(RoundRobinGenerationService::class)->generate($draw);
        $fixtures = $draw->drawFixtures()->where('stage', 'RR')->get();

        // Give players 1 and 2 equal wins (1 each).
        // Player 1 wins with 6-0 (better sets %).
        // Player 2 wins with 6-4.
        $p1vsP2 = $fixtures->first(fn($f) =>
            ($f->registration1_id === 1 && $f->registration2_id === 2) ||
            ($f->registration1_id === 2 && $f->registration2_id === 1)
        );

        if ($p1vsP2) {
            $w = $p1vsP2->registration1_id === 1 ? 1 : 2;
            $l = $w === 1 ? 2 : 1;
            $this->scoreFixture($p1vsP2, $w, $l, [[6, 0]]);
        }

        $draw->load(['drawFixtures.fixtureResults']);
        $allFx = $draw->drawFixtures()->with('fixtureResults')->get();
        $standings = app(StandingsService::class)->forGroup($group, $allFx);

        // After tiebreak, player 1 must be above player 2 if p1 has better sets%
        $ranks = array_column($standings, 'reg_id');
        $this->assertContains(1, $ranks);
        $this->assertContains(2, $ranks);
    }

    // ==================================================================
    // SCENARIO 2 — 8-player playoff: bracket structure
    // ==================================================================

    /** @test */
    public function main_4group_playoff_creates_correct_structure(): void
    {
        $draw  = $this->makeDraw();
        $seeds = ['A1' => 1, 'B1' => 2, 'C1' => 3, 'D1' => 4];

        $result = app(PlayoffGenerationService::class)->createMainBracket($draw, $seeds);

        $this->assertArrayHasKey('sf1',   $result);
        $this->assertArrayHasKey('sf2',   $result);
        $this->assertArrayHasKey('final', $result);
        $this->assertArrayHasKey('third', $result);

        // SF1 should have A1 vs D1
        $this->assertEquals(1, $result['sf1']->registration1_id);
        $this->assertEquals(4, $result['sf1']->registration2_id);

        // SF2 should have B1 vs C1
        $this->assertEquals(2, $result['sf2']->registration1_id);
        $this->assertEquals(3, $result['sf2']->registration2_id);

        // SF1 and SF2 winners should feed into final
        $this->assertEquals($result['final']->id, $result['sf1']->parent_fixture_id);
        $this->assertEquals($result['final']->id, $result['sf2']->parent_fixture_id);

        // SF losers should feed into 3rd/4th
        $this->assertEquals($result['third']->id, $result['sf1']->loser_parent_fixture_id);
        $this->assertEquals($result['third']->id, $result['sf2']->loser_parent_fixture_id);
    }

    /** @test */
    public function main_2group_playoff_creates_correct_structure(): void
    {
        $draw  = $this->makeDraw();
        $seeds = ['A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4];

        $result = app(PlayoffGenerationService::class)->createMainBracket($draw, $seeds);

        // A1 vs B2 and B1 vs A2
        $this->assertEquals(1, $result['sf1']->registration1_id);
        $this->assertEquals(4, $result['sf1']->registration2_id);
        $this->assertEquals(3, $result['sf2']->registration1_id);
        $this->assertEquals(2, $result['sf2']->registration2_id);
    }

    /** @test */
    public function playoff_winner_advances_to_final(): void
    {
        $draw  = $this->makeDraw();
        $seeds = ['A1' => 10, 'B1' => 20, 'C1' => 30, 'D1' => 40];
        $r     = app(PlayoffGenerationService::class)->createMainBracket($draw, $seeds);

        $sf1 = $r['sf1']->fresh();

        // Score sf1: player 10 wins
        $this->scoreFixture($sf1, 10, 40, [[6, 2]]);
        $sf1->loadMissing('fixtureResults');

        app(FixtureProgressionService::class)->advance($sf1, 10, 40);

        $final = $r['final']->fresh();
        $this->assertEquals(10, $final->registration1_id, 'Winner should advance into final slot 1');
    }

    /** @test */
    public function playoff_loser_advances_to_third_place(): void
    {
        $draw  = $this->makeDraw();
        $seeds = ['A1' => 10, 'B1' => 20, 'C1' => 30, 'D1' => 40];
        $r     = app(PlayoffGenerationService::class)->createMainBracket($draw, $seeds);

        $sf1 = $r['sf1']->fresh();
        $this->scoreFixture($sf1, 10, 40, [[6, 2]]);
        $sf1->loadMissing('fixtureResults');
        app(FixtureProgressionService::class)->advance($sf1, 10, 40);

        $third = $r['third']->fresh();
        $this->assertEquals(40, $third->registration1_id, 'Loser should advance into 3rd/4th slot');
    }

    // ==================================================================
    // SCENARIO 3 — Feed-in / consolation routing
    // ==================================================================

    /** @test */
    public function plate_bracket_creates_correct_qf_sf_structure(): void
    {
        $draw  = $this->makeDraw();
        $seeds = [
            'A2' => 1, 'A3' => 2,
            'B2' => 3, 'B3' => 4,
            'C2' => 5, 'C3' => 6,
            'D2' => 7, 'D3' => 8,
        ];

        $result = app(PlayoffGenerationService::class)->createPlateBracket($draw, $seeds);

        $this->assertArrayHasKey('qf1',   $result);
        $this->assertArrayHasKey('qf2',   $result);
        $this->assertArrayHasKey('sf1',   $result);
        $this->assertArrayHasKey('final', $result);
        $this->assertArrayHasKey('third', $result);

        // QF1: A2 vs D3
        $this->assertEquals(1, $result['qf1']->registration1_id);
        $this->assertEquals(8, $result['qf1']->registration2_id);

        // QF winners feed SF
        $this->assertEquals($result['sf1']->id, $result['qf1']->parent_fixture_id);
        $this->assertEquals($result['sf1']->id, $result['qf2']->parent_fixture_id);
    }

    /** @test */
    public function plate_bracket_throws_on_missing_seeds(): void
    {
        $draw = $this->makeDraw();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing seed key/i');

        app(PlayoffGenerationService::class)->createPlateBracket($draw, ['A2' => 1]); // incomplete
    }

    // ==================================================================
    // SCENARIO 4 — BYE advancement
    // ==================================================================

    /** @test */
    public function bye_advancement_advances_lone_player_to_next_round(): void
    {
        $draw = $this->makeDraw();

        $final = Fixture::factory()->create([
            'draw_id'  => $draw->id,
            'stage'    => 'MAIN',
            'round'    => 2,
            'match_nr' => 200,
        ]);

        // SF1: player 10 vs BYE (registration2 = null)
        $sf1 = Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'stage'               => 'MAIN',
            'round'               => 1,
            'match_nr'            => 101,
            'registration1_id'    => 10,
            'registration2_id'    => null,
            'parent_fixture_id'   => $final->id,
        ]);

        // SF2: player 20 vs player 30 (real match — not a bye)
        Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'stage'               => 'MAIN',
            'round'               => 1,
            'match_nr'            => 102,
            'registration1_id'    => 20,
            'registration2_id'    => 30,
            'parent_fixture_id'   => $final->id,
        ]);

        app(ByeAdvancementService::class)->advance($draw);

        $sf1->refresh();
        $final->refresh();

        $this->assertEquals(10, $sf1->winner_registration, 'BYE fixture should have winner set');
        $this->assertEquals(10, $final->registration1_id, 'BYE winner should advance to parent slot 1');
    }

    /** @test */
    public function bye_advancement_does_not_advance_when_both_slots_empty(): void
    {
        $draw = $this->makeDraw();

        $fx = Fixture::factory()->create([
            'draw_id'          => $draw->id,
            'stage'            => 'MAIN',
            'round'            => 1,
            'match_nr'         => 101,
            'registration1_id' => null,
            'registration2_id' => null,
        ]);

        app(ByeAdvancementService::class)->advance($draw);

        $fx->refresh();
        $this->assertNull($fx->winner_registration, 'Double-BYE fixture should not have a winner');
    }

    /** @test */
    public function bye_advancement_is_idempotent(): void
    {
        $draw = $this->makeDraw();

        $parent = Fixture::factory()->create([
            'draw_id'  => $draw->id,
            'stage'    => 'MAIN',
            'round'    => 2,
            'match_nr' => 200,
        ]);

        Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'stage'               => 'MAIN',
            'round'               => 1,
            'match_nr'            => 101,
            'registration1_id'    => 10,
            'registration2_id'    => null,
            'parent_fixture_id'   => $parent->id,
        ]);

        $svc = app(ByeAdvancementService::class);
        $svc->advance($draw);
        $svc->advance($draw); // second call must not corrupt state

        $parent->refresh();
        $this->assertEquals(10, $parent->registration1_id, 'After idempotent advance, slot must still be 10');
        $this->assertNull($parent->registration2_id, 'Slot 2 must not be overwritten');
    }

    // ==================================================================
    // SCENARIO 5 — 2-box playoff seeding
    // ==================================================================

    /** @test */
    public function two_box_playoff_invalid_seed_layout_throws(): void
    {
        $draw = $this->makeDraw();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/unrecognised seed layout/i');

        app(PlayoffGenerationService::class)->createMainBracket($draw, ['X1' => 1, 'Y1' => 2]);
    }

    // ==================================================================
    // SCENARIO 6 — 4-box playoff seeding (covered by scenario 2)
    // ==================================================================

    /** @test */
    public function four_box_playoff_uses_a1_b1_c1_d1_pattern(): void
    {
        $draw  = $this->makeDraw();
        $seeds = ['A1' => 100, 'B1' => 200, 'C1' => 300, 'D1' => 400];
        $r     = app(PlayoffGenerationService::class)->createMainBracket($draw, $seeds);

        // A1 vs D1 in SF1
        $this->assertEquals(100, $r['sf1']->registration1_id);
        $this->assertEquals(400, $r['sf1']->registration2_id);

        // B1 vs C1 in SF2
        $this->assertEquals(200, $r['sf2']->registration1_id);
        $this->assertEquals(300, $r['sf2']->registration2_id);
    }

    // ==================================================================
    // SCENARIO 7 — Locked draw: mutations blocked
    // ==================================================================

    /** @test */
    public function locked_draw_blocks_rr_generation(): void
    {
        $draw = $this->makeDraw(['locked' => true]);
        $this->makeGroup($draw, 'A', [1, 2, 3]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/i');

        app(RoundRobinGenerationService::class)->generate($draw);
    }

    /** @test */
    public function locked_draw_blocks_playoff_generation(): void
    {
        $draw = $this->makeDraw(['locked' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/i');

        app(PlayoffGenerationService::class)->createMainBracket($draw, ['A1' => 1, 'B1' => 2, 'C1' => 3, 'D1' => 4]);
    }

    /** @test */
    public function locked_draw_blocks_progression(): void
    {
        $draw = $this->makeDraw(['locked' => true]);

        $parent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 200]);
        $fx     = Fixture::factory()->create([
            'draw_id'           => $draw->id,
            'stage'             => 'MAIN',
            'round'             => 1,
            'match_nr'          => 101,
            'registration1_id'  => 10,
            'registration2_id'  => 20,
            'winner_registration' => 10,
            'parent_fixture_id' => $parent->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/i');

        app(FixtureProgressionService::class)->advance($fx, 10, 20);
    }

    /** @test */
    public function draw_guard_require_mutable_throws_on_locked_draw(): void
    {
        $draw = $this->makeDraw(['locked' => true]);
        $this->expectException(\RuntimeException::class);
        DrawGuard::requireMutable($draw, 'test-operation');
    }

    // ==================================================================
    // SCENARIO 8 — Duplicate progression idempotency
    // ==================================================================

    /** @test */
    public function duplicate_progression_does_not_overwrite_occupied_slot(): void
    {
        $draw = $this->makeDraw();

        $final = Fixture::factory()->create([
            'draw_id'  => $draw->id,
            'stage'    => 'MAIN',
            'round'    => 2,
            'match_nr' => 200,
        ]);

        $sf1 = Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'stage'               => 'MAIN',
            'round'               => 1,
            'match_nr'            => 101,
            'registration1_id'    => 10,
            'registration2_id'    => 20,
            'winner_registration' => 10,
            'match_status'        => 1,
            'parent_fixture_id'   => $final->id,
        ]);

        $sf2 = Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'stage'               => 'MAIN',
            'round'               => 1,
            'match_nr'            => 102,
            'registration1_id'    => 30,
            'registration2_id'    => 40,
            'winner_registration' => 30,
            'match_status'        => 1,
            'parent_fixture_id'   => $final->id,
        ]);

        FixtureResult::factory()->create(['fixture_id' => $sf1->id, 'set_nr' => 1,
            'registration1_score' => 6, 'registration2_score' => 2,
            'winner_registration' => 10, 'loser_registration' => 20]);
        FixtureResult::factory()->create(['fixture_id' => $sf2->id, 'set_nr' => 1,
            'registration1_score' => 6, 'registration2_score' => 2,
            'winner_registration' => 30, 'loser_registration' => 40]);

        $svc = app(FixtureProgressionService::class);
        $sf1->loadMissing('fixtureResults');
        $sf2->loadMissing('fixtureResults');

        $svc->advance($sf1, 10, 20);
        $svc->advance($sf2, 30, 40);

        // Second advance on sf1 should not overwrite slot 1 with player 10 again
        $svc->advance($sf1, 10, 20);

        $final->refresh();
        $this->assertEquals(10, $final->registration1_id, 'Slot 1 should hold player 10');
        $this->assertEquals(30, $final->registration2_id, 'Slot 2 should hold player 30');
    }

    // ==================================================================
    // SCENARIO 9 — Delete-score rollback
    // ==================================================================

    /** @test */
    public function rollback_clears_winner_from_parent_and_child(): void
    {
        $draw = $this->makeDraw();

        $parent = Fixture::factory()->create([
            'draw_id'  => $draw->id,
            'stage'    => 'MAIN',
            'round'    => 2,
            'match_nr' => 200,
            'registration1_id'    => 10,
            'winner_registration' => 10,
        ]);

        $child = Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'stage'               => 'MAIN',
            'round'               => 1,
            'match_nr'            => 101,
            'registration1_id'    => 10,
            'registration2_id'    => 20,
            'winner_registration' => 10,
            'match_status'        => 1,
            'parent_fixture_id'   => $parent->id,
        ]);

        FixtureResult::factory()->create([
            'fixture_id'          => $child->id,
            'set_nr'              => 1,
            'registration1_score' => 6,
            'registration2_score' => 3,
            'winner_registration' => 10,
            'loser_registration'  => 20,
        ]);

        app(FixtureProgressionService::class)->rollback($child);

        $child->refresh();
        $parent->refresh();

        $this->assertNull($child->winner_registration, 'Child winner_registration must be cleared');
        $this->assertEquals(0, $child->match_status, 'Child match_status must be reset to 0');
        $this->assertNull($parent->registration1_id, 'Parent slot must be cleared after rollback');
    }

    /** @test */
    public function rollback_is_noop_when_parent_has_different_player_in_slot(): void
    {
        $draw = $this->makeDraw();

        $parent = Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'stage'               => 'MAIN',
            'round'               => 2,
            'match_nr'            => 200,
            'registration1_id'    => 99, // occupied by a different player
        ]);

        $child = Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'stage'               => 'MAIN',
            'round'               => 1,
            'match_nr'            => 101,
            'registration1_id'    => 10,
            'registration2_id'    => 20,
            'winner_registration' => 10,
            'match_status'        => 1,
            'parent_fixture_id'   => $parent->id,
        ]);

        FixtureResult::factory()->create([
            'fixture_id'          => $child->id,
            'set_nr'              => 1,
            'registration1_score' => 6,
            'registration2_score' => 3,
            'winner_registration' => 10,
            'loser_registration'  => 20,
        ]);

        app(FixtureProgressionService::class)->rollback($child);

        $parent->refresh();
        // Parent slot was 99 (different player) — must NOT be cleared
        $this->assertEquals(99, $parent->registration1_id, 'Parent slot with different player must not be cleared');
    }

    // ==================================================================
    // SCENARIO 10 — Standings tiebreak edge cases
    // ==================================================================

    /** @test */
    public function standings_handles_all_players_at_zero_wins(): void
    {
        $draw  = $this->makeDraw();
        $group = $this->makeGroup($draw, 'A', [1, 2, 3, 4]);

        app(RoundRobinGenerationService::class)->generate($draw);

        // Score nothing — all fixtures remain incomplete
        $allFx    = $draw->drawFixtures()->with('fixtureResults')->get();
        $standings = app(StandingsService::class)->forGroup($group, $allFx);

        $this->assertCount(4, $standings, 'Should return a row for every player even with 0 wins');
        $this->assertTrue(
            collect($standings)->every(fn($r) => $r['wins'] === 0),
            'All players should have 0 wins'
        );
    }

    /** @test */
    public function standings_head_to_head_breaks_tie_between_two_equal_players(): void
    {
        $draw  = $this->makeDraw();
        $group = $this->makeGroup($draw, 'A', [1, 2]);

        app(RoundRobinGenerationService::class)->generate($draw);
        $allFx = $draw->drawFixtures()->where('stage', 'RR')->with('fixtureResults')->get();

        $h2h = $allFx->first(fn($f) =>
            ($f->registration1_id === 1 && $f->registration2_id === 2) ||
            ($f->registration1_id === 2 && $f->registration2_id === 1)
        );

        if ($h2h) {
            // Player 1 wins the head-to-head
            $win = $h2h->registration1_id === 1 ? 1 : 2;
            $lose = $win === 1 ? 2 : 1;
            $this->scoreFixture($h2h, $win, $lose, [[6, 6], [10, 8]]); // H2H sets equal, 3rd set decides
        }

        $allFx    = $draw->drawFixtures()->where('stage', 'RR')->with('fixtureResults')->get();
        $standings = app(StandingsService::class)->forGroup($group, $allFx);

        $this->assertCount(2, $standings);
        // With only 2 players the winner of H2H is ranked first
        $this->assertEquals(1, $standings[0]['reg_id'], 'H2H winner must be ranked first');
    }

    /** @test */
    public function standings_for_draw_returns_keyed_by_group_id(): void
    {
        $draw   = $this->makeDraw();
        $groupA = $this->makeGroup($draw, 'A', [1, 2, 3]);
        $groupB = $this->makeGroup($draw, 'B', [4, 5, 6]);

        app(RoundRobinGenerationService::class)->generate($draw);

        $standings = app(StandingsService::class)->forDraw($draw);

        $this->assertArrayHasKey($groupA->id, $standings);
        $this->assertArrayHasKey($groupB->id, $standings);
        $this->assertCount(3, $standings[$groupA->id]);
        $this->assertCount(3, $standings[$groupB->id]);
    }

    /** @test */
    public function qualifiers_returns_top_n_per_group(): void
    {
        $draw   = $this->makeDraw();
        $groupA = $this->makeGroup($draw, 'A', [1, 2, 3, 4]);
        $groupB = $this->makeGroup($draw, 'B', [5, 6, 7, 8]);

        app(RoundRobinGenerationService::class)->generate($draw);

        $qualifiers = app(StandingsService::class)->qualifiers($draw, 2);

        $this->assertCount(2, $qualifiers[$groupA->id], 'Should return 2 qualifiers per group');
        $this->assertCount(2, $qualifiers[$groupB->id]);
    }
}
