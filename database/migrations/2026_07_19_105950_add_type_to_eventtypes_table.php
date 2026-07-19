<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the 'type' discriminator column to eventtypes.
     * This column exists in production but was omitted from the original migration.
     * Values: 1 = Individual, 2 = Team, 3 = Camp  (see EventType constants).
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('eventtypes') || Schema::hasColumn('eventtypes', 'type')) {
            return;
        }

        Schema::table('eventtypes', function (Blueprint $table) {
            $table->integer('type')->nullable()->after('name');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('eventtypes') || !Schema::hasColumn('eventtypes', 'type')) {
            return;
        }

        Schema::table('eventtypes', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
