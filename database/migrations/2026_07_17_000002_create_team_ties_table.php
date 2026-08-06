<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_ties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draw_id');
            $table->unsignedSmallInteger('round_nr');
            $table->unsignedSmallInteger('tie_nr');
            $table->unsignedBigInteger('home_team_id');
            $table->unsignedBigInteger('away_team_id');
            $table->string('status', 20)->default('draft'); // draft|validated|published|completed
            $table->unsignedBigInteger('winner_team_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['draw_id', 'round_nr', 'tie_nr']);
            $table->unique(['draw_id', 'round_nr', 'home_team_id', 'away_team_id']);

            $table->foreign('draw_id')->references('id')->on('draws')->cascadeOnDelete();
            $table->foreign('home_team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('away_team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('winner_team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_ties');
    }
};
