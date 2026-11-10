<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_pathway_moves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id');
            $table->string('from_stage', 20);
            $table->string('to_stage', 20);
            $table->date('started_on');
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->timestamps();
            $table->unique(['player_id', 'to_stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_pathway_moves');
    }
};
