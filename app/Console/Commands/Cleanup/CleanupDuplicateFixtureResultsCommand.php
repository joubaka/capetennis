<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Support\Facades\DB;

/**
 * data:cleanup-duplicate-fixture-results
 *
 * Keeps the latest valid result row per (fixture_id, set_nr) pair and
 * hard-deletes exact duplicates / older superseded rows.
 *
 * Safe rules:
 *  - "Keep" row = highest id within each (fixture_id, set_nr) group.
 *  - "Discard" rows = all lower ids in the same group.
 *  - Export discard ids before any deletion.
 *  - Hard-delete is acceptable because fixture_results rows are derived
 *    score data — source of truth is the fixture match_status.
 *
 * Usage:
 *   php artisan data:cleanup-duplicate-fixture-results --dry-run
 *   php artisan data:cleanup-duplicate-fixture-results --dry-run --export=storage/cleanup/dup_results.csv
 *   php artisan data:cleanup-duplicate-fixture-results --confirm --export=storage/cleanup/dup_results.csv
 */
class CleanupDuplicateFixtureResultsCommand extends BaseCleanupCommand
{
    protected $signature = "data:cleanup-duplicate-fixture-results
                            {--dry-run  : Preview only — no changes made}
                            {--confirm  : Required to apply any changes}
                            {--limit=0  : Cap number of rows processed}
                            {--export=  : Write affected rows to this CSV path}";

    protected $description = "Delete older duplicate fixture_results rows, keeping the latest per (fixture_id, set_nr).";

    protected function scan(): iterable
    {
        // All rows that are NOT the max-id in their (fixture_id, set_nr) group.
        // MySQL uses NULL-safe equals (<=>); SQLite uses IS for the same semantics.
        $isMysql   = DB::getDriverName() === "mysql";
        $nullSafe  = $isMysql ? "fr.set_nr <=> dups.set_nr"
                              : "(fr.set_nr IS dups.set_nr)";

        $sql = "SELECT fr.id, fr.fixture_id, fr.set_nr,
                       fr.winner_registration, fr.loser_registration,
                       fr.registration1_score, fr.registration2_score,
                       fr.created_at, dups.max_id as keep_id
                FROM fixture_results fr
                INNER JOIN (
                    SELECT fixture_id, set_nr, MAX(id) as max_id
                    FROM fixture_results
                    GROUP BY fixture_id, set_nr
                    HAVING COUNT(*) > 1
                ) dups ON fr.fixture_id = dups.fixture_id
                       AND {$nullSafe}
                WHERE fr.id <> dups.max_id
                ORDER BY fr.fixture_id, fr.set_nr, fr.id";

        return collect(DB::select($sql));
    }

    protected function fix(object $row): void
    {
        DB::table("fixture_results")->where("id", $row->id)->delete();
    }

    protected function headers(): array
    {
        return [
            "discard_id", "keep_id", "fixture_id", "set_nr",
            "winner_registration", "loser_registration",
            "registration1_score", "registration2_score",
            "created_at",
        ];
    }

    protected function rowToCsv(object $row): array
    {
        return [
            $row->id,
            $row->keep_id,
            $row->fixture_id,
            $row->set_nr           ?? "NULL",
            $row->winner_registration  ?? "",
            $row->loser_registration   ?? "",
            $row->registration1_score  ?? "",
            $row->registration2_score  ?? "",
            $row->created_at           ?? "",
        ];
    }

    public function handle(): int
    {
        return $this->runCleanup("data:cleanup-duplicate-fixture-results");
    }
}
