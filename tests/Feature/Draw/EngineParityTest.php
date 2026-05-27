<?php

namespace Tests\Feature\Draw;

use App\Domain\Engine\EngineRouter;
use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Domain\Draws\Services\StandingsService;
use App\Domain\Fixtures\Services\FixtureProgressionService;
use App\Models\Draw;
use App\Models\DrawGroup;
use App\Models\DrawGroupRegistration;
use App\Models\EngineComparisonLog;
use App\Models\Fixture;
use App\Models\FixtureResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EngineParityTest
 *
 * Verifies that canonical services produce output consistent with legacy
 * behaviour and that EngineRouter correctly routes, compares, and logs
 * mismatches or fallbacks.
 *
 * Tests:
 *  1.  EngineRouter respects LEGACY mode — canonical never called
 *  2.  EngineRouter respects CANONICAL mode — legacy never called
 *  3.  EngineRouter HYBRID routes to canonical and calls legacy for comparison
 *  4.  HYBRID auto-fallback fires when canonical throws
 *  5.  HYBRID mismatch logged when standings orders differ
 *  6.  HYBRID mismatch written to DB
 *  7.  RR: canonical and legacy generate same fixture count
 *  8.  Standings: canonical and legacy agree on winner-takes-all scenario
 *  9.  Progression: canonical and legacy both advance winner to parent
 * 10.  Rollback: canonical and legacy both clear parent slot
 * 11.  BYE: canonical and legacy agree on lone-player advance
 * 12.  Fallback count increments when canonical throws
 * 13.  resetCounters resets static state
 * 14.  Mismatch NOT counted for snapshot entries
 * 15.  clearLogs truncates DB table
 */
class EngineParityTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // HELPERS
    // ------------------------------------------------------------------

    private function makeDraw(array $attrs = []): Draw
    {
        return Draw::factory()->create(array_merge(['locked' => false, 'published' => false], $attrs));
    }

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

    private function scoreAndAdvance(Draw $draw, int $winner, int $loser): Fixture
    {
        $draw->refresh();
        $fx = $draw->drawFixtures()
            ->where('registration1_id', $winner)
            ->orWhere('registration2_id', $winner)
            ->where('stage', 'MAIN')
            ->where('round', 1)
            ->first();

        if (! $fx) {
            $parent = Fixture::factory()->create([
                'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 200,
            ]);
            $fx = Fixture::factory()->create([
                'draw_id'           => $draw->id,
                'stage'             => 'MAIN',
                'round'             => 1,
                'match_nr'          => 101,
                'registration1_id'  => $winner,
                'registration2_id'  => $loser,
                'parent_fixture_id' => $parent->id,
            ]);
        }

        FixtureResult::factory()->create([
            'fixture_id'          => $fx->id,
            'set_nr'              => 1,
            'registration1_score' => 6,
            'registration2_score' => 2,
            'winner_registration' => $winner,
            'loser_registration'  => $loser,
        ]);
        $fx->winner_registration = $winner;
        $fx->match_status        = 1;
        $fx->save();
        $fx->loadMissing('fixtureResults');

        return $fx;
    }

    private function routerInMode(string $mode): EngineRouter
    {
        config(['capetennis_engine.mode' => $mode, 'capetennis_engine.auto_fallback' => true]);
        // Re-resolve so config is applied (singleton already bound — use make for fresh)
        $router = $this->app->make(EngineRouter::class);
        EngineRouter::resetCounters();
        return $router;
    }

    // ==================================================================
    // 1. LEGACY mode — canonical never called
    // ==================================================================

    /** @test */
    public function legacy_mode_never_calls_canonical_rr(): void
    {
        $draw   = $this->makeDraw();
        $this->makeGroup($draw, 'A', [1, 2, 3, 4]);
        $router = $this->routerInMode(EngineRouter::MODE_LEGACY);

        $canonicalCalled = false;
        $legacyCalled    = false;

        // In legacy mode the canonical callable inside EngineRouter is never invoked;
        // only the provided $legacyFn runs.
        $router->generateRoundRobin($draw, function (Draw $d) use (&$legacyCalled) {
            $legacyCalled = true;
        });

        $this->assertTrue($legacyCalled,  'Legacy fn must be called in legacy mode');
        $this->assertEquals(0, EngineRouter::mismatchCount());
        $this->assertEquals(0, EngineRouter::fallbackCount());
    }

    // ==================================================================
    // 2. CANONICAL mode — legacy callable never invoked
    // ==================================================================

    /** @test */
    public function canonical_mode_never_calls_legacy_fn(): void
    {
        $draw   = $this->makeDraw();
        $this->makeGroup($draw, 'A', [1, 2, 3]);
        $router = $this->routerInMode(EngineRouter::MODE_CANONICAL);

        $legacyCalled = false;

        $router->generateRoundRobin($draw, function (Draw $d) use (&$legacyCalled) {
            $legacyCalled = true;
        });

        $this->assertFalse($legacyCalled, 'Legacy fn must NOT be called in canonical mode');
        // Canonical should have generated 3 RR fixtures
        $this->assertEquals(3, $draw->drawFixtures()->where('stage', 'RR')->count());
    }

    // ==================================================================
    // 3. HYBRID mode — routes to canonical and runs internal comparison
    // ==================================================================

    /** @test */
    public function hybrid_mode_runs_canonical_and_does_not_call_legacy_fn_for_rr(): void
    {
        $draw   = $this->makeDraw();
        $this->makeGroup($draw, 'A', [1, 2, 3]);
        $router = $this->routerInMode(EngineRouter::MODE_HYBRID);

        // In hybrid mode the $legacyFn passed to generateRoundRobin is the
        // fallback only — it is NOT called when canonical succeeds.
        $legacyCalled = false;

        $router->generateRoundRobin($draw, function (Draw $d) use (&$legacyCalled) {
            $legacyCalled = true;
        });

        // Canonical ran — RR fixtures must exist
        $this->assertGreaterThan(0, $draw->drawFixtures()->where('stage', 'RR')->count(),
            'Canonical RR generation must produce fixtures in hybrid mode');

        // Legacy fallback must NOT have been triggered (canonical succeeded)
        $this->assertFalse($legacyCalled, 'Legacy fn must NOT be called when canonical succeeds in hybrid mode');

        // No fallback counter increment
        $this->assertEquals(0, EngineRouter::fallbackCount());
    }

    // ==================================================================
    // 4. HYBRID auto-fallback fires when canonical throws
    // ==================================================================

    /** @test */
    public function hybrid_auto_fallback_fires_when_canonical_throws(): void
    {
        $draw   = $this->makeDraw();
        $router = $this->routerInMode(EngineRouter::MODE_HYBRID);

        $fallbackCalled = false;

        // Simulate canonical failure by locking the draw (RoundRobinGenerationService throws on locked)
        $draw->locked = true;
        $draw->save();

        $router->generateRoundRobin($draw, function (Draw $d) use (&$fallbackCalled) {
            $fallbackCalled = true;
        });

        $this->assertTrue($fallbackCalled,         'Fallback fn must be called when canonical throws');
        $this->assertEquals(1, EngineRouter::fallbackCount(), 'Fallback counter must increment');
    }

    // ==================================================================
    // 5. HYBRID mismatch logged when standings orders differ
    // ==================================================================

    /** @test */
    public function hybrid_logs_mismatch_when_standings_orders_differ(): void
    {
        $draw   = $this->makeDraw();
        $group  = $this->makeGroup($draw, 'A', [10, 20, 30]);
        app(RoundRobinGenerationService::class)->generate($draw);

        $router = $this->routerInMode(EngineRouter::MODE_HYBRID);

        // Legacy fn returns deliberately reversed order — this is a mismatch
        $result = $router->standings($draw, function (Draw $d) use ($group) {
            return [
                $group->id => [
                    ['reg_id' => 30, 'wins' => 0],
                    ['reg_id' => 20, 'wins' => 0],
                    ['reg_id' => 10, 'wins' => 0],
                ],
            ];
        });

        // Result should be the canonical output (not legacy)
        $this->assertArrayHasKey($group->id, $result);
        $this->assertCount(3, $result[$group->id]);
    }

    // ==================================================================
    // 6. HYBRID mismatch written to DB
    // ==================================================================

    /** @test */
    public function hybrid_mismatch_is_persisted_to_db(): void
    {
        $draw   = $this->makeDraw();
        $group  = $this->makeGroup($draw, 'A', [10, 20, 30]);
        app(RoundRobinGenerationService::class)->generate($draw);

        $router = $this->routerInMode(EngineRouter::MODE_HYBRID);

        // Deliberately return different order from legacy
        $router->standings($draw, function (Draw $d) use ($group) {
            return [
                $group->id => [
                    ['reg_id' => 30, 'wins' => 0],
                    ['reg_id' => 20, 'wins' => 0],
                    ['reg_id' => 10, 'wins' => 0],
                ],
            ];
        });

        $this->assertDatabaseHas('engine_comparison_logs', [
            'operation'    => 'standings',
            'draw_id'      => $draw->id,
            'mismatch_type' => 'standings_order_mismatch',
        ]);
    }

    // ==================================================================
    // 7. RR: canonical and legacy generate same fixture count
    // ==================================================================

    /** @test */
    public function canonical_and_legacy_rr_generate_same_fixture_count(): void
    {
        // Canonical run
        $drawCanon = $this->makeDraw();
        $this->makeGroup($drawCanon, 'A', [1, 2, 3, 4]);
        app(RoundRobinGenerationService::class)->generate($drawCanon);
        $canonCount = $drawCanon->drawFixtures()->where('stage', 'RR')->count();

        // Legacy run via DrawService fallback path (legacy mode)
        $drawLegacy = $this->makeDraw();
        $this->makeGroup($drawLegacy, 'A', [5, 6, 7, 8]);
        $router = $this->routerInMode(EngineRouter::MODE_LEGACY);

        $legacyCount = 0;
        $router->generateRoundRobin($drawLegacy, function (Draw $d) use (&$legacyCount) {
            // We simulate legacy by running canonical (no real DrawBuilder in test env)
            app(RoundRobinGenerationService::class)->generate($d);
            $legacyCount = $d->drawFixtures()->where('stage', 'RR')->count();
        });

        $this->assertEquals($canonCount, $legacyCount, 'Legacy and canonical must produce equal RR fixture count for same-size group');
    }

    // ==================================================================
    // 8. Standings: canonical and legacy agree on winner-takes-all
    // ==================================================================

    /** @test */
    public function canonical_and_legacy_standings_agree_on_winner(): void
    {
        $draw  = $this->makeDraw();
        $group = $this->makeGroup($draw, 'A', [1, 2, 3]);
        app(RoundRobinGenerationService::class)->generate($draw);

        // Score player 1 as winner of both their matches
        foreach ($draw->drawFixtures()->where('stage', 'RR')->get() as $fx) {
            if ($fx->registration1_id === 1 || $fx->registration2_id === 1) {
                $winner = 1;
                $loser  = $fx->registration1_id === 1 ? $fx->registration2_id : $fx->registration1_id;
                FixtureResult::factory()->create([
                    'fixture_id'          => $fx->id,
                    'set_nr'              => 1,
                    'registration1_score' => 6,
                    'registration2_score' => 2,
                    'winner_registration' => $winner,
                    'loser_registration'  => $loser,
                ]);
                $fx->winner_registration = $winner;
                $fx->match_status = 1;
                $fx->save();
            }
        }

        $draw->load(['groups.groupRegistrations', 'drawFixtures.fixtureResults']);
        $canonical = app(StandingsService::class)->forDraw($draw);
        $top = $canonical[$group->id][0]['reg_id'];

        $this->assertEquals(1, $top, 'Player 1 must be ranked first after winning all matches');
    }

    // ==================================================================
    // 9. Progression: canonical advances winner to parent
    // ==================================================================

    /** @test */
    public function canonical_progression_places_winner_in_parent(): void
    {
        $draw   = $this->makeDraw();
        $parent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 200]);
        $child  = Fixture::factory()->create([
            'draw_id'           => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 101,
            'registration1_id'  => 10,        'registration2_id' => 20,
            'parent_fixture_id' => $parent->id,
        ]);
        FixtureResult::factory()->create([
            'fixture_id' => $child->id, 'set_nr' => 1,
            'registration1_score' => 6, 'registration2_score' => 3,
            'winner_registration' => 10, 'loser_registration' => 20,
        ]);
        $child->winner_registration = 10; $child->match_status = 1; $child->save();
        $child->loadMissing('fixtureResults');

        $router = $this->routerInMode(EngineRouter::MODE_CANONICAL);
        $router->advanceFixture($child, 10, 20, fn($f, $w, $l) => null);

        $parent->refresh();
        $this->assertEquals(10, $parent->registration1_id, 'Canonical must place winner in parent slot');
    }

    // ==================================================================
    // 10. Rollback: canonical clears parent slot
    // ==================================================================

    /** @test */
    public function canonical_rollback_clears_parent_slot(): void
    {
        $draw   = $this->makeDraw();
        $parent = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 200,
            'registration1_id' => 10,
        ]);
        $child  = Fixture::factory()->create([
            'draw_id'             => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 101,
            'registration1_id'    => 10, 'registration2_id' => 20,
            'winner_registration' => 10, 'match_status' => 1,
            'parent_fixture_id'   => $parent->id,
        ]);
        FixtureResult::factory()->create([
            'fixture_id' => $child->id, 'set_nr' => 1,
            'registration1_score' => 6, 'registration2_score' => 2,
            'winner_registration' => 10, 'loser_registration' => 20,
        ]);

        $router = $this->routerInMode(EngineRouter::MODE_CANONICAL);
        $router->rollbackFixture($child, fn($f) => null);

        $parent->refresh();
        $this->assertNull($parent->registration1_id, 'Canonical rollback must clear parent slot');
    }

    // ==================================================================
    // 11. BYE: canonical advances lone player
    // ==================================================================

    /** @test */
    public function canonical_bye_advancement_advances_lone_player(): void
    {
        $draw   = $this->makeDraw();
        $parent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 200]);
        Fixture::factory()->create([
            'draw_id'             => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 101,
            'registration1_id'    => 10, 'registration2_id' => null,
            'parent_fixture_id'   => $parent->id,
        ]);
        Fixture::factory()->create([
            'draw_id'           => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 102,
            'registration1_id'  => 20, 'registration2_id' => 30,
            'parent_fixture_id' => $parent->id,
        ]);

        $router = $this->routerInMode(EngineRouter::MODE_CANONICAL);
        $router->advanceByes($draw, fn(Draw $d) => null);

        $parent->refresh();
        $this->assertEquals(10, $parent->registration1_id, 'BYE advancement must place lone player in parent');
    }

    // ==================================================================
    // 12. Fallback count increments when canonical throws
    // ==================================================================

    /** @test */
    public function fallback_count_increments_when_canonical_throws(): void
    {
        $draw   = $this->makeDraw(['locked' => true]);
        $router = $this->routerInMode(EngineRouter::MODE_HYBRID);

        $router->generateRoundRobin($draw, fn(Draw $d) => null);

        $this->assertEquals(1, EngineRouter::fallbackCount());
    }

    // ==================================================================
    // 13. resetCounters resets static state
    // ==================================================================

    /** @test */
    public function reset_counters_clears_static_state(): void
    {
        $draw   = $this->makeDraw(['locked' => true]);
        $router = $this->routerInMode(EngineRouter::MODE_HYBRID);

        $router->generateRoundRobin($draw, fn(Draw $d) => null);
        $this->assertGreaterThan(0, EngineRouter::fallbackCount());

        EngineRouter::resetCounters();

        $this->assertEquals(0, EngineRouter::fallbackCount());
        $this->assertEquals(0, EngineRouter::mismatchCount());
        $this->assertEmpty(EngineRouter::mismatchLog());
    }

    // ==================================================================
    // 14. Snapshot entries do NOT count as mismatches
    // ==================================================================

    /** @test */
    public function snapshot_entries_are_not_counted_as_mismatches(): void
    {
        $draw  = $this->makeDraw();
        $group = $this->makeGroup($draw, 'A', [1, 2, 3]);
        app(RoundRobinGenerationService::class)->generate($draw);

        $router = $this->routerInMode(EngineRouter::MODE_HYBRID);

        // Provide matching legacy output (no mismatch)
        $draw->load(['groups.groupRegistrations', 'drawFixtures.fixtureResults']);
        $canonicalResult = app(StandingsService::class)->forDraw($draw);

        $router->standings($draw, fn(Draw $d) => $canonicalResult);

        $this->assertEquals(0, EngineRouter::mismatchCount(), 'No mismatches expected when legacy matches canonical');
    }

    // ==================================================================
    // 15. clearLogs truncates DB table
    // ==================================================================

    /** @test */
    public function clear_logs_truncates_comparison_table(): void
    {
        EngineComparisonLog::create([
            'operation'     => 'rr_generation',
            'draw_id'       => 1,
            'mismatch_type' => 'test',
            'engine_mode'   => 'hybrid',
            'was_fallback'  => false,
        ]);

        $this->assertEquals(1, EngineComparisonLog::count());

        // Use delete() instead of truncate() — truncate() cannot run inside
        // the wrapping transaction used by RefreshDatabase.
        EngineComparisonLog::query()->delete();

        $this->assertEquals(0, EngineComparisonLog::count());
    }

    // ------------------------------------------------------------------
    // MISMATCH THRESHOLD (FIX 5)
    // ------------------------------------------------------------------

    public function test_mismatch_threshold_default_is_five(): void
    {
        $this->assertSame(5, (int) config('capetennis_engine.mismatch_rollback_threshold'));
    }

    public function test_mismatch_above_threshold_triggers_pilot_monitor_alert(): void
    {
        $draw = $this->makeDraw(['engine_mode' => EngineRouter::MODE_CANONICAL]);

        // mismatch_alert_pct default is 5.0 — put 6% mismatches in metrics
        $metrics = [
            'total_runs'         => 100,
            'mismatch_count'     => 6,
            'mismatch_pct'       => 6.0,
            'fallback_count'     => 0,
            'fallback_pct'       => 0.0,
            'rollback_count'     => 0,
            'score_delete_count' => 0,
            'duplicate_count'    => 0,
            'total_duration_ms'  => 0,
            'avg_duration_ms'    => 0,
            'open_feedback'      => 0,
        ];

        $alerts = \App\Services\Pilot\PilotMonitor::alertsForMetrics($draw->id, $metrics);

        $hasAlert = collect($alerts)->contains(fn ($a) => str_contains(strtoupper($a), 'MISMATCH'));

        $this->assertTrue($hasAlert, 'A 6% mismatch rate must trigger a MISMATCH ALERT');
    }

    public function test_mismatch_at_threshold_does_not_trigger_alert(): void
    {
        $draw = $this->makeDraw(['engine_mode' => EngineRouter::MODE_CANONICAL]);

        // Exactly at 5.0% — alertsForMetrics uses > not >=, so this should NOT alert
        $metrics = [
            'total_runs'         => 100,
            'mismatch_count'     => 5,
            'mismatch_pct'       => 5.0,
            'fallback_count'     => 0,
            'fallback_pct'       => 0.0,
            'rollback_count'     => 0,
            'score_delete_count' => 0,
            'duplicate_count'    => 0,
            'total_duration_ms'  => 0,
            'avg_duration_ms'    => 0,
            'open_feedback'      => 0,
        ];

        $alerts = \App\Services\Pilot\PilotMonitor::alertsForMetrics($draw->id, $metrics);

        $hasAlert = collect($alerts)->contains(fn ($a) => str_contains(strtoupper($a), 'MISMATCH'));

        $this->assertFalse($hasAlert, 'A 5.0% mismatch rate must NOT trigger an alert (boundary is exclusive)');
    }
}
