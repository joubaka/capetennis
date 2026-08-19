<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Read-only structural audit for the team side of the draw system. */
class TeamDrawIntegrityCheckCommand extends Command
{
    protected $signature = 'team-draw:integrity-check {--json : Output machine-readable JSON}';

    protected $description = 'Check team draw fixtures, ties, player assignments, and score rows for orphan or duplicate data.';

    public function handle(): int
    {
        $checks = [
            'orphan_team_fixtures' => DB::table('team_fixtures as fixture')
                ->leftJoin('draws as draw', 'draw.id', '=', 'fixture.draw_id')
                ->whereNull('draw.id')->count(),
            'orphan_team_fixture_players' => DB::table('team_fixture_players as player')
                ->leftJoin('team_fixtures as fixture', 'fixture.id', '=', 'player.team_fixture_id')
                ->whereNull('fixture.id')->count(),
            'orphan_team_fixture_results' => DB::table('team_fixture_results as result')
                ->leftJoin('team_fixtures as fixture', 'fixture.id', '=', 'result.team_fixture_id')
                ->whereNull('fixture.id')->count(),
            'orphan_team_ties' => DB::table('team_ties as tie')
                ->leftJoin('draws as draw', 'draw.id', '=', 'tie.draw_id')
                ->whereNull('draw.id')->count(),
            'fixtures_with_missing_tie' => DB::table('team_fixtures as fixture')
                ->leftJoin('team_ties as tie', 'tie.id', '=', 'fixture.team_tie_id')
                ->whereNotNull('fixture.team_tie_id')->whereNull('tie.id')->count(),
            'duplicate_team_result_sets' => (int) DB::table('team_fixture_results')
                ->select('team_fixture_id', 'set_nr')
                ->groupBy('team_fixture_id', 'set_nr')
                ->havingRaw('COUNT(*) > 1')
                ->get()->count(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_PRETTY_PRINT));
        } else {
            $this->table(['Check', 'Count'], collect($checks)
                ->map(fn($count, $check) => [$check, $count])->values()->all());
        }

        return array_sum($checks) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
