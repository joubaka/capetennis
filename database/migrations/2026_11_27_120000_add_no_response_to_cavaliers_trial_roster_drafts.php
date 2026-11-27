<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cavaliers_trial_roster_drafts', function (Blueprint $table) {
            $table->json('no_response')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cavaliers_trial_roster_drafts', function (Blueprint $table) {
            $table->dropColumn('no_response');
        });
    }
};
