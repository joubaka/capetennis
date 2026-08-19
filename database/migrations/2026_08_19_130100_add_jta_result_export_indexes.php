<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $playerRegistrationIndexes = collect(Schema::getIndexes('player_registrations'))->pluck('name');
        Schema::table('player_registrations', function (Blueprint $table) use ($playerRegistrationIndexes): void {
            if (! $playerRegistrationIndexes->contains('jta_player_registration_lookup_idx')) {
                $table->index(['player_id', 'registration_id'], 'jta_player_registration_lookup_idx');
            }

            if (! $playerRegistrationIndexes->contains('jta_registration_player_lookup_idx')) {
                $table->index(['registration_id', 'player_id'], 'jta_registration_player_lookup_idx');
            }
        });

        $categoryResultIndexes = collect(Schema::getIndexes('category_results'))->pluck('name');
        Schema::table('category_results', function (Blueprint $table) use ($categoryResultIndexes): void {
            if (! $categoryResultIndexes->contains('jta_category_result_registration_idx')) {
                $table->index(
                    ['registration_id', 'event_id', 'category_id'],
                    'jta_category_result_registration_idx',
                );
            }
        });

        $seriesRankingIndexes = collect(Schema::getIndexes('series_rankings'))->pluck('name');
        Schema::table('series_rankings', function (Blueprint $table) use ($seriesRankingIndexes): void {
            if (! $seriesRankingIndexes->contains('jta_series_ranking_player_idx')) {
                $table->index(
                    ['player_id', 'status', 'series_id', 'category_id'],
                    'jta_series_ranking_player_idx',
                );
            }
        });
    }

    public function down(): void
    {
        // Intentionally retained to avoid removing lookup indexes that may be
        // shared by other player-history and ranking queries after deployment.
    }
};
