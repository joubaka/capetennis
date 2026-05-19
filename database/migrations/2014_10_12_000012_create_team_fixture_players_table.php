<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_fixture_players')) {
            return;
        }

        Schema::create('team_fixture_players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_fixture_id')->nullable();
            $table->unsignedBigInteger('team1_id')->nullable();
            $table->unsignedBigInteger('team2_id')->nullable();
            $table->unsignedBigInteger('team1_no_profile_id')->nullable();
            $table->unsignedBigInteger('team2_no_profile_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_fixture_players');
    }
};
