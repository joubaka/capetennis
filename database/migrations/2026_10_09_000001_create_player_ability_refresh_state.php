<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_ability_refresh_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('generation')->default(1);
            $table->unsignedBigInteger('completed_generation')->default(0);
            $table->timestamp('changed_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
        });
        // Seed before listeners run: an INSERT query callback must never insert
        // on the same connection and interfere with MySQL LAST_INSERT_ID.
        DB::table('player_ability_refresh_state')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('player_ability_refresh_state');
    }
};
