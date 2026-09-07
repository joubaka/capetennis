<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('registration_orders', 'status')) {
            Schema::table('registration_orders', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->after('pay_status')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('registration_orders', 'status')) {
            Schema::table('registration_orders', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            });
        }
    }
};
