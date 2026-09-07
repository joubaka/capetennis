<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('draw_settings', 'score_format')) {
            Schema::table('draw_settings', function (Blueprint $table): void {
                $table->string('score_format', 64)->nullable()->after('num_sets');
            });
        }

        if (! Schema::hasColumn('draw_settings', 'notes_print')) {
            Schema::table('draw_settings', function (Blueprint $table): void {
                $table->json('notes_print')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('draw_settings', 'notes_print')) {
            Schema::table('draw_settings', fn (Blueprint $table) => $table->dropColumn('notes_print'));
        }

        if (Schema::hasColumn('draw_settings', 'score_format')) {
            Schema::table('draw_settings', fn (Blueprint $table) => $table->dropColumn('score_format'));
        }
    }
};
