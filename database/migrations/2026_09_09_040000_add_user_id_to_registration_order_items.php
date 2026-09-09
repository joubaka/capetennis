<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('registration_order_items')
            && !Schema::hasColumn('registration_order_items', 'user_id')) {
            Schema::table('registration_order_items', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // This column predates the migration history in production. Do not
        // remove canonical registration ownership data during a rollback.
    }
};
