<?php

namespace Tests\Feature\Draw;

use App\Domain\Engine\EngineRouter;
use App\Models\Draw;
use App\Models\EngineMismatch;
use App\Models\EngineRun;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EngineCanonicalPilotTest
 *
 * Tests per-draw/per-event engine mode override, safety guard,
 * and rollback functionality.
 */
class EngineCanonicalPilotTest extends TestCase
{
    use RefreshDatabase;

    private function makeDraw(array $attrs = []): Draw
    {
        return Draw::factory()->create(array_merge([
            'drawName'    => 'Test Draw',
            'drawType_id' => 1,
            'event_id'    => 1,
        ], $attrs));
    }

    private function makeEvent(array $attrs = []): Event
    {
        return Event::factory()->create(array_merge([
            'name'       => 'Test Event',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->addDays(2)->toDateString(),
        ], $attrs));
    }

    // ------------------------------------------------------------------
    // 1. effectiveEngineMode inherits global config when no override
    // ------------------------------------------------------------------
    public function test_effective_mode_inherits_global_when_no_override(): void
    {
        config(['capetennis_engine.mode' => 'hybrid']);
        $draw = $this->makeDraw();

        $this->assertSame('hybrid', $draw->effectiveEngineMode());
    }

    // ------------------------------------------------------------------
    // 2. Draw-level override takes precedence over global config
    // ------------------------------------------------------------------
    public function test_draw_override_takes_precedence_over_global(): void
    {
        config(['capetennis_engine.mode' => 'hybrid']);
        $draw = $this->makeDraw(['engine_mode' => 'legacy']);

        $this->assertSame('legacy', $draw->effectiveEngineMode());
    }

    // ------------------------------------------------------------------
    // 3. Event-level override used when draw has no override
    // ------------------------------------------------------------------
    public function test_event_override_used_when_draw_has_none(): void
    {
        config(['capetennis_engine.mode' => 'hybrid']);
        $event = $this->makeEvent(['engine_mode' => 'canonical']);
        $draw  = $this->makeDraw(['event_id' => $event->id, 'engine_mode' => null]);

        // Reload relation
        $draw->load('event');

        $this->assertSame('canonical', $draw->effectiveEngineMode());
    }

    // ------------------------------------------------------------------
    // 4. Draw-level override beats event-level override
    // ------------------------------------------------------------------
    public function test_draw_override_beats_event_override(): void
    {
        $event = $this->makeEvent(['engine_mode' => 'canonical']);
        $draw  = $this->makeDraw(['event_id' => $event->id, 'engine_mode' => 'legacy']);

        $draw->load('event');

        $this->assertSame('legacy', $draw->effectiveEngineMode());
    }

    // ------------------------------------------------------------------
    // 5. canonicalAllowed returns true when no unresolved mismatches
    // ------------------------------------------------------------------
    public function test_canonical_allowed_when_no_mismatches(): void
    {
        $draw = $this->makeDraw();

        $this->assertTrue($draw->canonicalAllowed());
    }

    // ------------------------------------------------------------------
    // 6. canonicalAllowed returns false when unresolved HIGH mismatch exists
    // ------------------------------------------------------------------
    public function test_canonical_blocked_by_high_severity_mismatch(): void
    {
        $draw = $this->makeDraw();

        EngineMismatch::create([
            'draw_id'        => $draw->id,
            'operation_type' => 'progression',
            'mismatch_type'  => 'winner_not_placed_in_parent',
            'severity'       => 'high',
            'resolved'       => false,
            'created_at'     => now(),
        ]);

        $this->assertFalse($draw->canonicalAllowed());
    }

    // ------------------------------------------------------------------
    // 7. canonicalAllowed returns false for unresolved MEDIUM mismatch
    // ------------------------------------------------------------------
    public function test_canonical_blocked_by_medium_severity_mismatch(): void
    {
        $draw = $this->makeDraw();

        EngineMismatch::create([
            'draw_id'        => $draw->id,
            'operation_type' => 'standings',
            'mismatch_type'  => 'standings_order_mismatch',
            'severity'       => 'medium',
            'resolved'       => false,
            'created_at'     => now(),
        ]);

        $this->assertFalse($draw->canonicalAllowed());
    }

    // ------------------------------------------------------------------
    // 8. canonicalAllowed returns true when all mismatches are resolved
    // ------------------------------------------------------------------
    public function test_canonical_allowed_when_all_mismatches_resolved(): void
    {
        $draw = $this->makeDraw();

        EngineMismatch::create([
            'draw_id'        => $draw->id,
            'operation_type' => 'progression',
            'mismatch_type'  => 'winner_not_placed_in_parent',
            'severity'       => 'high',
            'resolved'       => true,
            'created_at'     => now(),
        ]);

        $this->assertTrue($draw->canonicalAllowed());
    }

