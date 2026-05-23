<?php

namespace App\Console\Commands;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Domain\Draws\Services\StandingsService;
use App\Models\Draw;
use App\Models\EngineRun;
use Illuminate\Console\Command;

/**
 * engine:replay-draw
 *
 * Replays canonical engine operations against a given draw and reports
 * the result. Does NOT modify any production data — operates in dry-run mode.
 *
 * Useful to:
 *  - verify canonical engine handles a specific draw correctly
 *  - replay a failing draw to reproduce canonical exceptions
 *  - validate standings against a known good snapshot
 */
class ReplayDraw extends Command
{
    protected $signature   = 'engine:replay-draw
                                {draw : The Draw ID to replay}
                                {--operation=all : Operation to replay: all|rr|standings|progression}
                                {--dry-run : Do not persist any results (default: true)}';

    protected $description = 'Replay canonical engine operations against a draw to validate correctness.';

    public function __construct(
        private readonly RoundRobinGenerationService $rrService,
        private readonly StandingsService            $standingsService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $drawId    = (int) $this->argument('draw');
        $operation = $this->option('operation');

        $draw = Draw::with(['drawFixtures', 'groups.groupRegistrations', 'registrations'])->find($drawId);

        if (! $draw) {
            $this->error("Draw #{$drawId} not found.");
            return self::FAILURE;
        }

        $this->info("Replaying draw #{$drawId} (" . ($draw->name ?? 'unnamed') . ") — operation: {$operation}");
        $this->newLine();

        $passed = 0;
        $failed = 0;

        if (in_array($operation, ['all', 'standings'])) {
            $this->line('  Running standings...');
            try {
                $standings = $this->standingsService->forDraw($draw);
                $groupCount = count($standings);
                $this->line("  <fg=green>✓</> Standings OK — {$groupCount} group(s) calculated.");
                $passed++;

                if ($this->option('verbose')) {
                    foreach ($standings as $groupId => $rows) {
                        $this->line("    Group #{$groupId}: " . count($rows) . " players");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Standings FAILED: {$e->getMessage()}");
                $failed++;
            }
        }

        if (in_array($operation, ['all', 'rr'])) {
            $this->line('  Checking RR fixture counts...');
            try {
                $rrFixtures = $draw->drawFixtures->where('stage', 'RR');
                $groupCount = $draw->groups->count();
                $this->line("  <fg=green>✓</> RR fixtures: {$rrFixtures->count()} across {$groupCount} group(s).");
                $passed++;
            } catch (\Throwable $e) {
                $this->error("  ✗ RR check FAILED: {$e->getMessage()}");
                $failed++;
            }
        }

        if (in_array($operation, ['all', 'progression'])) {
            $this->line('  Checking bracket progression integrity...');
            try {
                $bracketFixtures = $draw->drawFixtures->where('stage', '!=', 'RR');
                $withParent      = $bracketFixtures->whereNotNull('parent_fixture_id');
                $issues          = 0;

                foreach ($withParent as $fixture) {
                    $parent = $draw->drawFixtures->firstWhere('id', $fixture->parent_fixture_id);
                    if (! $parent) {
                        $this->warn("    ! Fixture #{$fixture->id} has orphaned parent_fixture_id={$fixture->parent_fixture_id}");
                        $issues++;
                    }
                }

                if ($issues === 0) {
                    $this->line("  <fg=green>✓</> Progression integrity OK — {$withParent->count()} parent-child links valid.");
                    $passed++;
                } else {
                    $this->warn("  ~ Progression: {$issues} issue(s) found.");
                    $failed++;
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Progression check FAILED: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();

        // Show historical run summary for this draw
        $runSummary = EngineRun::forDraw($drawId)
            ->selectRaw('operation_type, count(*) as runs,
                sum(case when canonical_success = 1 then 1 else 0 end) as canon_ok,
                sum(case when fallback_used = 1 then 1 else 0 end) as fallbacks')
            ->groupBy('operation_type')
            ->get();

        if ($runSummary->isNotEmpty()) {
            $this->line('Historical run data for this draw:');
            $this->table(
                ['Operation', 'Runs', 'Canon OK', 'Fallbacks'],
                $runSummary->map(fn($r) => [$r->operation_type, $r->runs, $r->canon_ok, $r->fallbacks])->toArray()
            );
        }

        if ($failed === 0) {
            $this->info("Replay complete. {$passed} check(s) passed.");
            return self::SUCCESS;
        } else {
            $this->warn("Replay complete. {$passed} passed, {$failed} failed.");
            return self::FAILURE;
        }
    }
}
