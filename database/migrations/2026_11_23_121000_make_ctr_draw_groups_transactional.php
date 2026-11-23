<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** DDL locks each table and commits implicitly; run only this reviewed path. */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $tables = ['draw_groups', 'draw_group_registrations'];
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->whereIn('TABLE_NAME', $tables)->pluck('ENGINE', 'TABLE_NAME');
        // Preflight both tables before altering either. No generic engine conversion.
        foreach ($tables as $table) {
            if (!in_array(strtolower((string) ($engines[$table] ?? '')), ['myisam', 'innodb'], true)) {
                throw new RuntimeException('Review the storage engine for '.$table.' before running the CTR compatibility migration.');
            }
        }
        foreach ($tables as $table) {
            $before = DB::table($table)->count();
            if (strtolower($engines[$table]) === 'myisam') {
                // Identifiers come only from the literal two-table allowlist above.
                DB::statement('ALTER TABLE `'.$table.'` ENGINE=InnoDB');
            }
            $engine = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', $table)->value('ENGINE');
            if (strtolower((string) $engine) !== 'innodb' || DB::table($table)->count() !== $before) {
                throw new RuntimeException('Verify storage engine and row preservation for '.$table.' before continuing.');
            }
        }
    }

    public function down(): void
    {
        // Deliberately retained: downgrading would remove verified rollback safety.
    }
};
