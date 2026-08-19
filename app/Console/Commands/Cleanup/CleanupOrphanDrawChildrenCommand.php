<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Support\Facades\DB;

/** Removes draw child rows only when their required parent no longer exists. */
class CleanupOrphanDrawChildrenCommand extends BaseCleanupCommand
{
    protected $signature = "data:cleanup-orphan-draw-children
                            {--dry-run : Preview only - no changes made}
                            {--confirm : Required to apply any changes}
                            {--limit=0 : Cap number of rows processed}
                            {--export= : Write affected rows to this CSV path}";

    protected $description = 'Delete orphan team fixtures, fixture results, and team fixture player rows in dependency-safe order.';

    protected function scan(): iterable
    {
        $rows = collect();

        DB::table('team_fixtures as fixture')
            ->leftJoin('draws as draw', 'draw.id', '=', 'fixture.draw_id')
            ->whereNull('draw.id')->select('fixture.id', 'fixture.draw_id')->orderBy('fixture.id')->get()
            ->each(fn($row) => $rows->push((object) [
                'kind' => 'team_fixture', 'id' => $row->id,
                'parent_id' => $row->draw_id, 'parent_table' => 'draws',
            ]));

        DB::table('fixture_results as result')
            ->leftJoin('fixtures as fixture', 'fixture.id', '=', 'result.fixture_id')
            ->whereNull('fixture.id')->select('result.id', 'result.fixture_id')->orderBy('result.id')->get()
            ->each(fn($row) => $rows->push((object) [
                'kind' => 'fixture_result', 'id' => $row->id,
                'parent_id' => $row->fixture_id, 'parent_table' => 'fixtures',
            ]));

        DB::table('team_fixture_players as player')
            ->leftJoin('team_fixtures as fixture', 'fixture.id', '=', 'player.team_fixture_id')
            ->whereNull('fixture.id')->select('player.id', 'player.team_fixture_id')->orderBy('player.id')->get()
            ->each(fn($row) => $rows->push((object) [
                'kind' => 'team_fixture_player', 'id' => $row->id,
                'parent_id' => $row->team_fixture_id, 'parent_table' => 'team_fixtures',
            ]));

        return $rows;
    }

    protected function fix(object $row): void
    {
        DB::transaction(function () use ($row) {
            if ($row->kind === 'team_fixture') {
                DB::table('team_fixture_results')->where('team_fixture_id', $row->id)->delete();
                DB::table('team_fixture_players')->where('team_fixture_id', $row->id)->delete();
                DB::table('team_fixtures')->where('id', $row->id)
                    ->whereNotExists(fn($query) => $query->selectRaw('1')->from('draws')->whereColumn('draws.id', 'team_fixtures.draw_id'))
                    ->delete();
                return;
            }

            if ($row->kind === 'fixture_result') {
                DB::table('fixture_results')->where('id', $row->id)
                    ->whereNotExists(fn($query) => $query->selectRaw('1')->from('fixtures')->whereColumn('fixtures.id', 'fixture_results.fixture_id'))
                    ->delete();
                return;
            }

            DB::table('team_fixture_players')->where('id', $row->id)
                ->whereNotExists(fn($query) => $query->selectRaw('1')->from('team_fixtures')->whereColumn('team_fixtures.id', 'team_fixture_players.team_fixture_id'))
                ->delete();
        });
    }

    protected function headers(): array
    {
        return ['kind', 'row_id', 'missing_parent_table', 'missing_parent_id', 'risk_note'];
    }

    protected function rowToCsv(object $row): array
    {
        return [$row->kind, $row->id, $row->parent_table, $row->parent_id, 'orphan child row'];
    }

    public function handle(): int
    {
        return $this->runCleanup('data:cleanup-orphan-draw-children');
    }
}
