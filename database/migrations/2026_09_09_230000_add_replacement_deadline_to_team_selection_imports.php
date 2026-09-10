<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_selection_imports')
            && ! Schema::hasColumn('team_selection_imports', 'replacement_payment_deadline')) {
            Schema::table('team_selection_imports', function (Blueprint $table): void {
                $table->timestamp('replacement_payment_deadline')->nullable()->after('payment_deadline');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('team_selection_imports')
            && Schema::hasColumn('team_selection_imports', 'replacement_payment_deadline')) {
            Schema::table('team_selection_imports', function (Blueprint $table): void {
                $table->dropColumn('replacement_payment_deadline');
            });
        }
    }
};
