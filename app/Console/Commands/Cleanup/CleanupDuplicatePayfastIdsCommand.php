<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Support\Facades\DB;

/**
 * data:cleanup-duplicate-payfast-ids
 *
 * SAFE RULES:
 *  - Never auto-deletes payment records.
 *  - Export-only: marks suspected duplicates with a flag column comment.
 *  - No mutation even with --confirm; operator must resolve manually.
 *
 * Usage:
 *   php artisan data:cleanup-duplicate-payfast-ids --dry-run
 *   php artisan data:cleanup-duplicate-payfast-ids --export=storage/cleanup/dup_payfast.csv
 */
class CleanupDuplicatePayfastIdsCommand extends BaseCleanupCommand
{
    protected $signature = "data:cleanup-duplicate-payfast-ids
                            {--dry-run  : Preview only — no changes made}
                            {--confirm  : Required to apply any changes}
                            {--limit=0  : Cap number of rows processed}
                            {--export=  : Write affected rows to this CSV path}";

    protected $description = "Export duplicate pf_payment_id rows for manual review (never auto-deletes).";

    protected function scan(): iterable
    {
        // Find all rows that share a pf_payment_id with at least one other row.
        $dupIds = DB::table("transactions_pf")
            ->select("pf_payment_id")
            ->whereNotNull("pf_payment_id")
            ->groupBy("pf_payment_id")
            ->havingRaw("COUNT(*) > 1")
            ->pluck("pf_payment_id");

        return DB::table("transactions_pf")
            ->whereIn("pf_payment_id", $dupIds)
            ->orderBy("pf_payment_id")
            ->orderBy("id")
            ->get();
    }

    protected function fix(object $row): void
    {
        // INTENTIONALLY NO-OP.
        // Duplicate PayFast IDs are financial records that must never be
        // auto-deleted. This command is export-only.
        // Operator must resolve each group manually after reviewing the export.
    }

    protected function headers(): array
    {
        return ["id", "pf_payment_id", "player_id", "event_id", "amount",
                "created_at", "risk_note"];
    }

    protected function rowToCsv(object $row): array
    {
        return [
            $row->id,
            $row->pf_payment_id,
            $row->player_id ?? "",
            $row->event_id  ?? "",
            $row->amount    ?? "",
            $row->created_at ?? "",
            "DUPLICATE — manual review required",
        ];
    }

    public function handle(): int
    {
        $this->warn("⚠  PayFast duplicate records are NEVER auto-deleted.");
        $this->warn("   This command exports them for manual operator review only.");

        $result = $this->runCleanup("data:cleanup-duplicate-payfast-ids");

        $count = collect($this->scan())->count();
        if ($count > 0) {
            $this->line("");
            $this->error("ACTION REQUIRED: {$count} rows share a pf_payment_id with another row.");
            $this->line("Steps:");
            $this->line("  1. Review the export CSV.");
            $this->line("  2. Identify which row is the true payment vs retry/duplicate.");
            $this->line("  3. Manually reconcile — do NOT delete without finance team sign-off.");
        }

        return $result;
    }
}
