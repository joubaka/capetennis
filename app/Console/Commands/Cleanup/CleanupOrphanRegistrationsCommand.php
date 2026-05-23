<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * data:cleanup-orphan-registrations
 *
 * Finds category_event_registrations whose category_event_id no longer
 * exists in the category_events table.
 *
 * Safe rules:
 *  - Only soft-delete rows with no payment or refund dependency.
 *  - Rows with payfast_id, pf_transaction_id, or non-zero refund amounts
 *    are exported but SKIPPED (operator must resolve manually).
 *  - Export all rows before any mutation.
 *
 * Usage:
 *   php artisan data:cleanup-orphan-registrations --dry-run
 *   php artisan data:cleanup-orphan-registrations --dry-run --export=storage/cleanup/orphan_cer.csv
 *   php artisan data:cleanup-orphan-registrations --confirm --export=storage/cleanup/orphan_cer.csv
 */
class CleanupOrphanRegistrationsCommand extends BaseCleanupCommand
{
    protected $signature = "data:cleanup-orphan-registrations
                            {--dry-run  : Preview only — no changes made}
                            {--confirm  : Required to apply any changes}
                            {--limit=0  : Cap number of rows processed}
                            {--export=  : Write affected rows to this CSV path}";

    protected $description = "Soft-delete category_event_registrations whose category_event_id no longer exists.";

    protected function scan(): iterable
    {
        $existingCeIds = DB::table("category_events")->pluck("id");

        return DB::table("category_event_registrations")
            ->whereNotIn("category_event_id", $existingCeIds)
            ->whereNull("deleted_at")
            ->orderBy("id")
            ->get();
    }

    protected function fix(object $row): void
    {
        // Skip rows with financial dependency
        if ($this->hasPaymentDependency($row)) {
            $this->warn("  SKIP #{$row->id}: has payment dependency — manual review required.");
            return;
        }

        DB::table("category_event_registrations")
            ->where("id", $row->id)
            ->update([
                "status"     => "withdrawn",
                "deleted_at" => Carbon::now(),
                "updated_at" => Carbon::now(),
            ]);
    }

    protected function headers(): array
    {
        return [
            "id", "category_event_id", "registration_id", "status",
            "refund_status", "pf_transaction_id",
            "refund_gross", "created_at", "risk_note",
        ];
    }

    protected function rowToCsv(object $row): array
    {
        return [
            $row->id,
            $row->category_event_id,
            $row->registration_id,
            $row->status,
            $row->refund_status     ?? "",
            $row->pf_transaction_id ?? "",
            $row->refund_gross      ?? "",
            $row->created_at        ?? "",
            $this->hasPaymentDependency($row)
                ? "HAS PAYMENT — skip auto-fix, manual review needed"
                : "no payment dependency — safe to soft-delete",
        ];
    }

    public function handle(): int
    {
        return $this->runCleanup("data:cleanup-orphan-registrations");
    }

    private function hasPaymentDependency(object $row): bool
    {
        return !empty($row->pf_transaction_id)
            || (isset($row->refund_gross) && $row->refund_gross > 0);
    }
}
