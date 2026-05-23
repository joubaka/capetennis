<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ranking_lists') || Schema::hasColumn('ranking_lists', 'best_num_of_scores')) {
            return;
        }

        Schema::table('ranking_lists', function (Blueprint $table) {
            $table->unsignedTinyInteger('best_num_of_scores')->nullable()->after('series_id')
                  ->comment('Override series-level best_num_of_scores for this category');
        });
    }

    public function down(): void
    {
        Schema::table('ranking_lists', function (Blueprint $table) {
            $table->dropColumn('best_num_of_scores');
        });
    }
};
