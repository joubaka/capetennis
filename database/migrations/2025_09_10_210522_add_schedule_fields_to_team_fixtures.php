<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    Schema::table('team_fixtures', function (Blueprint $table) {
      if (!Schema::hasColumn('team_fixtures', 'scheduled_at')) {
        $table->dateTime('scheduled_at')->nullable()->after('scheduled');
      }

      if (!Schema::hasColumn('team_fixtures', 'venue_id')) {
        $table->unsignedBigInteger('venue_id')->nullable()->after('scheduled_at');
        $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
      }

      if (!Schema::hasColumn('team_fixtures', 'court_label')) {
        $table->string('court_label', 50)->nullable()->after('venue_id');
      }

      if (!Schema::hasColumn('team_fixtures', 'duration_min')) {
        $table->unsignedInteger('duration_min')->nullable()->after('court_label')
          ->comment('Match duration in minutes');
      }
    });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    Schema::table('team_fixtures', function (Blueprint $table) {
      $table->dropForeign(['venue_id']);
      $table->dropColumn(['scheduled_at', 'venue_id', 'court_label', 'duration_min']);
    });
    }
};
