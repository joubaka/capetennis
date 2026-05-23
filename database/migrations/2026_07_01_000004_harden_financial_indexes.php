<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Financial schema hardening.
 *
 * Problems:
 *  - transactions_pf.pf_payment_id has no unique constraint; 4 duplicate
 *    pf_payment_id values found in production (webhook retries). We cannot
 *    add UNIQUE now without resolving dupes — add an index and a comment.
 *  - transactions_pf has no index on event_id or player_id despite heavy
 *    filtering on both.
 *  - registration_orders has no index on pay_status or payfast_paid.
 *  - wallet_transactions has TWO identical unique constraints
 *    (wallet_tx_unique and wallet_txn_unique_source both on wallet_id+source_type+source_id).
 *    Drop the older duplicate alias.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop duplicate wallet unique index (keep wallet_txn_unique_source).
        // Safe try/catch: index may not exist in test (SQLite) environments.
        try {
            Schema::table("wallet_transactions", function (Blueprint $table) {
                $table->dropIndex("wallet_tx_unique");
            });
        } catch (\Throwable $e) {
            // Index does not exist — nothing to drop
        }

        // transactions_pf indexes
        Schema::table("transactions_pf", function (Blueprint $table) {
            // Non-unique because of production dupes — intent is still pf_payment_id uniqueness
            $table->index("pf_payment_id", "transactions_pf_pf_payment_id_index");
            $table->index("event_id",      "transactions_pf_event_id_index");
            $table->index("player_id",     "transactions_pf_player_id_index");
        });

        // registration_orders indexes
        Schema::table("registration_orders", function (Blueprint $table) {
            $table->index("pay_status",    "reg_orders_pay_status_index");
            $table->index("payfast_paid",  "reg_orders_payfast_paid_index");
        });
    }

    public function down(): void
    {
        Schema::table("registration_orders", function (Blueprint $table) {
            $table->dropIndex("reg_orders_pay_status_index");
            $table->dropIndex("reg_orders_payfast_paid_index");
        });

        Schema::table("transactions_pf", function (Blueprint $table) {
            $table->dropIndex("transactions_pf_pf_payment_id_index");
            $table->dropIndex("transactions_pf_event_id_index");
            $table->dropIndex("transactions_pf_player_id_index");
        });

        // Restore duplicate (harmless but expected in down)
        Schema::table("wallet_transactions", function (Blueprint $table) {
            $table->unique(["wallet_id", "source_type", "source_id"], "wallet_tx_unique");
        });
    }
};
