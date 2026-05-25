<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registration_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('registration_orders', 'total_fee')) {
                $table->decimal('total_fee', 10, 2)->default(0)->after('payfast_pf_payment_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registration_orders', function (Blueprint $table) {
            if (Schema::hasColumn('registration_orders', 'total_fee')) {
                $table->dropColumn('total_fee');
            }
        });
    }
};
