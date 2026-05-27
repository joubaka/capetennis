<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a UNIQUE index on transactions_pf.pf_payment_id.
 *
 * Safety:
 *  - Idempotent: skips if index already exists.
 *  - NULL-safe: MySQL allows multiple NULL values in a UNIQUE index.
 *  - Will fail fast if duplicates still exist (run finance:dedupe-payfast-transactions first).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard: skip if index already present
        $indexes = DB::select(
            "SHOW INDEX FROM transactions_pf WHERE Key_name = 'transactions_pf_pf_payment_id_unique'"
        );

        if (!empty($indexes)) {
            return;
        }

        // Guard: refuse to add the constraint if live duplicates still exist
        $dupes = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM (
                SELECT pf_payment_id FROM transactions_pf
                WHERE pf_payment_id IS NOT NULL
                GROUP BY pf_payment_id HAVING COUNT(*) > 1
             ) AS dupe_check"
        );

        if ($dupes && $dupes->cnt > 0) {
            throw new \RuntimeException(
                "Cannot add UNIQUE index on transactions_pf.pf_payment_id: {$dupes->cnt} duplicate group(s) still exist. " .
                'Run: php artisan finance:dedupe-payfast-transactions --confirm'
            );
        }

        Schema::table('transactions_pf', function (Blueprint $table) {
            $table->unique('pf_payment_id', 'transactions_pf_pf_payment_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions_pf', function (Blueprint $table) {
            $table->dropUnique('transactions_pf_pf_payment_id_unique');
        });
    }
};
