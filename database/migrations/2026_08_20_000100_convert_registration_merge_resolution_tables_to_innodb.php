<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Duplicate-player merges may retire an abandoned registration inside the
     * same transaction. Legacy MyISAM storage would make that change survive a
     * later merge failure, so the registration table must support rollback.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('category_event_registrations')) {
            DB::statement('ALTER TABLE `category_event_registrations` ENGINE=InnoDB');
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: MyISAM would remove merge rollback safety.
    }
};
