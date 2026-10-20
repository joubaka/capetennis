<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_roster_promotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('no_profile_slot_id')->nullable();
            $table->unsignedBigInteger('profile_slot_id')->nullable();
            $table->unsignedBigInteger('player_id')->default(0);
            $table->unsignedInteger('source_rank');
            $table->unsignedInteger('target_rank');
            $table->unsignedBigInteger('actor_id');
            $table->timestamp('created_at');
            $table->index(['event_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_roster_promotions');
    }
};
