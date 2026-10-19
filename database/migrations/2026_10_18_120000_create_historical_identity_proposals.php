<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_identity_proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('fixture_player_id');
            $table->unsignedTinyInteger('side');
            $table->unsignedBigInteger('source_team_id');
            $table->unsignedBigInteger('suggested_player_id');
            $table->unsignedBigInteger('created_by');
            $table->string('source_fingerprint', 64);
            $table->string('target_fingerprint', 64);
            $table->string('cohort', 150);
            $table->text('evidence');
            $table->text('reference');
            $table->text('reason');
            $table->timestamp('created_at');
            $table->index(['event_id', 'fixture_player_id', 'side'], 'historical_identity_event_fixture_side_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_identity_proposals');
    }
};
