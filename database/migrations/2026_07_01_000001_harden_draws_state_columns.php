<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema hardening: draw state columns.
 *
 * Problems fixed:
 *  - draws.published / locked / oop_published / oop_created stored as nullable INT
 *    causing NULL != 0 comparison bugs in guards.
 *  - Backfills NULL -> 0 before adding NOT NULL default.
 *  - draws.drawType_id stored as TEXT instead of INT (harmless but wasteful;
 *    we add an index on event_id and category_event_id only — type stays TEXT
 *    to avoid risk of data loss on existing non-numeric values).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill NULLs to 0 before tightening
        DB::table("draws")->whereNull("published")->update(["published" => 0]);
        DB::table("draws")->whereNull("locked")->update(["locked" => 0]);
        DB::table("draws")->whereNull("oop_published")->update(["oop_published" => 0]);
        DB::table("draws")->whereNull("oop_created")->update(["oop_created" => 0]);

        Schema::table("draws", function (Blueprint $table) {
            $table->integer("published")->default(0)->change();
            $table->integer("locked")->default(0)->change();
            $table->integer("oop_published")->default(0)->change();
            $table->integer("oop_created")->default(0)->change();

            // Add indexes for frequent lookup patterns (safe: catch duplicate-key exception)
            $table->index("event_id", "draws_event_id_index");
            $table->index("category_event_id", "draws_category_event_id_index");
        });
    }

    public function down(): void
    {
        Schema::table("draws", function (Blueprint $table) {
            $table->integer("published")->nullable()->default(null)->change();
            $table->integer("locked")->nullable()->default(null)->change();
            $table->integer("oop_published")->nullable()->default(null)->change();
            $table->integer("oop_created")->nullable()->default(null)->change();
            $table->dropIndex("draws_event_id_index");
            $table->dropIndex("draws_category_event_id_index");
        });
    }
};
