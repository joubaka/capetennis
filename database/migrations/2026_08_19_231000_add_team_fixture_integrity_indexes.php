<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('draw_registrations') && ! Schema::hasColumn('draw_registrations', 'box_number')) {
            Schema::table('draw_registrations', function (Blueprint $table) {
                $table->unsignedTinyInteger('box_number')->nullable()->after('seed');
            });
        }

        $this->addIndex('team_fixtures', ['draw_id'], 'team_fixtures_draw_id_index');
        $this->addIndex('team_fixture_players', ['team_fixture_id'], 'team_fixture_players_fixture_id_index');
        $this->addIndex('team_fixture_players', ['team_fixture_id', 'slot_no'], 'team_fixture_players_fixture_slot_index');
        $this->addIndex('team_fixture_results', ['team_fixture_id'], 'team_fixture_results_fixture_id_index');
        $this->addIndex('team_fixture_results', ['team_fixture_id', 'set_nr'], 'team_fixture_results_fixture_set_index');
    }

    public function down(): void
    {
        foreach ([
            ['team_fixtures', 'team_fixtures_draw_id_index'],
            ['team_fixture_players', 'team_fixture_players_fixture_id_index'],
            ['team_fixture_players', 'team_fixture_players_fixture_slot_index'],
            ['team_fixture_results', 'team_fixture_results_fixture_id_index'],
            ['team_fixture_results', 'team_fixture_results_fixture_set_index'],
        ] as [$table, $index]) {
            try {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
            } catch (Throwable) {
                // Safe rollback across installations where an index was already absent.
            }
        }
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

    private function indexExists(string $table, string $name): bool
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]))->isNotEmpty();
        }

        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select(sprintf('PRAGMA index_list("%s")', $table)))
                ->contains(fn (object $index) => $index->name === $name);
        }

        return false;
    }
};
