<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_fixtures') && ! Schema::hasColumn('team_fixtures', 'match_status')) {
            Schema::table('team_fixtures', function (Blueprint $table): void {
                $table->integer('match_status')->default(0)->after('scheduled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('team_fixtures') && Schema::hasColumn('team_fixtures', 'match_status')) {
            Schema::table('team_fixtures', function (Blueprint $table): void {
                $table->dropColumn('match_status');
            });
        }
    }
};
