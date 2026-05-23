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
        Schema::table('draw_group_registrations', function (Blueprint $table) {
            $table->unique(['draw_group_id', 'registration_id'], 'dgr_group_registration_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('draw_group_registrations', function (Blueprint $table) {
            $table->dropUnique('dgr_group_registration_unique');
        });
    }
};
