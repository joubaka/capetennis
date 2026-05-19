<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('registration_orders', 'wallet_transaction_id')) {
            Schema::table('registration_orders', function (Blueprint $table) {
                $table->string('payment_method', 20)->nullable()->after('payfast_pf_payment_id');
                $table->unsignedBigInteger('wallet_transaction_id')->nullable()->after('payment_method');
            });
        }
    }

    public function down(): void
    {
        Schema::table('registration_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'wallet_transaction_id']);
        });
    }
};
