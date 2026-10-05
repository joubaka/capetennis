<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('team_fixtures', 'gap_minutes')) {
            Schema::table('team_fixtures', function (Blueprint $table) {
                $table->unsignedSmallInteger('gap_minutes')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('team_fixtures', 'gap_minutes')) {
            Schema::table('team_fixtures', fn (Blueprint $table) => $table->dropColumn('gap_minutes'));
        }
    }
};
