<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Per-draw engine mode override
        if (! Schema::hasColumn('draws', 'engine_mode')) {
            Schema::table('draws', function (Blueprint $table) {
                // null = inherit from event/global config; 'legacy'|'hybrid'|'canonical' = explicit override
                $table->string('engine_mode', 20)->nullable()->after('locked');
            });
        }

        // Per-event engine mode override (applies to all draws in that event unless draw overrides)
        if (! Schema::hasColumn('events', 'engine_mode')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('engine_mode', 20)->nullable()->after('status');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('draws', 'engine_mode')) {
            Schema::table('draws', function (Blueprint $table) {
                $table->dropColumn('engine_mode');
            });
        }
        if (Schema::hasColumn('events', 'engine_mode')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('engine_mode');
            });
        }
    }
};
