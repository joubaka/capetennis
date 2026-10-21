<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_ties', function (Blueprint $table) {
            $table->unsignedBigInteger('home_team_id')->nullable()->change();
            $table->unsignedBigInteger('away_team_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('team_ties')->whereNull('home_team_id')->orWhereNull('away_team_id')->exists()) {
            throw new RuntimeException('Resolve or remove draft playoff encounters before reverting nullable sides.');
        }
        Schema::table('team_ties', function (Blueprint $table) {
            $table->unsignedBigInteger('home_team_id')->nullable(false)->change();
            $table->unsignedBigInteger('away_team_id')->nullable(false)->change();
        });
    }
};
