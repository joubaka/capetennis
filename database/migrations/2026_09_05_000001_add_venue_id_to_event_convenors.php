<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureInnoDb('venues');
        $this->ensureInnoDb('event_convenors');

        if (! Schema::hasColumn('event_convenors', 'venue_id')) {
            Schema::table('event_convenors', function (Blueprint $table) {
                $table->foreignId('venue_id')->nullable()->after('user_id');
            });
        }

        if (! $this->foreignKeyExists()) {
            Schema::table('event_convenors', function (Blueprint $table) {
                $table->foreign('venue_id', 'event_convenors_venue_id_foreign')
                    ->references('id')
                    ->on('venues')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event_convenors', 'venue_id')) {
            return;
        }

        $foreignKeyExists = $this->foreignKeyExists();

        Schema::table('event_convenors', function (Blueprint $table) use ($foreignKeyExists) {
            if ($foreignKeyExists) {
                $table->dropForeign('event_convenors_venue_id_foreign');
            }

            $table->dropColumn('venue_id');
        });
    }

    private function foreignKeyExists(): bool
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return DB::selectOne(
                'SELECT 1 AS found FROM information_schema.KEY_COLUMN_USAGE '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? '
                .'AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
                ['event_convenors', 'event_convenors_venue_id_foreign']
            ) !== null;
        }

        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select('PRAGMA foreign_key_list("event_convenors")'))
                ->contains(fn (object $foreignKey) => $foreignKey->from === 'venue_id');
        }

        return false;
    }

    private function ensureInnoDb(string $table): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || ! Schema::hasTable($table)) {
            return;
        }

        $current = DB::selectOne(
            'SELECT ENGINE AS engine FROM information_schema.TABLES '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
            [$table]
        );

        if ($current !== null && strcasecmp((string) $current->engine, 'InnoDB') !== 0) {
            DB::statement(sprintf('ALTER TABLE `%s` ENGINE = InnoDB', $table));
        }
    }
};
