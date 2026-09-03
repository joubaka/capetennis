<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates draw-domain tables required for the P0 stabilization test suite.
 * These tables exist in production MySQL but were not included in the
 * base SQLite testing schema migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->nullable();
                $table->string('information')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('email')->nullable();
                $table->string('organizer')->nullable();
                $table->decimal('entryFee', 8, 2)->nullable();
                $table->integer('deadline')->nullable();
                $table->string('withdrawal_deadline')->nullable();
                $table->string('eventType')->nullable();
                $table->string('status')->nullable();
                $table->string('venue_notes')->nullable();
                $table->string('logo')->nullable();
                $table->boolean('published')->default(false);
                $table->boolean('signUp')->default(false);
                $table->string('engine_mode', 20)->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('events', 'engine_mode')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('engine_mode', 20)->nullable();
            });
        }

        if (! Schema::hasTable('draws')) {
            Schema::create('draws', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('drawName')->nullable();
                $table->unsignedBigInteger('drawType_id')->nullable();
                $table->unsignedBigInteger('category_event_id')->nullable();
                $table->unsignedBigInteger('event_id')->nullable();
                $table->boolean('published')->default(false);
                $table->boolean('oop_published')->default(false);
                $table->boolean('locked')->default(false);
                $table->string('engine_mode', 20)->nullable();
                $table->integer('num_courts')->nullable();
                $table->string('start_time')->nullable();
                $table->integer('time_per_match')->nullable();
                $table->unsignedBigInteger('team_category_id')->nullable();
                $table->string('gender')->nullable();
                $table->boolean('oop_created')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('draw_groups')) {
            Schema::create('draw_groups', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('draw_id')->nullable();
                $table->string('name')->nullable();
                $table->string('color')->nullable();
                $table->string('category_slug')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('draw_group_registrations')) {
            Schema::create('draw_group_registrations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('draw_group_id')->nullable();
                $table->unsignedBigInteger('registration_id')->nullable();
                $table->integer('seed')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('draw_registrations')) {
            Schema::create('draw_registrations', function (Blueprint $table) {
                $table->unsignedBigInteger('draw_id');
                $table->unsignedBigInteger('registration_id');
                $table->integer('seed')->nullable();
                $table->primary(['draw_id', 'registration_id']);
            });
        }

        // Expand the stub created by the base schema migration.
        // Only add columns that are NOT managed by later alter-migrations.
        // (playoff_config → 2026_03_04, preset_key → 2024_01_15, notes → 2026_03_07)
        if (Schema::hasTable('draw_settings') && ! Schema::hasColumn('draw_settings', 'draw_id')) {
            Schema::table('draw_settings', function (Blueprint $table) {
                $table->unsignedBigInteger('draw_id')->nullable()->after('id');
                $table->integer('boxes')->nullable();
                $table->integer('playoff_size')->nullable();
                $table->integer('num_sets')->nullable();
            });
        }

        if (Schema::hasTable('fixtures') && ! Schema::hasColumn('fixtures', 'stage')) {
            Schema::table('fixtures', function (Blueprint $table) {
                $table->string('stage')->nullable()->after('draw_id');
                $table->integer('round')->nullable();
                $table->integer('match_nr')->nullable();
                $table->integer('match_status')->default(0);
                $table->unsignedBigInteger('registration1_id')->nullable();
                $table->unsignedBigInteger('registration2_id')->nullable();
                $table->unsignedBigInteger('winner_registration')->nullable();
                $table->unsignedBigInteger('parent_fixture_id')->nullable();
                $table->unsignedBigInteger('loser_parent_fixture_id')->nullable();
                $table->unsignedBigInteger('draw_group_id')->nullable();
                $table->integer('position')->nullable();
                $table->unsignedBigInteger('bracket_id')->nullable();
                $table->string('playoff_type')->nullable();
                $table->unsignedBigInteger('loser_feeder_slot')->nullable();
            });
        }

        if (! Schema::hasTable('fixture_results')) {
            Schema::create('fixture_results', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('fixture_id')->nullable();
                $table->unsignedBigInteger('result_id')->nullable();
                $table->unsignedBigInteger('winner_registration')->nullable();
                $table->unsignedBigInteger('loser_registration')->nullable();
                $table->integer('registration1_score')->nullable();
                $table->integer('registration2_score')->nullable();
                $table->integer('set_nr')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_of_plays')) {
            Schema::create('order_of_plays', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('fixture_id');
                $table->integer('venue_id');
                $table->datetime('time')->nullable();
                $table->integer('draw_id')->nullable();
                $table->string('court', 200)->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->integer('round_number')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('venues')) {
            Schema::create('venues', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->unsignedBigInteger('event_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('draw_venues')) {
            Schema::create('draw_venues', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('draw_id')->nullable();
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->integer('num_courts')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('engine_comparison_logs')) {
            Schema::create('engine_comparison_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('operation', 60);
                $table->unsignedBigInteger('draw_id')->nullable();
                $table->string('mismatch_type', 80);
                $table->string('engine_mode', 20)->default('hybrid');
                $table->text('legacy_result')->nullable();
                $table->text('canonical_result')->nullable();
                $table->boolean('was_fallback')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('engine_runs')) {
            Schema::create('engine_runs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('draw_id')->nullable();
                $table->string('engine_mode', 20);
                $table->string('operation_type', 50);
                $table->boolean('legacy_success')->nullable();
                $table->boolean('canonical_success')->nullable();
                $table->boolean('mismatch_detected')->default(false);
                $table->boolean('fallback_used')->default(false);
                $table->unsignedSmallInteger('mismatch_count')->default(0);
                $table->unsignedInteger('duration_ms')->default(0);
                $table->text('exception')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('engine_mismatches')) {
            Schema::create('engine_mismatches', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('draw_id')->nullable();
                $table->string('operation_type', 50);
                $table->string('mismatch_type', 80);
                $table->text('legacy_output')->nullable();
                $table->text('canonical_output')->nullable();
                $table->string('severity', 10)->default('medium');
                $table->boolean('resolved')->default(false);
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'draw_venues',
            'venues',
            'order_of_plays',
            'fixture_results',
            'draw_registrations',
            'draw_group_registrations',
            'draw_groups',
            'draws',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
