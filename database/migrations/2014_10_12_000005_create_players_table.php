<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('players')) {
            return;
        }

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('surname')->nullable();
            $table->string('cellNr')->nullable();
            $table->string('gender')->nullable();
            $table->unsignedBigInteger('userId')->nullable();
            $table->string('email')->nullable();
            $table->date('dateOfBirth')->nullable();
            $table->string('coach')->nullable();
            $table->timestamp('profile_updated_at')->nullable();
            $table->boolean('profile_complete')->default(false);
            $table->timestamps();
        });

        Schema::create('user_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_players');
        Schema::dropIfExists('players');
    }
};
