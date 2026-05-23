<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Support\Facades\DB;

/**
 * data:cleanup-refund-without-withdrawal
 *
 * Finds category_event_registrations with refund_status='pending'
 * but no matching record in the withdrawals table.
 *
 * Safe rules:
 *  - Export-only for rows where the CER status is still 'active'
 *    (no safe auto-fix — likely a workflow bug needing manual review).
 *  - For rows where status='withdrawn', no auto-fix is applied either
 *    unless the operator explicitly confirms after reviewing the export.
 *  - Never deletes or modifies refund_status automatically.
 *
 * Usage:
 *   php artisan data:cleanup-refund-without-withdrawal --dry-run
 *   php artisan data:cleanup-refund-without-withdrawal --dry-run --export=storage/cleanup/refund_no_withdrawal.csv
 *   php artisan data:cleanup-refund-without-withdrawal --confirm
 */
class CleanupRefundWithoutWithdrawalCommand extends BaseCleanupCommand
{
    protected $signature = "data:cleanup-refund-without-withdrawal
                            {--dry-run  : Preview only — no changes made}
                            {--confirm  : Required to apply any changes}
                            {--limit=0  : Cap number of rows processed}
                            {--export=  : Write affected rows to this CSV path}";

    protected $description = "Export CER rows with pending refunds but no withdrawal record.";

    protected function scan(): iterable
    {
        $withWithdrawal = DB::table("withdrawals")
            ->whereNotNull("registration_id")
            ->pluck("registration_id");

        return DB::table("category_event_registrations")
            ->where("refund_status", "pending")
            ->whereNotIn("registration_id", $withWithdrawal)
            ->orderBy("id")
            ->get();
    }

    protected function fix(object $row): void
    {
        // Intentionally minimal — only log, do not mutate refund_status.
        // A pending refund without a withdrawal record is a workflow gap
        // that needs human sign-off before any state change.
        $this->warn("  MANUAL REVIEW required for CER #{$row->id} "
            . "(registration_id={$row->registration_id}, status={$row->status})");
    }

    protected function headers(): array
    {
        return [
            "id", "category_event_id", "registration_id", "status",
            "refund_status", "refund_gross", "refund_method",
            "pf_transaction_id", "created_at", "risk_note",
        ];
    }

    protected function rowToCsv(object $row): array
    {
        $note = $row->status === "withdrawn"
            ? "withdrawn but no withdrawal record — review payment/refund chain"
            : "ACTIVE with pending refund and no withdrawal — likely workflow bug";

        return [
            $row->id,
            $row->category_event_id,
            $row->registration_id,
            $row->status,
            $row->refund_status     ?? "",
            $row->refund_gross      ?? "",
            $row->refund_method     ?? "",
            $row->pf_transaction_id ?? "",
            $row->created_at        ?? "",
            $note,
        ];
    }

    public function handle(): int
    {
        $this->warn("⚠  This command exports rows for manual review only.");
        $this->warn("   Refund_status is never automatically modified.");
        return $this->runCleanup("data:cleanup-refund-without-withdrawal");
    }
}
