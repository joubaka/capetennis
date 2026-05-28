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
        Schema::table('registration_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('registration_orders', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('pay_status');
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
        Schema::table('registration_orders', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
