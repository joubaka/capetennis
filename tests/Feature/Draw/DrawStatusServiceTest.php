<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\DrawStatusService;
use App\Models\Draw;
use App\Models\DrawGroup;
use App\Models\DrawGroupRegistration;
use App\Models\Fixture;
use App\Models\FixtureResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DrawStatusService unit/integration tests.
 *
 * Covers status(), isRRComplete(), and hasAnyResults() across all workflow states:
 *
 *   A1.  Empty draw — all incomplete
 *   A2.  Groups assigned, no fixtures
 *   A3.  Fixtures generated, no scores
 *   A4.  Partial scores
 *   A5.  All RR scores complete
 *   A5b. Warns "playoffs can now be generated" when RR complete, no brackets
 *   A6.  Published flag reflected
 *   A7.  Locked flag reflected
 *   A8.  isRRComplete helper
 *   A9.  hasAnyResults helper
 *   A10. Bracket fixtures correctly detected
 */
class DrawStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────

    private function svc(): DrawStatusService
    {
        return new DrawStatusService();
    }

    private function draw(array $attrs = []): Draw
    {
        return Draw::factory()->create(array_merge([
            'locked'    => false,
            'published' => false,
        ], $attrs));
    }

    private function groupWithPlayer(Draw $draw): DrawGroup
    {
        $group = DrawGroup::factory()->create(['draw_id' => $draw->id]);
        DrawGroupRegistration::factory()->create([
            'draw_group_id'   => $group->id,
            'registration_id' => 1,
        ]);
        return $group;
    }

    private function rrFixture(Draw $draw): Fixture
    {
        return Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage'   => 'RR',
        ]);
    }

    private function scoreFixture(Fixture $fixture): FixtureResult
    {
        return FixtureResult::factory()->create([
            'fixture_id'          => $fixture->id,
            'set_nr'              => 1,
            'registration1_score' => 6,
            'registration2_score' => 4,
        ]);
    }

    // ─── A1. Empty draw ───────────────────────────────────────────────

    public function test_empty_draw_reports_all_incomplete(): void
    {
        $draw   = $this->draw();
        $status = $this->svc()->status($draw);

        $this->assertFalse($status['groups_configured'],  'groups_configured should be false');
        $this->assertFalse($status['fixtures_generated'], 'fixtures_generated should be false');
        $this->assertSame(0,   $status['rr_total']);
        $this->assertSame(0,   $status['rr_played']);
        $this->assertSame(0,   $status['rr_complete_pct']);
        $this->assertFalse($status['rr_complete']);
        $this->assertFalse($status['standings_ready']);
        $this->assertFalse($status['brackets_generated']);
        $this->assertFalse($status['locked']);
        $this->assertFalse($status['published']);
        $this->assertNotEmpty($status['warnings'], 'Empty draw should produce at least one warning');
    }

    // ─── A2. Groups assigned, no fixtures ────────────────────────────

    public function test_groups_configured_but_no_fixtures(): void
    {
        $draw = $this->draw();
        $this->groupWithPlayer($draw);

        $status = $this->svc()->status($draw);

        $this->assertTrue($status['groups_configured'],    'groups_configured should be true');
        $this->assertFalse($status['fixtures_generated'],  'fixtures not yet generated');
        $this->assertFalse($status['rr_complete']);
        $this->assertFalse($status['standings_ready']);
        $this->assertFalse($status['brackets_generated']);
    }

    // ─── A3. Fixtures generated, no scores ───────────────────────────

    public function test_fixtures_generated_no_scores(): void
    {
        $draw = $this->draw();
        $this->groupWithPlayer($draw);
        $this->rrFixture($draw);
        $this->rrFixture($draw);

        $status = $this->svc()->status($draw);

        $this->assertTrue($status['fixtures_generated']);
        $this->assertSame(2, $status['rr_total']);
        $this->assertSame(0, $status['rr_played']);
        $this->assertSame(0, $status['rr_complete_pct']);
        $this->assertFalse($status['rr_complete']);
        $this->assertFalse($status['standings_ready']);
        $this->assertFalse($status['brackets_generated']);
        $this->assertNotEmpty($status['warnings']);
    }

    // ─── A4. Partial scores ───────────────────────────────────────────

    public function test_partial_scores_reported_correctly(): void
    {
        $draw = $this->draw();
        $this->groupWithPlayer($draw);
        $f1 = $this->rrFixture($draw);
        $f2 = $this->rrFixture($draw);
        $this->scoreFixture($f1); // only f1 scored

        $status = $this->svc()->status($draw);

        $this->assertSame(2,  $status['rr_total']);
        $this->assertSame(1,  $status['rr_played']);
        $this->assertSame(50, $status['rr_complete_pct']);
        $this->assertFalse($status['rr_complete']);
        $this->assertFalse($status['standings_ready']);
        $this->assertFalse($status['brackets_generated']);
    }

    // ─── A5. All RR complete ─────────────────────────────────────────

    public function test_all_rr_scored_reports_complete(): void
    {
        $draw = $this->draw();
        $this->groupWithPlayer($draw);
        $f1 = $this->rrFixture($draw);
        $f2 = $this->rrFixture($draw);
        $this->scoreFixture($f1);
        $this->scoreFixture($f2);

        $status = $this->svc()->status($draw);

        $this->assertSame(2,   $status['rr_total']);
        $this->assertSame(2,   $status['rr_played']);
        $this->assertSame(100, $status['rr_complete_pct']);
        $this->assertTrue($status['rr_complete']);
        $this->assertTrue($status['standings_ready']);
        $this->assertFalse($status['brackets_generated'], 'No bracket fixtures created yet');
    }

    // A5b. Hints that playoffs can now be generated
    public function test_warns_playoffs_ready_when_rr_complete_no_brackets(): void
    {
        $draw = $this->draw();
        $this->groupWithPlayer($draw);
        $f = $this->rrFixture($draw);
        $this->scoreFixture($f);

        $status      = $this->svc()->status($draw);
        $warningText = implode(' ', $status['warnings']);

        $this->assertStringContainsStringIgnoringCase('playoff', $warningText,
            'Should hint that playoffs can now be generated');
    }

    // ─── A6. Published draw ───────────────────────────────────────────

    public function test_published_flag_reflected_in_status(): void
    {
        $draw   = $this->draw(['published' => true]);
        $status = $this->svc()->status($draw);

        $this->assertTrue($status['published']);
        $this->assertFalse($status['locked']);
    }

    // ─── A7. Locked draw ─────────────────────────────────────────────

    public function test_locked_flag_reflected_in_status(): void
    {
        $draw   = $this->draw(['locked' => true]);
        $status = $this->svc()->status($draw);

        $this->assertTrue($status['locked']);
        $this->assertFalse($status['published']);
    }

    // ─── A8. isRRComplete helper ──────────────────────────────────────

    public function test_is_rr_complete_false_when_no_fixtures(): void
    {
        $this->assertFalse($this->svc()->isRRComplete($this->draw()));
    }

    public function test_is_rr_complete_false_when_partial(): void
    {
        $draw = $this->draw();
        $f1   = $this->rrFixture($draw);
        $this->rrFixture($draw); // unscored
        $this->scoreFixture($f1);

        $this->assertFalse($this->svc()->isRRComplete($draw));
    }

    public function test_is_rr_complete_true_when_all_scored(): void
    {
        $draw = $this->draw();
        $f1   = $this->rrFixture($draw);
        $f2   = $this->rrFixture($draw);
        $this->scoreFixture($f1);
        $this->scoreFixture($f2);

        $this->assertTrue($this->svc()->isRRComplete($draw));
    }

    // ─── A9. hasAnyResults helper ─────────────────────────────────────

    public function test_has_any_results_false_when_no_fixtures(): void
    {
        $this->assertFalse($this->svc()->hasAnyResults($this->draw()));
    }

    public function test_has_any_results_false_when_fixtures_unscored(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw);
        $this->assertFalse($this->svc()->hasAnyResults($draw));
    }

    public function test_has_any_results_true_when_one_fixture_scored(): void
    {
        $draw = $this->draw();
        $f    = $this->rrFixture($draw);
        $this->scoreFixture($f);
        $this->assertTrue($this->svc()->hasAnyResults($draw));
    }

    // ─── A10. Bracket detection ───────────────────────────────────────

    public function test_brackets_detected_when_main_stage_fixture_exists(): void
    {
        $draw = $this->draw();
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN']);

        $this->assertTrue($this->svc()->status($draw)['brackets_generated']);
    }

    public function test_brackets_not_detected_with_only_rr_fixtures(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw);

        $this->assertFalse($this->svc()->status($draw)['brackets_generated']);
    }
}
