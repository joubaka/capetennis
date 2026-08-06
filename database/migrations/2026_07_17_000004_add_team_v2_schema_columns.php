<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds columns required by the Team Draw v2 feature that may be missing
 * in the test schema (where some tables were created with minimal columns).
 *
 * All additions are guarded with hasColumn checks so this migration is a
 * no-op on production databases where the columns already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ensure teams table has app-team columns (they may be missing in
        // the test schema where teams was created as a Jetstream stub).
        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'num_team_members')) {
                $table->integer('num_team_members')->nullable();
            }
            if (!Schema::hasColumn('teams', 'year')) {
                $table->string('year', 10)->nullable();
            }
            if (!Schema::hasColumn('teams', 'published')) {
                $table->boolean('published')->default(false);
            }
            if (!Schema::hasColumn('teams', 'region_id')) {
                $table->unsignedBigInteger('region_id')->nullable();
            }
            if (!Schema::hasColumn('teams', 'category_event_id')) {
                $table->unsignedBigInteger('category_event_id')->nullable();
            }
            if (!Schema::hasColumn('teams', 'noProfile')) {
                $table->boolean('noProfile')->default(false);
            }
        });

        // Ensure team_fixture_players has the full set of columns.
        // In tests, this table may have been created with only 'id' and
        // 'fixture_id' (the Jetstream test stub).
        Schema::table('team_fixture_players', function (Blueprint $table) {
            if (!Schema::hasColumn('team_fixture_players', 'team_fixture_id')) {
                $table->unsignedBigInteger('team_fixture_id')->nullable();
            }
            if (!Schema::hasColumn('team_fixture_players', 'team1_id')) {
                $table->unsignedBigInteger('team1_id')->nullable();
            }
            if (!Schema::hasColumn('team_fixture_players', 'team2_id')) {
                $table->unsignedBigInteger('team2_id')->nullable();
            }
        });

        // Ensure team_players has a rank column.
        Schema::table('team_players', function (Blueprint $table) {
            if (!Schema::hasColumn('team_players', 'rank')) {
                $table->integer('rank')->nullable();
            }
        });
    }

    public function down(): void
    {
        // These columns belong to the base application schema; do not drop.
    }
};
