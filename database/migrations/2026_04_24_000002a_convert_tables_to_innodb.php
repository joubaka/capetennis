<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need to be InnoDB to support foreign key constraints.
     */
    private array $tables = [
        'users',
        'events',
        'event_convenors',
        'event_expenses',
        'event_income_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` ENGINE = MyISAM");
            }
        }
    }
};
