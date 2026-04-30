<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('violation_type_id')->constrained('violation_types');
            $table->date('violation_date');
            $table->enum('penalty_type', ['warning', 'point', 'game', 'default'])->nullable();
            $table->unsignedInteger('points_assigned');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_violations');
    }
};
