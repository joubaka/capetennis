<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * finance:dedupe-payfast-transactions
 *
 * Detects duplicate pf_payment_id rows in transactions_pf, keeps the oldest
 * valid row (lowest id), soft-archives duplicates via deleted_at, and exports
 * a CSV before any mutation.
 *
 * Options:
 *   --dry-run   : Report only, no mutations.
 *   --confirm   : Actually perform the cleanup (required outside dry-run).
 */
class FinanceDedupePayfastTransactionsCommand extends Command
{
    protected $signature = 'finance:dedupe-payfast-transactions
                            {--dry-run : Report duplicates without making changes}
                            {--confirm : Confirm you want to perform the cleanup}';

    protected $description = 'Detect and archive duplicate pf_payment_id rows in transactions_pf. Keeps the oldest row per payment ID.';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isConfirm = $this->option('confirm');

        if (!$isDryRun && !$isConfirm) {
            $this->error('You must pass --dry-run or --confirm.');
            return 1;
        }

        // ── Find duplicate groups ─────────────────────────────────────────
        $duplicates = DB::select(
            "SELECT pf_payment_id, COUNT(*) as cnt, MIN(id) as keep_id
             FROM transactions_pf
             WHERE pf_payment_id IS NOT NULL
               AND archived_at IS NULL
             GROUP BY pf_payment_id
             HAVING cnt > 1"
        );

        if (empty($duplicates)) {
            $this->info('✅ No duplicate pf_payment_id values found. Table is clean.');
            return 0;
        }

        $this->warn(sprintf('⚠  Found %d duplicate pf_payment_id group(s):', count($duplicates)));

        $allDupeIds = [];

        foreach ($duplicates as $row) {
            $dupeIds = DB::table('transactions_pf')
                ->where('pf_payment_id', $row->pf_payment_id)
                ->whereNull('archived_at')
                ->where('id', '!=', $row->keep_id)
                ->pluck('id')
                ->toArray();

            $allDupeIds = array_merge($allDupeIds, $dupeIds);

            $this->line(sprintf(
                '  pf_payment_id=%s  total=%d  keep_id=%d  archive_ids=[%s]',
                $row->pf_payment_id,
                $row->cnt,
                $row->keep_id,
                implode(',', $dupeIds)
            ));
        }

        // ── Export CSV before mutation ────────────────────────────────────
        if (!empty($allDupeIds)) {
            $this->exportCsv($allDupeIds);
        }

        if ($isDryRun) {
            $this->info('Dry-run complete. No rows were modified.');
            return 0;
        }

        // ── Archive duplicates: stamp archived_at and NULL out pf_payment_id ──
        // NULLing pf_payment_id lets the UNIQUE index work cleanly post-cleanup.
        DB::table('transactions_pf')
            ->whereIn('id', $allDupeIds)
            ->update([
                'archived_at'   => now(),
                'pf_payment_id' => null,
            ]);

        $this->info(sprintf('✅ Archived %d duplicate row(s) (archived_at stamped, pf_payment_id nulled).', count($allDupeIds)));

        return 0;
    }

    private function exportCsv(array $ids): void
    {
        $rows = DB::table('transactions_pf')
            ->whereIn('id', $ids)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $filename = 'finance/dedupe_payfast_' . now()->format('Ymd_His') . '.csv';
        $headers  = array_keys((array) $rows->first());
        $lines    = [implode(',', $headers)];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                fn($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                (array) $row
            ));
        }

        Storage::put($filename, implode("\n", $lines));
        $this->info("CSV exported to storage://{$filename}");
    }
}
