<?php

namespace App\Console\Commands;

use App\Domain\Engine\EngineRouter;
use App\Models\Draw;
use App\Models\EngineRun;
use App\Models\EngineMismatch;
use Illuminate\Console\Command;

/**
 * engine:compare-engines
 *
 * Reports parity statistics from persisted engine run/mismatch data.
 * Shows canonical confidence score and identifies draws with the
 * highest mismatch/fallback rates.
 */
class CompareEngines extends Command
{
    protected $signature   = 'engine:compare-engines
                                {--draw= : Compare engines for a specific draw ID}
                                {--last= : Consider only the last N runs (default: all)}';

    protected $description = 'Report canonical vs legacy engine parity from run/mismatch log data.';

    public function handle(): int
    {
        $drawId = $this->option('draw');
        $last   = (int) $this->option('last');

        $this->info('=== Engine Comparison Report ===');
        $this->newLine();

        // --- Confidence score
        $confidence = EngineRun::confidenceScore();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Engine Mode',          config('capetennis_engine.mode', 'hybrid')],
                ['Total Runs',           $confidence['total_runs']],
                ['Canonical Runs',       $confidence['canonical_runs']],
                ['Parity %',             $confidence['parity_pct'] !== null ? $confidence['parity_pct'] . '%' : '--'],
                ['Mismatch %',           $confidence['mismatch_pct'] !== null ? $confidence['mismatch_pct'] . '%' : '--'],
                ['Fallback %',           $confidence['fallback_pct'] !== null ? $confidence['fallback_pct'] . '%' : '--'],
                ['Progression OK %',     $confidence['progression_ok_pct'] !== null ? $confidence['progression_ok_pct'] . '%' : '--'],
                ['Standings OK %',       $confidence['standings_ok_pct'] !== null ? $confidence['standings_ok_pct'] . '%' : '--'],
                ['Confidence Score',     $confidence['confidence_score'] !== null ? $confidence['confidence_score'] . '%' : '--'],
                ['Confidence Label',     $confidence['confidence_label']],
            ]
        );

        // --- Top mismatch types
        $topTypes = EngineMismatch::topTypes(10);
        if (! empty($topTypes)) {
            $this->newLine();
            $this->warn('Top Mismatch Types:');
            $this->table(
                ['Mismatch Type', 'Operation', 'Count'],
                array_map(fn($r) => [$r['mismatch_type'], $r['operation_type'], $r['total']], $topTypes)
            );
        }

        // --- Draws with most issues
        $worstDraws = EngineRun::selectRaw('draw_id, count(*) as runs,
            sum(case when mismatch_detected = 1 then 1 else 0 end) as mismatches,
            sum(case when fallback_used = 1 then 1 else 0 end) as fallbacks')
            ->groupBy('draw_id')
            ->having('mismatches', '>', 0)
            ->orHaving('fallbacks', '>', 0)
            ->orderByDesc('mismatches')
            ->limit(10)
            ->get();

        if ($worstDraws->isNotEmpty()) {
            $this->newLine();
            $this->warn('Draws with Issues:');
            $this->table(
                ['Draw ID', 'Total Runs', 'Mismatches', 'Fallbacks'],
                $worstDraws->map(fn($r) => [$r->draw_id ?? '--', $r->runs, $r->mismatches, $r->fallbacks])->toArray()
            );
        }

        // --- Blockers summary
        $this->newLine();
        $this->info('=== Canonical-Only Readiness ===');
        $score = $confidence['confidence_score'];
        if ($score === null) {
            $this->warn('  No run data. Run in hybrid mode on production traffic first.');
        } elseif ($score >= 98) {
            $this->info("  ✓ Confidence {$score}% — canonical-only mode may be safe.");
        } elseif ($score >= 90) {
            $this->warn("  ~ Confidence {$score}% — review remaining mismatches before cutover.");
        } else {
            $this->error("  ✗ Confidence {$score}% — NOT ready for canonical-only mode. Resolve issues first.");
        }

        return self::SUCCESS;
    }
}
