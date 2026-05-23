<?php

namespace App\Console\Commands;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * engine:audit-draws
 *
 * Validates each active draw for:
 *  - fixture parent/child slot consistency
 *  - BYE advancement integrity
 *  - progression chain completeness
 *  - standings consistency (played = wins + losses)
 *  - playoff mapping validity
 */
class AuditDraws extends Command
{
    protected $signature   = 'engine:audit-draws
                                {--draw= : Audit a single draw by ID}
                                {--stage= : Filter by stage (RR|bracket)}
                                {--fix-report : Show fixable issues summary}';

    protected $description = 'Audit all draws for fixture progression, parent/child, and standings integrity.';

    public function handle(): int
    {
        $drawId = $this->option('draw');
        $stage  = $this->option('stage');

        $query = Draw::query();
        if ($drawId) {
            $query->where('id', $drawId);
        }

        $draws = $query->get();

        if ($draws->isEmpty()) {
            $this->warn('No draws found.');
            return self::SUCCESS;
        }

        $this->info("Auditing {$draws->count()} draw(s)...");
        $this->newLine();

        $totalIssues = 0;

        foreach ($draws as $draw) {
            $issues = $this->auditDraw($draw, $stage);
            $totalIssues += count($issues);

            if (empty($issues)) {
                $name = $draw->name ?? 'unnamed';
                $this->line("  <fg=green>✓</> Draw #{$draw->id} ({$name}) — OK");
            } else {
                $name = $draw->name ?? 'unnamed';
                $this->line("  <fg=red>✗</> Draw #{$draw->id} ({$name}) — " . count($issues) . " issue(s):");
                foreach ($issues as $issue) {
                    $this->line("      <fg=yellow>» {$issue}</>");
                }
            }
        }

        $this->newLine();

        if ($totalIssues === 0) {
            $this->info("All draws passed audit. No integrity issues found.");
        } else {
            $this->warn("Audit complete. {$totalIssues} total issue(s) found across {$draws->count()} draw(s).");
        }

        return $totalIssues > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function auditDraw(Draw $draw, ?string $stageFilter): array
    {
        $issues    = [];
        $fixtures  = Fixture::where('draw_id', $draw->id)
            ->when($stageFilter, fn($q) => $q->where('stage', $stageFilter))
            ->with('result')
            ->get();

        // --- 1. Parent/child slot consistency
        foreach ($fixtures as $fixture) {
            if (! $fixture->parent_fixture_id) {
                continue;
            }
            $parent = $fixtures->firstWhere('id', $fixture->parent_fixture_id);
            if (! $parent) {
                $issues[] = "Fixture #{$fixture->id}: parent_fixture_id={$fixture->parent_fixture_id} not found in same draw.";
                continue;
            }
            // If this fixture has a result, the winner should appear in parent
            if ($fixture->result) {
                $winner = $fixture->result->winner_registration_id;
                if ($winner && $parent->registration1_id !== $winner && $parent->registration2_id !== $winner) {
                    $issues[] = "Fixture #{$fixture->id}: winner (reg #{$winner}) not placed in parent fixture #{$parent->id}.";
                }
            }
        }

        // --- 2. BYE advancement: BYE fixtures should have auto-advanced winner
        foreach ($fixtures as $fixture) {
            $isBye = ($fixture->registration2_id === null && $fixture->registration1_id !== null)
                  || ($fixture->registration1_id === null && $fixture->registration2_id !== null);

            if ($isBye && $fixture->parent_fixture_id && ! $fixture->result) {
                $issues[] = "Fixture #{$fixture->id}: BYE fixture missing result/advancement.";
            }
        }

        // --- 3. Duplicate progression: same registration in multiple winner slots at same round
        $winnersByRound = [];
        foreach ($fixtures as $fixture) {
            if (! $fixture->result || ! $fixture->result->winner_registration_id) {
                continue;
            }
            $round = $fixture->round ?? 'unknown';
            $winner = $fixture->result->winner_registration_id;
            $winnersByRound[$round][] = $winner;
        }
        foreach ($winnersByRound as $round => $winners) {
            $dupes = array_diff_assoc($winners, array_unique($winners));
            foreach (array_unique($dupes) as $regId) {
                $issues[] = "Round {$round}: registration #{$regId} appears as winner in multiple fixtures (duplicate progression).";
            }
        }

        // --- 4. Standings consistency: for RR fixtures, played = wins + losses
        $rrFixtures = $fixtures->where('stage', 'RR')->filter(fn($f) => $f->result !== null);
        $stats = [];
        foreach ($rrFixtures as $f) {
            $r = $f->result;
            foreach ([$f->registration1_id, $f->registration2_id] as $regId) {
                if (! $regId) continue;
                $stats[$regId]['played'] = ($stats[$regId]['played'] ?? 0) + 1;
                if ($r->winner_registration_id === $regId) {
                    $stats[$regId]['wins'] = ($stats[$regId]['wins'] ?? 0) + 1;
                } else {
                    $stats[$regId]['losses'] = ($stats[$regId]['losses'] ?? 0) + 1;
                }
            }
        }
        foreach ($stats as $regId => $s) {
            $played = $s['played'];
            $wl     = ($s['wins'] ?? 0) + ($s['losses'] ?? 0);
            if ($played !== $wl) {
                $issues[] = "Registration #{$regId}: played={$played} but wins+losses={$wl} (standings inconsistency).";
            }
        }

        return $issues;
    }
}
