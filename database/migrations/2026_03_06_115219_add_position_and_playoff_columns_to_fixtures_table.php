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
        Schema::table('fixtures', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable()->after('match_status');
            $table->string('playoff_type')->nullable()->after('position');
            $table->unsignedInteger('feeder_slot')->nullable()->after('playoff_type');
            $table->unsignedBigInteger('region1')->nullable()->after('feeder_slot');
            $table->unsignedBigInteger('region2')->nullable()->after('region1');
            $table->unsignedInteger('tie_nr')->nullable()->after('region2');
            $table->unsignedInteger('home_rank_nr')->nullable()->after('tie_nr');
            $table->unsignedInteger('away_rank_nr')->nullable()->after('home_rank_nr');
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
