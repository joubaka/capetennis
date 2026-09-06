<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('draw_settings', 'require_full_sets')) {
            Schema::table('draw_settings', function (Blueprint $table): void {
                $table->boolean('require_full_sets')->default(true)->after('num_sets');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('draw_settings', 'require_full_sets')) {
            Schema::table('draw_settings', fn (Blueprint $table) => $table->dropColumn('require_full_sets'));
        }
    }
};
