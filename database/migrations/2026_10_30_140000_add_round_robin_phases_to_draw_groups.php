<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draw_groups', function (Blueprint $table) {
            $table->unsignedTinyInteger('round_robin_phase')->default(1)->index();
            $table->unsignedSmallInteger('qualifying_position')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('draw_groups', function (Blueprint $table) {
            $table->dropIndex(['round_robin_phase']);
            $table->dropColumn(['round_robin_phase', 'qualifying_position']);
        });
    }
};
