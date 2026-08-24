<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('series_rankings')) {
            return;
        }

        if ($this->indexExists('uniq_series_category_player')) {
            Schema::table('series_rankings', function (Blueprint $table): void {
                $table->dropUnique('uniq_series_category_player');
            });
        }

        if (! $this->indexExists('uniq_series_category_player_snapshot')) {
            Schema::table('series_rankings', function (Blueprint $table): void {
                $table->unique(
                    ['series_id', 'category_id', 'player_id', 'status', 'run_id'],
                    'uniq_series_category_player_snapshot'
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('series_rankings')) {
            return;
        }

        if ($this->indexExists('uniq_series_category_player_snapshot')) {
            Schema::table('series_rankings', function (Blueprint $table): void {
                $table->dropUnique('uniq_series_category_player_snapshot');
            });
        }

        if (! $this->indexExists('uniq_series_category_player')) {
            Schema::table('series_rankings', function (Blueprint $table): void {
                $table->unique(
                    ['series_id', 'category_id', 'player_id'],
                    'uniq_series_category_player'
                );
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return collect(DB::select(
                'SHOW INDEX FROM `series_rankings` WHERE Key_name = ?',
                [$indexName]
            ))->isNotEmpty();
        }

        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select('PRAGMA index_list("series_rankings")'))
                ->contains(fn (object $index) => $index->name === $indexName);
        }

        return false;
    }
};
