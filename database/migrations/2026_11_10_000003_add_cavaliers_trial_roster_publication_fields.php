<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cavaliers_trial_roster_drafts', 'declined')) {
            Schema::table('cavaliers_trial_roster_drafts', function (Blueprint $table) {
                $table->json('declined')->nullable();
            });
        }
        if (! Schema::hasColumn('event_nominations', 'roster_position')) {
            Schema::table('event_nominations', function (Blueprint $table) {
                $table->unsignedSmallInteger('roster_position')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('event_nominations', function (Blueprint $table) {
            $table->dropColumn('roster_position');
        });
        Schema::table('cavaliers_trial_roster_drafts', function (Blueprint $table) {
            $table->dropColumn('declined');
        });
    }
};
