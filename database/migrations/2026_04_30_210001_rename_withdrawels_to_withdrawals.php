<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the legacy misspelled `withdrawels` table to `withdrawals`.
 * All existing foreign key references and the Withdrawals model are updated
 * to match the corrected name.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only rename if the misspelled table still exists
        if (Schema::hasTable('withdrawels') && !Schema::hasTable('withdrawals')) {
            Schema::rename('withdrawels', 'withdrawals');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('withdrawals') && !Schema::hasTable('withdrawels')) {
            Schema::rename('withdrawals', 'withdrawels');
        }
    }
};
