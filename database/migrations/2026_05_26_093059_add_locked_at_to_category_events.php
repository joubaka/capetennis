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
        // locked_at was defined in the original create migration but never added to
        // pre-existing databases because that migration had an early-return guard.
        // This migration safely adds it only if it is still missing.
        if (! Schema::hasColumn('category_events', 'locked_at')) {
            Schema::table('category_events', function (Blueprint $table) {
                $table->timestamp('locked_at')->nullable()->after('nominations_published');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('category_events', 'locked_at')) {
            Schema::table('category_events', function (Blueprint $table) {
                $table->dropColumn('locked_at');
            });
        }
    }
};
