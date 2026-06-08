<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('fixtures', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('stage');
        });
    }

    public function down()
    {
        Schema::table('fixtures', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
