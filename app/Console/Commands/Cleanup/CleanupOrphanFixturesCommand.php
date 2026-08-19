<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Support\Facades\DB;

/**
 * data:cleanup-orphan-fixtures
 *
 * Hard-deletes fixtures whose draw_id references a draw that no longer
 * exists.  Also hard-deletes any dangling fixture_results for those
 * fixtures.
 *
 * Safe rules:
 *  - Export fixture IDs and any linked fixture_results before deletion.
 *  - Only deletes rows where the parent draw is genuinely missing.
 *  - Run last in the cleanup sequence (after financial and registration
 *    cleanup is complete).
 *
 * Usage:
 *   php artisan data:cleanup-orphan-fixtures --dry-run
 *   php artisan data:cleanup-orphan-fixtures --dry-run --export=storage/cleanup/orphan_fixtures.csv
 *   php artisan data:cleanup-orphan-fixtures --confirm --export=storage/cleanup/orphan_fixtures.csv
 */
class CleanupOrphanFixturesCommand extends BaseCleanupCommand
{
    protected $signature = "data:cleanup-orphan-fixtures
                            {--dry-run  : Preview only — no changes made}
                            {--confirm  : Required to apply any changes}
                            {--limit=0  : Cap number of rows processed}
                            {--export=  : Write affected rows to this CSV path}";

    protected $description = "Delete fixtures whose draw_id no longer exists, plus their fixture_results.";

    protected function scan(): iterable
    {
        return DB::table("fixtures as f")
            ->leftJoin("draws as d", "f.draw_id", "=", "d.id")
            ->whereNull("d.id")
            ->select("f.id", "f.draw_id", "f.match_status", "f.round",
                     "f.stage", "f.created_at")
            ->orderBy("f.draw_id")
            ->orderBy("f.id")
            ->get();
    }

    protected function fix(object $row): void
    {
        // Delete linked fixture_results first (FK safety)
        DB::table("order_of_plays")->where("fixture_id", $row->id)->delete();
        DB::table("fixture_results")->where("fixture_id", $row->id)->delete();
        DB::table("fixtures")->where("id", $row->id)->delete();
    }

    protected function headers(): array
    {
        return ["fixture_id", "draw_id", "match_status", "round",
                "stage", "created_at", "risk_note"];
    }

    protected function rowToCsv(object $row): array
    {
        return [
            $row->id,
            $row->draw_id,
            $row->match_status ?? "NULL",
            $row->round        ?? "NULL",
            $row->stage        ?? "NULL",
            $row->created_at   ?? "",
            "orphan — draw #{$row->draw_id} no longer exists",
        ];
    }

    public function handle(): int
    {
        return $this->runCleanup("data:cleanup-orphan-fixtures");
    }
}
