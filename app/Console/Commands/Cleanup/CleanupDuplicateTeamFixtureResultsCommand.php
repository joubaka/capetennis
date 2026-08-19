<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Support\Facades\DB;

class CleanupDuplicateTeamFixtureResultsCommand extends BaseCleanupCommand
{
    protected $signature = "data:cleanup-duplicate-team-fixture-results
                            {--dry-run : Preview only - no changes made}
                            {--confirm : Required to apply any changes}
                            {--limit=0 : Cap number of rows processed}
                            {--export= : Write affected rows to this CSV path}";

    protected $description = 'Delete older duplicate team fixture result rows, keeping the latest set row.';

    protected function scan(): iterable
    {
        $nullSafe = DB::getDriverName() === 'mysql'
            ? 'result.set_nr <=> duplicate.set_nr'
            : '(result.set_nr IS duplicate.set_nr)';

        return collect(DB::select("SELECT result.id, result.team_fixture_id, result.set_nr, duplicate.keep_id
                FROM team_fixture_results result
                INNER JOIN (
                    SELECT team_fixture_id, set_nr, MAX(id) keep_id
                    FROM team_fixture_results
                    GROUP BY team_fixture_id, set_nr
                    HAVING COUNT(*) > 1
                ) duplicate ON result.team_fixture_id = duplicate.team_fixture_id AND {$nullSafe}
                WHERE result.id <> duplicate.keep_id
                ORDER BY result.team_fixture_id, result.set_nr, result.id"));
    }

    protected function fix(object $row): void
    {
        DB::table('team_fixture_results')->where('id', $row->id)->delete();
    }

    protected function headers(): array
    {
        return ['discard_id', 'keep_id', 'team_fixture_id', 'set_nr'];
    }

    protected function rowToCsv(object $row): array
    {
        return [$row->id, $row->keep_id, $row->team_fixture_id, $row->set_nr ?? 'NULL'];
    }

    public function handle(): int
    {
        return $this->runCleanup('data:cleanup-duplicate-team-fixture-results');
    }
}
