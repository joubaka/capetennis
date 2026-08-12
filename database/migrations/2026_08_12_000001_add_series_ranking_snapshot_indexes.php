<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('series_rankings') || $this->indexExists('series_rankings_snapshot_lookup')) {
            return;
        }

        Schema::table('series_rankings', function (Blueprint $table) {
            $table->index(
                ['series_id', 'status', 'run_id', 'ranking_list_id'],
                'series_rankings_snapshot_lookup'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('series_rankings') || ! $this->indexExists('series_rankings_snapshot_lookup')) {
            return;
        }

        Schema::table('series_rankings', function (Blueprint $table) {
            $table->dropIndex('series_rankings_snapshot_lookup');
        });
    }

    private function indexExists(string $indexName): bool
    {
        return collect(Schema::getIndexes('series_rankings'))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $indexName);
    }
};
