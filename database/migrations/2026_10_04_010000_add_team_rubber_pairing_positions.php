<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_event_format_rubbers', function (Blueprint $table) {
            $table->json('home_positions')->nullable();
            $table->json('away_positions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('team_event_format_rubbers', function (Blueprint $table) {
            $table->dropColumn(['home_positions', 'away_positions']);
        });
    }
};
