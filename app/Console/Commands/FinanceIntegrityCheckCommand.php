<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * finance:integrity-check
 *
 * Single command that runs all financial integrity checks:
 *  - negative wallet balances
 *  - duplicate pf_payment_id values
 *  - duplicate wallet_transactions
 *  - refund_status=pending with no withdrawal record
 *  - payfast_paid=1 but pf_payment_id is NULL
 *  - wallet_debited=0 despite pay_status=1
 *
 * Safe: read-only.
 */
class FinanceIntegrityCheckCommand extends Command
{
    protected $signature   = "finance:integrity-check {--json : Output as JSON}";
    protected $description = "Run all financial integrity checks in one pass.";

    public function handle(): int
    {
        $issues = [];

        // 1. Negative wallet balances
        $negBal = DB::select(
            "SELECT wallet_id, ROUND(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),2) as balance
             FROM wallet_transactions GROUP BY wallet_id HAVING balance < 0"
        );
        foreach ($negBal as $row) {
            $issues[] = ["area" => "wallets", "severity" => "CRITICAL",
                "issue" => "Wallet #{$row->wallet_id} has negative balance {$row->balance}"];
        }

        // 2. Duplicate pf_payment_id
        $dupPf = DB::select(
            "SELECT pf_payment_id, COUNT(*) as cnt FROM transactions_pf
             WHERE pf_payment_id IS NOT NULL GROUP BY pf_payment_id HAVING cnt > 1"
        );
        foreach ($dupPf as $row) {
            $issues[] = ["area" => "transactions_pf", "severity" => "CRITICAL",
                "issue" => "pf_payment_id={$row->pf_payment_id} has {$row->cnt} rows (duplicate gateway reference)"];
        }

        // 3. Duplicate wallet transactions
        $dupWt = DB::select(
            "SELECT wallet_id, source_type, source_id, COUNT(*) as cnt
             FROM wallet_transactions GROUP BY wallet_id, source_type, source_id HAVING cnt > 1"
        );
        foreach ($dupWt as $row) {
            $issues[] = ["area" => "wallet_transactions", "severity" => "CRITICAL",
                "issue" => "wallet_id={$row->wallet_id} source={$row->source_type}#{$row->source_id} has {$row->cnt} rows"];
        }

        // 4. Refund pending with no withdrawal
        $pendingNoW = DB::table("category_event_registrations as cer")
            ->where("cer.refund_status", "pending")
            ->whereNotIn("cer.registration_id", DB::table("withdrawals")->select("registration_id"))
            ->select("cer.id", "cer.registration_id")
            ->get();
        foreach ($pendingNoW as $row) {
            $issues[] = ["area" => "category_event_registrations", "severity" => "WARN",
                "issue" => "CER #{$row->id} reg#{$row->registration_id} refund_status=pending but no withdrawal record"];
        }

        // 5. PayFast paid but missing pf_payment_id on registration_orders
        $pfPaidNoId = DB::table("registration_orders")
            ->where("payfast_paid", 1)
            ->whereNull("payfast_pf_payment_id")
            ->where("payfast_amount_due", ">", 0)
            ->count();
        if ($pfPaidNoId > 0) {
            $issues[] = ["area" => "registration_orders", "severity" => "WARN",
                "issue" => "{$pfPaidNoId} orders marked payfast_paid=1 but payfast_pf_payment_id is NULL"];
        }

        // 6. pay_status=1 but wallet_debited=0 where wallet was expected
        $paidNotDebited = DB::table("registration_orders")
            ->where("pay_status", 1)
            ->where("wallet_debited", 0)
            ->where("wallet_reserved", ">", 0)
            ->count();
        if ($paidNotDebited > 0) {
            $issues[] = ["area" => "registration_orders", "severity" => "WARN",
                "issue" => "{$paidNotDebited} orders pay_status=1 but wallet_debited=0 with reserved wallet amount"];
        }

        if ($this->option("json")) {
            $this->line(json_encode($issues, JSON_PRETTY_PRINT));
        } elseif (empty($issues)) {
            $this->info("finance:integrity-check — no issues found.");
        } else {
            $this->table(
                ["area", "severity", "issue"],
                array_map(fn($i) => [$i["area"], $i["severity"], $i["issue"]], $issues)
            );
        }

        $critical = count(array_filter($issues, fn($i) => $i["severity"] === "CRITICAL"));
        return $critical > 0 ? self::FAILURE : self::SUCCESS;
    }
}