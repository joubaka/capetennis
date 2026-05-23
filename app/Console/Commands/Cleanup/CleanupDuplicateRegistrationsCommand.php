<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * data:cleanup-duplicate-registrations
 *
 * Finds duplicate active (category_event_id, registration_id) pairs in
 * category_event_registrations and soft-deletes the older record.
 *
 * Safe rules:
 *  - Keep the newest row (highest id) that has a payfast_id or pf_transaction_id.
 *  - If neither row has payment evidence, keep the newest by id.
 *  - Export both rows before any mutation.
 *  - Only soft-deletes (sets deleted_at + status=withdrawn) — never hard-deletes.
 *
 * Usage:
 *   php artisan data:cleanup-duplicate-registrations --dry-run
 *   php artisan data:cleanup-duplicate-registrations --dry-run --export=storage/cleanup/dup_cer.csv
 *   php artisan data:cleanup-duplicate-registrations --confirm --export=storage/cleanup/dup_cer.csv
 */
class CleanupDuplicateRegistrationsCommand extends BaseCleanupCommand
{
    protected $signature = "data:cleanup-duplicate-registrations
                            {--dry-run  : Preview only — no changes made}
                            {--confirm  : Required to apply any changes}
                            {--limit=0  : Cap number of rows processed}
                            {--export=  : Write affected rows to this CSV path}";

    protected $description = "Soft-delete older duplicate active registration pairs.";

    /** @var array  keeps_id => discard_id resolution for the fix phase */
    private array $resolution = [];

    protected function scan(): iterable
    {
        // Find duplicate active pairs
        $pairs = DB::table("category_event_registrations")
            ->select("category_event_id", "registration_id")
            ->where("status", "active")
            ->whereNull("deleted_at")
            ->groupBy("category_event_id", "registration_id")
            ->havingRaw("COUNT(*) > 1")
            ->get();

        $rows = collect();

        foreach ($pairs as $pair) {
            $candidates = DB::table("category_event_registrations")
                ->where("category_event_id", $pair->category_event_id)
                ->where("registration_id",   $pair->registration_id)
                ->where("status", "active")
                ->whereNull("deleted_at")
                ->orderByRaw("(pf_transaction_id IS NOT NULL) DESC")
                ->orderBy("id", "desc")
                ->get();

            // First = keep, rest = discard
            $keep = $candidates->first();
            foreach ($candidates->slice(1) as $discard) {
                $discard->_keep_id   = $keep->id;
                $discard->_risk_note = $this->riskNote($keep, $discard);
                $rows->push($discard);
                $this->resolution[$discard->id] = $keep->id;
            }
        }

        return $rows;
    }

    protected function fix(object $row): void
    {
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
            "discard_id", "keep_id",
            "category_event_id", "registration_id",
            "discard_pf_transaction_id",
            "discard_created_at", "keep_created_at",
            "risk_note",
        ];
    }

    protected function rowToCsv(object $row): array
    {
        return [
            $row->id,
            $row->_keep_id ?? "",
            $row->category_event_id,
            $row->registration_id,
            $row->pf_transaction_id ?? "",
            $row->created_at        ?? "",
            "",  // keep_created_at not fetched here — see export for full detail
            $row->_risk_note ?? "",
        ];
    }

    public function handle(): int
    {
        return $this->runCleanup("data:cleanup-duplicate-registrations");
    }

    private function riskNote(object $keep, object $discard): string
    {
        if ($discard->pf_transaction_id) {
            return "WARNING: discard row has payment ref — verify before confirming";
        }
        if ($keep->pf_transaction_id) {
            return "OK: keep row has payment ref, discard row does not";
        }
        return "CAUTION: neither row has payment ref — review manually";
    }
}
