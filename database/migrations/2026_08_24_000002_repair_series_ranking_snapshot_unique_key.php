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
        // The snapshot key is required for rollback-safe ranking history.
    }

    private function indexExists(string $indexName): bool
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return collect(DB::select(
                'SHOW INDEX FROM `series_rankings` WHERE Key_name = ?',
                [$indexName]
            ))->isNotEmpty();
        }

        return false;
    }
};
