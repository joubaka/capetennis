<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_event_registrations')
            || ! Schema::hasColumn('category_event_registrations', 'refund_method')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE category_event_registrations MODIFY refund_method ENUM('wallet','bank','payfast') NULL"
            );
        }
    }

    public function down(): void
    {
        // Historical PayFast refunds may exist after this migration. Narrowing
        // the enum would destroy their meaning, so rollback is intentionally safe.
    }
};
