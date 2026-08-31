<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_events') || ! Schema::hasColumn('audit_events', 'id')) {
            return;
        }

        DB::statement(
            'ALTER TABLE `audit_events` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
        );
    }

    public function down(): void
    {
        // Removing AUTO_INCREMENT would make audit event inserts invalid again.
    }
};
