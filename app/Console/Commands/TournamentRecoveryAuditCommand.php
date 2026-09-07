<?php

namespace App\Console\Commands;

use App\Domain\Draws\Services\StandingsService;
use App\Models\Draw;
use App\Models\DrawRecoveryCase;
use App\Models\DrawRecoverySnapshot;
use App\Models\Fixture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TournamentRecoveryAuditCommand extends Command
{
    protected $signature = 'draw:recovery-audit {--draw= : Limit the audit to one draw ID} {--json : Output JSON}';

    protected $description = 'Read-only audit for result, progression, playoff-source, and recovery-snapshot integrity.';

    public function handle(StandingsService $standings): int
    {
        $issues = [];
        $draws = Draw::query()->when($this->option('draw'), fn ($query, $id) => $query->whereKey($id));

        foreach ($draws->cursor() as $draw) {
            $fixtures = Fixture::where('draw_id', $draw->id)->with('fixtureResults')->get();
            foreach ($fixtures as $fixture) {
                $participants = collect([$fixture->registration1_id, $fixture->registration2_id])->filter()->map(fn ($id) => (int) $id);
                $meaningfulResults = $fixture->fixtureResults->filter(fn ($result) =>
                    $result->winner_registration || $result->loser_registration
                    || $result->registration1_score !== null || $result->registration2_score !== null
                );
                $resultWinners = $meaningfulResults->pluck('winner_registration')->filter()->map(fn ($id) => (int) $id)->unique();
                $validBye = $participants->count() === 1
                    && ($fixture->winner_registration || $resultWinners->isNotEmpty())
                    && (! $fixture->winner_registration || $participants->contains((int) $fixture->winner_registration))
                    && $resultWinners->every(fn ($id) => $participants->contains($id))
                    && $meaningfulResults->every(fn ($result) => ! $result->loser_registration)
                    && ((int) $fixture->match_status === 3 || $meaningfulResults->isNotEmpty());
                if (($fixture->winner_registration || $meaningfulResults->isNotEmpty())
                    && $participants->count() < 2 && ! $validBye) {
                    $issues[] = $this->issue($draw->id, 'CRITICAL', $fixture->id, 'A scored/resolved fixture is missing a participant.');
                }
                if ($fixture->winner_registration && ! $participants->contains((int) $fixture->winner_registration)) {
                    $issues[] = $this->issue($draw->id, 'CRITICAL', $fixture->id, 'The fixture winner is not one of its participants.');
                }
                foreach ($fixture->fixtureResults as $result) {
                    foreach (['winner_registration', 'loser_registration'] as $column) {
                        if ($result->{$column} && ! $participants->contains((int) $result->{$column})) {
                            $issues[] = $this->issue($draw->id, 'CRITICAL', $fixture->id,
                                "Result set {$result->set_nr} {$column} is not one of the fixture participants.");
                        }
                    }
                }
            }

            $this->auditPositionSources($draw, $fixtures, $standings, $issues);
            $this->auditFixedPlayoffFreshness($draw, $issues);
        }

        $this->auditRecoveryRecords($issues);

        if ($this->option('json')) {
            $this->line(json_encode($issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($issues === []) {
            $this->info('No tournament recovery integrity issues found.');
        } else {
            $this->table(['draw', 'severity', 'fixture', 'issue'], array_map(fn ($issue) => [
                $issue['draw_id'] ?? 'global', $issue['severity'], $issue['fixture_id'] ?? '-', $issue['issue'],
            ], $issues));
        }

        return collect($issues)->contains(fn ($issue) => $issue['severity'] === 'CRITICAL')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function auditPositionSources(Draw $draw, $fixtures, StandingsService $standings, array &$issues): void
    {
        $sourced = $fixtures->where('stage', '!=', 'RR')->filter(fn (Fixture $fixture) =>
            $fixture->registration1_source_group_id || $fixture->registration2_source_group_id
        );
        if ($sourced->isEmpty()) {
            return;
        }

        $rankings = $standings->forDraw($draw);
        foreach ($sourced as $fixture) {
            foreach ([1, 2] as $slot) {
                $groupId = $fixture->{'registration'.$slot.'_source_group_id'};
                $position = $fixture->{'registration'.$slot.'_source_position'};
                if (! $groupId || ! $position) {
                    continue;
                }
                $expected = data_get($rankings, $groupId.'.'.((int) $position - 1).'.reg_id');
                $actual = $fixture->{'registration'.$slot.'_id'};
                if ($expected && (int) $expected !== (int) $actual) {
                    $issues[] = $this->issue($draw->id, 'CRITICAL', $fixture->id,
                        "Playoff slot {$slot} has registration #{$actual}; current group position requires #{$expected}.");
                }
            }
        }
    }

    private function auditFixedPlayoffFreshness(Draw $draw, array &$issues): void
    {
        $playoffCreated = DB::table('fixtures')->where('draw_id', $draw->id)->where('stage', '!=', 'RR')
            ->whereNull('registration1_source_group_id')->whereNull('registration2_source_group_id')->min('created_at');
        if (! $playoffCreated) {
            return;
        }
        $latestRoundRobinResult = DB::table('fixture_results as result')
            ->join('fixtures as fixture', 'fixture.id', '=', 'result.fixture_id')
            ->where('fixture.draw_id', $draw->id)->where('fixture.stage', 'RR')->max('result.updated_at');
        if ($latestRoundRobinResult && $latestRoundRobinResult > $playoffCreated) {
            $issues[] = $this->issue($draw->id, 'WARN', null,
                'A round-robin result is newer than a fixed playoff bracket. Preview recovery before relying on playoff participants.');
        }
    }

    private function auditRecoveryRecords(array &$issues): void
    {
        if (! Schema::hasTable('draw_recovery_cases') || ! Schema::hasTable('draw_recovery_snapshots')) {
            return;
        }
        foreach (DrawRecoveryCase::where('status', 'applying')->where('created_at', '<', now()->subMinutes(5))->get() as $case) {
            $issues[] = $this->issue($case->draw_id, 'CRITICAL', $case->source_fixture_id,
                "Recovery case #{$case->id} has remained in applying state for more than five minutes.");
        }
        foreach (DrawRecoverySnapshot::cursor() as $snapshot) {
            $json = json_encode($snapshot->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (! hash_equals($snapshot->checksum, hash('sha256', $json))) {
                $issues[] = $this->issue($snapshot->draw_id, 'CRITICAL', null,
                    "Recovery snapshot #{$snapshot->id} failed checksum verification.");
            }
        }
    }

    private function issue(int $drawId, string $severity, ?int $fixtureId, string $message): array
    {
        return ['draw_id' => $drawId, 'severity' => $severity, 'fixture_id' => $fixtureId, 'issue' => $message];
    }
}
