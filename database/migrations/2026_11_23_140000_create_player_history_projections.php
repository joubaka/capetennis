<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_history_projection_builds', function (Blueprint $table) {
            $table->string('slot', 20)->primary();
            $table->char('build_id', 64);
            $table->unsignedInteger('version');
            $table->longText('metadata');
            $table->char('checksum', 64);
        });
        Schema::create('player_history_projections', function (Blueprint $table) {
            $table->char('build_id', 64);
            $table->unsignedBigInteger('player_id');
            $table->longText('payload');
            $table->char('checksum', 64);
            $table->primary(['build_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_history_projections');
        Schema::dropIfExists('player_history_projection_builds');
    }
};
