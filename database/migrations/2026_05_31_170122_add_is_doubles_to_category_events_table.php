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
        Schema::table('category_events', function (Blueprint $table) {
            $table->boolean('is_doubles')->default(false)->after('entry_fee');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('category_events', function (Blueprint $table) {
            $table->dropColumn('is_doubles');
        });
    }
};
