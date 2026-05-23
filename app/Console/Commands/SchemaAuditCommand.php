<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * schema:audit
 *
 * Reports structural issues: missing indexes, nullable columns that should
 * not be nullable, and known schema drift between migrations and production.
 */
class SchemaAuditCommand extends Command
{
    protected $signature   = "schema:audit {--table= : Limit to a specific table}";
    protected $description = "Audit database schema for drift, missing indexes, and integrity gaps.";

    private array $issues = [];

    public function handle(): int
    {
        $table = $this->option("table");

        $this->auditDraws();
        $this->auditFixtures();
        $this->auditRegistrations();
        $this->auditFinance();

        $filtered = $table
            ? array_filter($this->issues, fn($i) => $i["table"] === $table)
            : $this->issues;

        if (empty($filtered)) {
            $this->info("schema:audit — no issues found.");
            return self::SUCCESS;
        }

        $this->table(
            ["table", "severity", "issue"],
            array_map(fn($i) => [$i["table"], $i["severity"], $i["issue"]], array_values($filtered))
        );

        $critical = count(array_filter($filtered, fn($i) => $i["severity"] === "CRITICAL"));
        $warn     = count(array_filter($filtered, fn($i) => $i["severity"] === "WARN"));

        $this->newLine();
        $this->line("  <fg=red>CRITICAL: {$critical}</> | <fg=yellow>WARN: {$warn}</>");

        return $critical > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function add(string $table, string $severity, string $issue): void
    {
        $this->issues[] = compact("table", "severity", "issue");
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (\Throwable) {
            // SQLite or other drivers that don't support SHOW INDEX
            return false;
        }
    }

    private function auditDraws(): void
    {
        $nullPublished = DB::table("draws")->whereNull("published")->count();
        $nullLocked    = DB::table("draws")->whereNull("locked")->count();
        if ($nullPublished > 0) {
            $this->add("draws", "CRITICAL", "{$nullPublished} rows with NULL published (should be 0/1)");
        }
        if ($nullLocked > 0) {
            $this->add("draws", "CRITICAL", "{$nullLocked} rows with NULL locked (should be 0/1)");
        }
        if (!$this->hasIndex("draws", "draws_event_id_index")) {
            $this->add("draws", "WARN", "Missing index on event_id");
        }
        if (!$this->hasIndex("draws", "draws_category_event_id_index")) {
            $this->add("draws", "WARN", "Missing index on category_event_id");
        }

        // drawType_id stored as TEXT instead of INT
        try {
            $col = DB::selectOne("SELECT column_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='draws' AND column_name='drawType_id'");
            if ($col && str_starts_with($col->column_type, "text")) {
                $this->add("draws", "WARN", "drawType_id column is TEXT — should be INT after data cleanup");
            }
        } catch (\Throwable) {}
    }

    private function auditFixtures(): void
    {
        $nullStatus = DB::table("fixtures")->whereNull("match_status")->count();
        $nullStage  = DB::table("fixtures")->whereNull("stage")->count();
        $orphans    = DB::table("fixtures as f")
            ->leftJoin("draws as d", "f.draw_id", "=", "d.id")
            ->whereNull("d.id")
            ->count();

        if ($nullStatus > 0) {
            $this->add("fixtures", "CRITICAL", "{$nullStatus} rows with NULL match_status");
        }
        if ($nullStage > 0) {
            $this->add("fixtures", "WARN", "{$nullStage} rows with NULL stage (pre-stage era records)");
        }
        if ($orphans > 0) {
            $this->add("fixtures", "CRITICAL", "{$orphans} fixtures whose draw_id references no draw");
        }

        if (!$this->hasIndex("fixtures", "fixtures_draw_id_index")) {
            $this->add("fixtures", "WARN", "Missing index on draw_id");
        }
        if (!$this->hasIndex("fixtures", "fixtures_parent_fixture_id_index")) {
            $this->add("fixtures", "WARN", "Missing index on parent_fixture_id");
        }
        if (!$this->hasIndex("fixtures", "fixtures_draw_stage_index")) {
            $this->add("fixtures", "WARN", "Missing composite index on (draw_id, stage)");
        }

        $dupResults = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM (SELECT fixture_id, set_nr, COUNT(*) c FROM fixture_results GROUP BY fixture_id, set_nr HAVING c > 1) x"
        );
        if ($dupResults && $dupResults->cnt > 0) {
            $this->add("fixture_results", "CRITICAL", "{$dupResults->cnt} duplicate (fixture_id, set_nr) pairs — idempotency risk");
        }

        $winnerNoResult = DB::table("fixtures as f")
            ->whereNotNull("f.winner_registration")
            ->where("f.winner_registration", ">", 0)
            ->whereNotIn("f.id", DB::table("fixture_results")->select("fixture_id")->distinct())
            ->count();
        if ($winnerNoResult > 0) {
            $this->add("fixtures", "WARN", "{$winnerNoResult} fixtures have winner_registration but no fixture_results rows");
        }
    }

