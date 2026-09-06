<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->boolean('use_third_score_tiebreak')->default(true)->after('auto_award_rule');
            $table->boolean('use_head_to_head_tiebreak')->default(true)->after('use_third_score_tiebreak');
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn(['use_third_score_tiebreak', 'use_head_to_head_tiebreak']);
        });
    }
};
