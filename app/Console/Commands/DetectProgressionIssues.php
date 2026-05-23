<?php

namespace App\Console\Commands;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Console\Command;

/**
 * engine:detect-progression-issues
 *
 * Deep-scan all bracket fixtures for:
 *  - orphaned fixtures (parent deleted)
 *  - slots filled with wrong winner
 *  - players in two places simultaneously
 *  - unadvanced completed fixtures with parent slots empty
 */
class DetectProgressionIssues extends Command
{
    protected $signature   = 'engine:detect-progression-issues
                                {--draw= : Scan a single draw by ID}
                                {--verbose : Show per-fixture detail}';

    protected $description = 'Detect bracket progression inconsistencies across all draws.';

    public function handle(): int
    {
        $drawId = $this->option('draw');

        $query = Draw::query();
        if ($drawId) {
            $query->where('id', $drawId);
        }
        $draws = $query->get();

        if ($draws->isEmpty()) {
            $this->warn('No draws found.');
            return self::SUCCESS;
        }

        $totalIssues = 0;

        foreach ($draws as $draw) {
            $issues = $this->scanDraw($draw);
            $totalIssues += count($issues);

            if (empty($issues)) {
                $this->line("  <fg=green>✓</> Draw #{$draw->id} — no progression issues");
            } else {
                $this->line("  <fg=red>✗</> Draw #{$draw->id} — " . count($issues) . " progression issue(s):");
                foreach ($issues as $issue) {
                    $this->line("      <fg=yellow>» {$issue}</>");
                }
            }
        }

        $this->newLine();
        if ($totalIssues === 0) {
            $this->info('No progression issues detected.');
        } else {
            $this->error("{$totalIssues} progression issue(s) detected. Review draw integrity before enabling canonical-only mode.");
        }

        return $totalIssues > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function scanDraw(Draw $draw): array
    {
        $issues   = [];
        $fixtures = Fixture::where('draw_id', $draw->id)->with('result')->get()->keyBy('id');

        foreach ($fixtures as $fixture) {
            // Orphaned parent reference
            if ($fixture->parent_fixture_id && ! $fixtures->has($fixture->parent_fixture_id)) {
                $issues[] = "Fixture #{$fixture->id}: parent_fixture_id={$fixture->parent_fixture_id} is orphaned (parent missing).";
            }

            // Completed fixture with empty parent slot
            if ($fixture->result && $fixture->parent_fixture_id) {
                $parent = $fixtures->get($fixture->parent_fixture_id);
                $winner = $fixture->result->winner_registration_id;
                if ($parent && $winner) {
                    if ($parent->registration1_id !== $winner && $parent->registration2_id !== $winner) {
                        $issues[] = "Fixture #{$fixture->id}: completed, winner reg#{$winner} not placed into parent fixture #{$parent->id} (slots: {$parent->registration1_id}, {$parent->registration2_id}).";
                    }
                }
            }
        }

        // Players in two simultaneous active bracket slots (same round)
        $roundSlots = [];
        foreach ($fixtures as $fixture) {
            $round = $fixture->round ?? 'x';
            foreach ([$fixture->registration1_id, $fixture->registration2_id] as $regId) {
                if (! $regId) continue;
                $roundSlots[$round][$regId][] = $fixture->id;
            }
        }
        foreach ($roundSlots as $round => $players) {
            foreach ($players as $regId => $fixtureIds) {
                if (count($fixtureIds) > 1) {
                    $ids = implode(', ', $fixtureIds);
                    $issues[] = "Round {$round}: registration #{$regId} appears in multiple fixtures [{$ids}] simultaneously.";
                }
            }
        }

        return $issues;
    }
}