    private function auditRegistrations(): void
    {
        $dupActive = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM (SELECT category_event_id, registration_id, COUNT(*) c FROM category_event_registrations WHERE deleted_at IS NULL GROUP BY 1,2 HAVING c > 1) x"
        );
        if ($dupActive && $dupActive->cnt > 0) {
            $this->add("category_event_registrations", "CRITICAL", "{$dupActive->cnt} duplicate active (category_event_id, registration_id) pairs");
        }

        $withdrawnNotDeleted = DB::table("category_event_registrations")
            ->where("status", "withdrawn")
            ->whereNull("deleted_at")
            ->count();
        if ($withdrawnNotDeleted > 0) {
            $this->add("category_event_registrations", "WARN", "{$withdrawnNotDeleted} withdrawn rows without deleted_at (soft-delete inconsistency)");
        }

        $pendingNoWithdrawal = DB::table("category_event_registrations as cer")
            ->where("cer.refund_status", "pending")
            ->whereNotIn("cer.registration_id", DB::table("withdrawals")->select("registration_id"))
            ->count();
        if ($pendingNoWithdrawal > 0) {
            $this->add("category_event_registrations", "WARN", "{$pendingNoWithdrawal} refund_status=pending with no matching withdrawal record");
        }

        $orphanCer = DB::table("category_event_registrations as cer")
            ->leftJoin("category_events as ce", "cer.category_event_id", "=", "ce.id")
            ->whereNull("ce.id")
            ->count();
        if ($orphanCer > 0) {
            $this->add("category_event_registrations", "CRITICAL", "{$orphanCer} rows reference non-existent category_event_id");
        }

        if (!$this->hasIndex("category_event_registrations", "cer_category_event_registration_index")) {
            $this->add("category_event_registrations", "WARN", "Missing composite index on (category_event_id, registration_id)");
        }
    }

    private function auditFinance(): void
    {
        $dupPf = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM (SELECT pf_payment_id, COUNT(*) c FROM transactions_pf WHERE pf_payment_id IS NOT NULL GROUP BY 1 HAVING c > 1) x"
        );
        if ($dupPf && $dupPf->cnt > 0) {
            $this->add("transactions_pf", "CRITICAL", "{$dupPf->cnt} duplicate pf_payment_id values (no unique constraint)");
        }

        $negBal = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM (SELECT wallet_id, SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as bal FROM wallet_transactions GROUP BY wallet_id HAVING bal < 0) x"
        );
        if ($negBal && $negBal->cnt > 0) {
            $this->add("wallets", "CRITICAL", "{$negBal->cnt} wallets have a negative computed balance");
        }

        // Detect duplicate wallet unique indexes
        $dupIdx = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='wallet_transactions' AND index_name IN ('wallet_tx_unique','wallet_txn_unique_source')"
        );
        if ($dupIdx && $dupIdx->cnt === 6) {
            $this->add("wallet_transactions", "WARN", "Two identical unique constraints on (wallet_id, source_type, source_id) — wallet_tx_unique is a duplicate of wallet_txn_unique_source");
        }
    }
}
