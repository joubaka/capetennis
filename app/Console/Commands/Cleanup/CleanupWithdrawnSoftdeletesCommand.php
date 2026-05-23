<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * data:cleanup-withdrawn-softdeletes
 *
 * Sets deleted_at on category_event_registrations where status='withdrawn'
 * but deleted_at is NULL, correcting the soft-delete inconsistency.
 *
 * Safe rules:
 *  - Only updates rows where status is clearly 'withdrawn'.
 *  - Sets deleted_at = withdrawn_at if available, otherwise = updated_at,
 *    otherwise = now().
 *  - Does NOT change any other column.
 *
 * Usage:
 *   php artisan data:cleanup-withdrawn-softdeletes --dry-run
 *   php artisan data:cleanup-withdrawn-softdeletes --dry-run --export=storage/cleanup/withdrawn_softdeletes.csv
 *   php artisan data:cleanup-withdrawn-softdeletes --confirm
 */
class CleanupWithdrawnSoftdeletesCommand extends BaseCleanupCommand
{
    protected $signature = "data:cleanup-withdrawn-softdeletes
                            {--dry-run  : Preview only — no changes made}
                            {--confirm  : Required to apply any changes}
                            {--limit=0  : Cap number of rows processed}
                            {--export=  : Write affected rows to this CSV path}";

    protected $description = "Backfill deleted_at for withdrawn CER rows that are missing it.";

    protected function scan(): iterable
    {
        return DB::table("category_event_registrations")
            ->where("status", "withdrawn")
            ->whereNull("deleted_at")
            ->orderBy("id")
            ->get();
    }

    protected function fix(object $row): void
    {
        // Use the most accurate timestamp available
        $timestamp = $row->withdrawn_at
            ?? $row->updated_at
            ?? Carbon::now()->toDateTimeString();

        DB::table("category_event_registrations")
            ->where("id", $row->id)
            ->update([
                "deleted_at" => $timestamp,
                "updated_at" => Carbon::now(),
            ]);
    }

    protected function headers(): array
    {
        return [
            "id", "category_event_id", "registration_id",
            "status", "withdrawn_at", "updated_at",
            "resolved_deleted_at",
        ];
    }

    protected function rowToCsv(object $row): array
    {
        $resolved = $row->withdrawn_at ?? $row->updated_at ?? "NOW()";
        return [
            $row->id,
            $row->category_event_id,
            $row->registration_id,
            $row->status,
            $row->withdrawn_at ?? "",
            $row->updated_at   ?? "",
            $resolved,
        ];
    }

    public function handle(): int
    {
        return $this->runCleanup("data:cleanup-withdrawn-softdeletes");
    }
}
