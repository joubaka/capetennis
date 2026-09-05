<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(config('permission.table_names.roles', 'roles'))) {
            return;
        }

        DB::table(config('permission.table_names.roles', 'roles'))->updateOrInsert(
            ['name' => 'score-keeper', 'guard_name' => 'web'],
            ['updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        // Keep the role on rollback so an assigned scoring account is never orphaned.
    }
};
