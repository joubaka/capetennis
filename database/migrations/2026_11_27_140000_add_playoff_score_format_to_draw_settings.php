<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('draw_settings',function(Blueprint $table): void {
            $table->string('playoff_score_format',64)->nullable()->after('score_format');
        });
    }
    public function down(): void
    {
        Schema::table('draw_settings',function(Blueprint $table): void {
            $table->dropColumn('playoff_score_format');
        });
    }
};
