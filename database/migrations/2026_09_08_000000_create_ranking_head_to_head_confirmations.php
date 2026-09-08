<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ranking_head_to_head_confirmations')) {
            return;
        }

        Schema::create('ranking_head_to_head_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('series_id')->index();
            $table->string('run_id', 100)->index();
            $table->unsignedBigInteger('ranking_list_id')->index();
            $table->unsignedBigInteger('fixture_id')->index();
            $table->unsignedBigInteger('player1_id');
            $table->unsignedBigInteger('player2_id');
            $table->unsignedBigInteger('winner_player_id');
            $table->unsignedBigInteger('confirmed_by')->index();
            $table->timestamp('confirmed_at');
            $table->json('decision_snapshot');
            $table->timestamps();

            $table->unique(
                ['series_id', 'run_id', 'ranking_list_id', 'player1_id', 'player2_id'],
                'ranking_h2h_confirmation_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_head_to_head_confirmations');
    }
};
