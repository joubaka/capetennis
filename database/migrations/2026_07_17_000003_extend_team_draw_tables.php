<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add format reference to draws
        if (! Schema::hasColumn('draws', 'team_event_format_id')) {
            Schema::table('draws', function (Blueprint $table) {
                $table->unsignedBigInteger('team_event_format_id')->nullable()->after('engine_mode');
            });
        }

        $this->ensureForeignKey(
            'draws',
            'draws_team_event_format_id_foreign',
            'team_event_format_id',
            'team_event_formats'
        );

        // Add tie/rubber metadata columns to team_fixtures
        if (! Schema::hasColumn('team_fixtures', 'team_tie_id')) {
            Schema::table('team_fixtures', function (Blueprint $table) {
                $table->unsignedBigInteger('team_tie_id')->nullable()->after('draw_id')->index();
            });
        }

        $this->ensureForeignKey(
            'team_fixtures',
            'team_fixtures_team_tie_id_foreign',
            'team_tie_id',
            'team_ties'
        );

        Schema::table('team_fixtures', function (Blueprint $table) {
            if (!Schema::hasColumn('team_fixtures', 'rubber_sequence')) {
                $table->unsignedTinyInteger('rubber_sequence')->nullable();
            }
            if (!Schema::hasColumn('team_fixtures', 'rubber_code')) {
                $table->string('rubber_code', 30)->nullable();
            }
            if (!Schema::hasColumn('team_fixtures', 'rubber_name')) {
                $table->string('rubber_name', 100)->nullable();
            }
            if (!Schema::hasColumn('team_fixtures', 'gender_rule')) {
                $table->string('gender_rule', 20)->nullable();
            }
            if (!Schema::hasColumn('team_fixtures', 'player_count_per_team')) {
                $table->unsignedTinyInteger('player_count_per_team')->nullable();
            }
        });

        // Add unique index for rubber idempotency.
        // Use SHOW INDEX for MySQL; fall back to try-catch for other drivers.
        $indexExists = collect(Schema::getIndexes('team_fixtures'))
            ->contains(fn (array $index) => ($index['name'] ?? null) === 'team_fixtures_tie_rubber_unique');

        if (!$indexExists) {
            Schema::table('team_fixtures', function (Blueprint $table) {
                $table->unique(['team_tie_id', 'rubber_sequence'], 'team_fixtures_tie_rubber_unique');
            });
        }

        // Add slot_no to team_fixture_players for deterministic pair ordering
        Schema::table('team_fixture_players', function (Blueprint $table) {
            if (!Schema::hasColumn('team_fixture_players', 'slot_no')) {
                $table->unsignedTinyInteger('slot_no')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_fixture_players', function (Blueprint $table) {
            if (Schema::hasColumn('team_fixture_players', 'slot_no')) {
                $table->dropColumn('slot_no');
            }
        });

        Schema::table('team_fixtures', function (Blueprint $table) {
            try { $table->dropForeign(['team_tie_id']); } catch (\Throwable) {}
            try { $table->dropUnique('team_fixtures_tie_rubber_unique'); } catch (\Throwable) {}
            $cols = ['team_tie_id', 'rubber_sequence', 'rubber_code', 'rubber_name', 'gender_rule', 'player_count_per_team'];
            $toDrop = array_values(array_filter($cols, fn($c) => Schema::hasColumn('team_fixtures', $c)));
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });

        Schema::table('draws', function (Blueprint $table) {
            if (Schema::hasColumn('draws', 'team_event_format_id')) {
                try { $table->dropForeign(['team_event_format_id']); } catch (\Throwable) {}
                $table->dropColumn('team_event_format_id');
            }
        });
    }

    private function ensureForeignKey(
        string $tableName,
        string $name,
        string $column,
        string $parentTable,
    ): void {
        $exists = collect(Schema::getForeignKeys($tableName))
            ->contains(fn (array $foreignKey) => ($foreignKey['name'] ?? null) === $name);

        if ($exists) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($name, $column, $parentTable) {
            $table->foreign($column, $name)
                ->references('id')
                ->on($parentTable)
                ->nullOnDelete();
        });
    }
};

