<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('is_player_of_colour')->nullable()->after('coach');
            $table->timestamp('player_of_colour_declared_at')->nullable()->after('is_player_of_colour');
            $table->foreignId('player_of_colour_declared_by_user_id')
                ->nullable()
                ->after('player_of_colour_declared_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('player_of_colour_declared_by_user_id');
            $table->dropColumn(['is_player_of_colour', 'player_of_colour_declared_at']);
        });
    }
};
