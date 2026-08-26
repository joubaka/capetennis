<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('series')) {
            return;
        }

        Schema::create('series', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->text('name');
            $table->integer('leaderboard_published')->default(0);
            $table->boolean('auto_award_rule')->default(true);
            $table->integer('best_num_of_scores')->nullable();
            $table->integer('points_template_created')->nullable();
            $table->string('rank_type', 100)->nullable()->default('position_based');
            $table->integer('year');
        });
    }

    public function down(): void
    {
        // This is a repair migration; do not remove a potentially populated table on rollback.
    }
};
