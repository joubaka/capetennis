<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_pathway_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id')->unique();
            $table->string('stage', 20)->nullable();
            $table->string('wilson_division', 1)->nullable();
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->timestamps();
        });
        Schema::create('player_pathway_achievements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id');
            $table->string('stage', 20);
            $table->string('milestone', 60);
            $table->unsignedBigInteger('verified_by_user_id');
            $table->timestamp('verified_at');
            $table->unique(['player_id', 'stage', 'milestone'], 'player_pathway_achievement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_pathway_achievements');
        Schema::dropIfExists('player_pathway_profiles');
    }
};
