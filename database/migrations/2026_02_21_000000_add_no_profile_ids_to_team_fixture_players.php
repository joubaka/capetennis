<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    // Changing existing columns to nullable uses change(); doctrine/dbal is required.
    Schema::table('team_fixture_players', function (Blueprint $table) {

      if (!Schema::hasColumn('team_fixture_players', 'team1_no_profile_id')) {
        $table->unsignedBigInteger('team1_no_profile_id')->nullable()->after('team1_id');
      }

      if (!Schema::hasColumn('team_fixture_players', 'team2_no_profile_id')) {
        $table->unsignedBigInteger('team2_no_profile_id')->nullable()->after('team2_id');
      }

    });
  }

  public function down(): void
  {
    Schema::table('team_fixture_players', function (Blueprint $table) {
      // reverse changes
      $table->dropColumn(['team1_no_profile_id', 'team2_no_profile_id']);

      // revert nullable -> not nullable (ensure no nulls exist before running)
      $table->unsignedBigInteger('team1_id')->nullable(false)->change();
      $table->unsignedBigInteger('team2_id')->nullable(false)->change();
    });
  }
};
