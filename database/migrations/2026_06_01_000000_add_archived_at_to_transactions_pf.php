<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds archived_at to transactions_pf for soft-archival of duplicate rows.
 * Idempotent: skips if column already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transactions_pf', 'archived_at')) {
            return;
        }

        Schema::table('transactions_pf', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_test')->index();
        });
    }

    public function down(): void
    {
        Schema::table('transactions_pf', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
