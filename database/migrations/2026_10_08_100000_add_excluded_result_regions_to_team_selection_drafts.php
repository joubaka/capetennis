<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_result_selection_drafts', function (Blueprint $table) {
            $table->json('excluded_result_region_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('team_result_selection_drafts', function (Blueprint $table) {
            $table->dropColumn('excluded_result_region_ids');
        });
    }
};
