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
            if (!Schema::hasColumn('fixtures', 'position')) {
                $table->unsignedTinyInteger('position')->nullable()
                    ->comment('Position playoff: 3=3rd/4th, 5=5th/6th, 7=7th/8th, etc.');
            }
            if (!Schema::hasColumn('fixtures', 'playoff_type')) {
                $table->string('playoff_type', 50)->nullable()
                    ->comment('Playoff type label: 3rd/4th, 5th/6th, cons_sf1, etc.');
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
            if (Schema::hasColumn('fixtures', 'position')) {
                $table->dropColumn('position');
            }
            if (Schema::hasColumn('fixtures', 'playoff_type')) {
                $table->dropColumn('playoff_type');
            }
        });
    }
};
