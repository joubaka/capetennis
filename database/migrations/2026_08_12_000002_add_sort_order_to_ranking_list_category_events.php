<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ranking_list_category_events')
            && !Schema::hasColumn('ranking_list_category_events', 'sort_order')) {
            Schema::table('ranking_list_category_events', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->nullable()->after('category_event_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ranking_list_category_events')
            && Schema::hasColumn('ranking_list_category_events', 'sort_order')) {
            Schema::table('ranking_list_category_events', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};
