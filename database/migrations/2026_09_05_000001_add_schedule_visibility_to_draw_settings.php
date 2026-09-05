<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('draw_settings', 'schedule_visibility')) {
            Schema::table('draw_settings', function (Blueprint $table): void {
                $table->string('schedule_visibility', 32)
                    ->default('full_schedule')
                    ->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('draw_settings', 'schedule_visibility')) {
            Schema::table('draw_settings', fn (Blueprint $table) => $table->dropColumn('schedule_visibility'));
        }
    }
};
