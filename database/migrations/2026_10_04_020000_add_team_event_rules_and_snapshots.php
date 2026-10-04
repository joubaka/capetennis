<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_event_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->primary();
            $table->json('rules');
            $table->timestamps();
        });
        Schema::table('draws', function (Blueprint $table) {
            $table->json('team_scoring_rules')->nullable();
            $table->json('team_format_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->dropColumn(['team_scoring_rules', 'team_format_snapshot']);
        });
        Schema::dropIfExists('team_event_rules');
    }
};
