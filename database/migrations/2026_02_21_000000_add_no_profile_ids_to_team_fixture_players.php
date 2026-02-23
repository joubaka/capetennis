<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    // Changing existing columns to nullable uses change(); doctrine/dbal is required.
    Schema::table('team_fixture_players', function (Blueprint $table) {
      // add no-profile reference columns
      $table->unsignedBigInteger('team1_no_profile_id')->nullable()->after('team1_id');
      $table->unsignedBigInteger('team2_no_profile_id')->nullable()->after('team2_id');

      // allow existing profile id columns to be nullable
      $table->unsignedBigInteger('team1_id')->nullable()->change();
      $table->unsignedBigInteger('team2_id')->nullable()->change();
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
