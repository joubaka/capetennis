<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->json('team_draw_selection')->nullable();
            $table->string('team_draw_batch_key', 64)->nullable();
            $table->unsignedSmallInteger('team_draw_batch_index')->nullable();
            $table->unique(['event_id', 'team_draw_batch_key', 'team_draw_batch_index'], 'draw_team_batch_unique');
        });
    }

    public function down(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->dropUnique('draw_team_batch_unique');
            $table->dropColumn(['team_draw_selection', 'team_draw_batch_key', 'team_draw_batch_index']);
        });
    }
};