    // ------------------------------------------------------------------
    // 9. canonicalSafetyCheck returns not-allowed with reason
    // ------------------------------------------------------------------
    public function test_canonical_safety_check_returns_reason_when_blocked(): void
    {
        $draw = $this->makeDraw();

        EngineMismatch::create([
            'draw_id'        => $draw->id,
            'operation_type' => 'progression',
            'mismatch_type'  => 'winner_not_placed_in_parent',
            'severity'       => 'high',
            'resolved'       => false,
            'created_at'     => now(),
        ]);

        $result = EngineRouter::canonicalSafetyCheck($draw);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('HIGH severity', $result['reason']);
    }

    // ------------------------------------------------------------------
    // 10. forDraw() returns canonical mode when safe
    // ------------------------------------------------------------------
    public function test_for_draw_returns_canonical_when_safe(): void
    {
        $draw = $this->makeDraw(['engine_mode' => 'canonical']);

        $router = app(EngineRouter::class)->forDraw($draw);

        $this->assertSame('canonical', $router->mode());
    }

    // ------------------------------------------------------------------
    // 11. forDraw() falls back to hybrid when canonical is blocked
    // ------------------------------------------------------------------
    public function test_for_draw_falls_back_to_hybrid_when_canonical_blocked(): void
    {
        $draw = $this->makeDraw(['engine_mode' => 'canonical']);

        EngineMismatch::create([
            'draw_id'        => $draw->id,
            'operation_type' => 'progression',
            'mismatch_type'  => 'winner_not_placed_in_parent',
            'severity'       => 'high',
            'resolved'       => false,
            'created_at'     => now(),
        ]);

        $router = app(EngineRouter::class)->forDraw($draw);

        $this->assertSame('hybrid', $router->mode());
    }

    // ------------------------------------------------------------------
    // 12. Rollback command sets draw to legacy and resolves mismatches
    // ------------------------------------------------------------------
    public function test_rollback_command_sets_legacy_and_resolves_mismatches(): void
    {
        $draw = $this->makeDraw(['engine_mode' => 'hybrid']);

        EngineMismatch::create([
            'draw_id'        => $draw->id,
            'operation_type' => 'standings',
            'mismatch_type'  => 'standings_order_mismatch',
            'severity'       => 'medium',
            'resolved'       => false,
            'created_at'     => now(),
        ]);

        $this->artisan('engine:rollback-draw', [
            'draw'    => $draw->id,
            '--force' => true,
        ])->assertExitCode(0);

        $draw->refresh();
        $this->assertSame('legacy', $draw->engine_mode);
        $this->assertSame(0, EngineMismatch::forDraw($draw->id)->unresolved()->count());
    }

    // ------------------------------------------------------------------
    // 13. Rollback command logs EngineRun entry
    // ------------------------------------------------------------------
    public function test_rollback_command_logs_engine_run(): void
    {
        $draw = $this->makeDraw(['engine_mode' => 'hybrid']);

        $this->artisan('engine:rollback-draw', [
            'draw'    => $draw->id,
            '--force' => true,
        ])->assertExitCode(0);

        $run = EngineRun::where('draw_id', $draw->id)
            ->where('operation_type', 'rollback')
            ->first();

        $this->assertNotNull($run);
        $this->assertSame('legacy', $run->engine_mode);
        $this->assertTrue((bool) $run->legacy_success);
    }

    // ------------------------------------------------------------------
    // 14. Rollback on already-legacy draw is a no-op
    // ------------------------------------------------------------------
    public function test_rollback_on_legacy_draw_is_noop(): void
    {
        $draw = $this->makeDraw(['engine_mode' => 'legacy']);

        $this->artisan('engine:rollback-draw', [
            'draw'    => $draw->id,
            '--force' => true,
        ])->assertExitCode(0);

        $draw->refresh();
        $this->assertSame('legacy', $draw->engine_mode);
    }

    // ------------------------------------------------------------------
    // 15. EngineRun confidence score reflects zero runs initially
    // ------------------------------------------------------------------
    public function test_confidence_score_null_when_no_runs(): void
    {
        $score = EngineRun::confidenceScore();

        $this->assertNull($score['confidence_score']);
        $this->assertSame('no data', $score['confidence_label']);
    }

    // ------------------------------------------------------------------
    // 16. LOW severity mismatch does not block canonical
    // ------------------------------------------------------------------
    public function test_low_severity_mismatch_does_not_block_canonical(): void
    {
        $draw = $this->makeDraw();

        EngineMismatch::create([
            'draw_id'        => $draw->id,
            'operation_type' => 'bracket_render',
            'mismatch_type'  => 'comparison_threw',
            'severity'       => 'low',
            'resolved'       => false,
            'created_at'     => now(),
        ]);

        $this->assertTrue($draw->canonicalAllowed());
    }
}
