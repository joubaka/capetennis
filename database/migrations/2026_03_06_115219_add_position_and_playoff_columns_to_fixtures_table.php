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
        if (!Schema::hasTable('fixtures')) {
            return;
        }

        Schema::table('fixtures', function (Blueprint $table) {
            if (! Schema::hasColumn('fixtures', 'position')) {
                $table->unsignedInteger('position')->nullable()->after('match_status');
            }
            if (! Schema::hasColumn('fixtures', 'playoff_type')) {
                $table->string('playoff_type')->nullable()->after('position');
            }
            if (! Schema::hasColumn('fixtures', 'feeder_slot')) {
                $table->unsignedInteger('feeder_slot')->nullable()->after('playoff_type');
            }
            if (! Schema::hasColumn('fixtures', 'region1')) {
                $table->unsignedBigInteger('region1')->nullable()->after('feeder_slot');
            }
            if (! Schema::hasColumn('fixtures', 'region2')) {
                $table->unsignedBigInteger('region2')->nullable()->after('region1');
            }
            if (! Schema::hasColumn('fixtures', 'tie_nr')) {
                $table->unsignedInteger('tie_nr')->nullable()->after('region2');
            }
            if (! Schema::hasColumn('fixtures', 'home_rank_nr')) {
                $table->unsignedInteger('home_rank_nr')->nullable()->after('tie_nr');
            }
            if (! Schema::hasColumn('fixtures', 'away_rank_nr')) {
                $table->unsignedInteger('away_rank_nr')->nullable()->after('home_rank_nr');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fixtures', function (Blueprint $table) {
            $table->dropColumn([
                'position',
                'playoff_type',
                'feeder_slot',
                'region1',
                'region2',
                'tie_nr',
                'home_rank_nr',
                'away_rank_nr',
            ]);
        });
    }
};
