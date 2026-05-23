<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema hardening: fixtures table.
 *
 * Problems fixed:
 *  - match_status nullable INT — 3,669 NULLs in production.
 *    NULL treated as 0 (pending) by all code, backfill before tightening.
 *  - round nullable — 26 NULLs; cannot index efficiently without default.
 *  - stage nullable — 3,633 NULLs; older records pre-date stage column.
 *    Leave nullable but add index (stage is used in WHERE clauses).
 *  - No index on draw_id, parent_fixture_id, winner_registration —
 *    all three are queried heavily by progression, audit, and standings.
 *  - Duplicate fixture_results (fixture_id, set_nr) have no unique constraint;
 *    production data has 2,498 dupes so we cannot add UNIQUE now — add index only.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill NULLs
        DB::table("fixtures")->whereNull("match_status")->update(["match_status" => 0]);
        DB::table("fixtures")->whereNull("round")->update(["round" => 0]);

        Schema::table("fixtures", function (Blueprint $table) {
            $table->integer("match_status")->default(0)->change();
        });

        // Add indexes individually — MySQL uses raw DDL for prefix/idempotency;
        // SQLite (test env) uses Schema::table with try/catch for duplicate protection.
        $isMysql = DB::getDriverName() === "mysql";

        if ($isMysql) {
            $fixtureIndexes = [
                "fixtures_draw_id_index"            => "ALTER TABLE fixtures ADD INDEX fixtures_draw_id_index (draw_id)",
                "fixtures_parent_fixture_id_index"  => "ALTER TABLE fixtures ADD INDEX fixtures_parent_fixture_id_index (parent_fixture_id)",
                "fixtures_winner_registration_index"=> "ALTER TABLE fixtures ADD INDEX fixtures_winner_registration_index (winner_registration)",
                "fixtures_round_index"              => "ALTER TABLE fixtures ADD INDEX fixtures_round_index (round)",
                "fixtures_stage_index"              => "ALTER TABLE fixtures ADD INDEX fixtures_stage_index (stage(20))",
                "fixtures_draw_stage_index"         => "ALTER TABLE fixtures ADD INDEX fixtures_draw_stage_index (draw_id, stage(20))",
                "fixtures_draw_round_index"         => "ALTER TABLE fixtures ADD INDEX fixtures_draw_round_index (draw_id, round)",
            ];
            foreach ($fixtureIndexes as $name => $ddl) {
                try {
                    DB::statement($ddl);
                } catch (\Throwable $e) {
                    if (!str_contains($e->getMessage(), "Duplicate key name")) {
                        throw $e;
                    }
                }
            }

            $resultIndexes = [
                "fixture_results_fixture_id_index"  => "ALTER TABLE fixture_results ADD INDEX fixture_results_fixture_id_index (fixture_id)",
                "fixture_results_fixture_set_index" => "ALTER TABLE fixture_results ADD INDEX fixture_results_fixture_set_index (fixture_id, set_nr)",
            ];
            foreach ($resultIndexes as $name => $ddl) {
                try {
                    DB::statement($ddl);
                } catch (\Throwable $e) {
                    if (!str_contains($e->getMessage(), "Duplicate key name")) {
                        throw $e;
                    }
                }
            }
        } else {
            // SQLite — Schema::table with per-index try/catch
            $addFixtureIndex = function (string $col, string $name) {
                try {
                    Schema::table("fixtures", function (\Illuminate\Database\Schema\Blueprint $t) use ($col, $name) {
                        $t->index($col, $name);
                    });
                } catch (\Throwable $e) {
                    // ignore duplicate index errors in SQLite
                }
            };
            $addFixtureIndex("draw_id",             "fixtures_draw_id_index");
            $addFixtureIndex("parent_fixture_id",   "fixtures_parent_fixture_id_index");
            $addFixtureIndex("winner_registration",  "fixtures_winner_registration_index");
            $addFixtureIndex("round",               "fixtures_round_index");
            $addFixtureIndex("stage",               "fixtures_stage_index");
            try {
                Schema::table("fixtures", function (\Illuminate\Database\Schema\Blueprint $t) {
                    $t->index(["draw_id", "stage"], "fixtures_draw_stage_index");
                });
            } catch (\Throwable $e) {}
            try {
                Schema::table("fixtures", function (\Illuminate\Database\Schema\Blueprint $t) {
                    $t->index(["draw_id", "round"], "fixtures_draw_round_index");
                });
            } catch (\Throwable $e) {}

            foreach (["fixture_results_fixture_id_index" => "fixture_id",
                      "fixture_results_fixture_set_index" => ["fixture_id", "set_nr"]] as $name => $col) {
                try {
                    Schema::table("fixture_results", function (\Illuminate\Database\Schema\Blueprint $t) use ($col, $name) {
                        $t->index($col, $name);
                    });
                } catch (\Throwable $e) {}
            }
        }
    }

    public function down(): void
    {
        Schema::table("fixture_results", function (Blueprint $table) {
            $table->dropIndex("fixture_results_fixture_set_index");
            $table->dropIndex("fixture_results_fixture_id_index");
        });

        Schema::table("fixtures", function (Blueprint $table) {
            $table->integer("match_status")->nullable()->default(null)->change();
            $table->dropIndex("fixtures_draw_id_index");
            $table->dropIndex("fixtures_parent_fixture_id_index");
            $table->dropIndex("fixtures_winner_registration_index");
            $table->dropIndex("fixtures_round_index");
        });
        DB::statement("ALTER TABLE fixtures DROP INDEX fixtures_stage_index");
        DB::statement("ALTER TABLE fixtures DROP INDEX fixtures_draw_stage_index");
        DB::statement("ALTER TABLE fixtures DROP INDEX fixtures_draw_round_index");
    }
};
