<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('team_ties', 'format_snapshot')) {
            Schema::table('team_ties', fn (Blueprint $table) => $table->json('format_snapshot')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('team_ties', 'format_snapshot')) {
            if (DB::table('team_ties')->whereNotNull('format_snapshot')->exists()) {
                throw new RuntimeException('Recorded tie formats must be preserved; this migration cannot be rolled back after snapshots are captured.');
            }
            Schema::table('team_ties', fn (Blueprint $table) => $table->dropColumn('format_snapshot'));
        }
    }
};
