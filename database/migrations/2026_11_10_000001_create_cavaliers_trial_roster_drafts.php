<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cavaliers_trial_roster_drafts', function (Blueprint $table) {
            // Legacy category_events tables may use MyISAM and signed INT ids.
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('category_event_id');
            $table->primary(['event_id', 'category_event_id']);
            $table->uuid('revision');
            $table->json('selected');
            $table->json('reserves');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cavaliers_trial_roster_drafts');
    }
};
