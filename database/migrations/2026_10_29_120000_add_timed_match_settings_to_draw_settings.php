<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draw_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('match_duration_minutes')->nullable();
            $table->unsignedTinyInteger('changeover_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('draw_settings', function (Blueprint $table) {
            $table->dropColumn(['match_duration_minutes', 'changeover_minutes']);
        });
    }
};
