<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('registration_orders')) {
            return;
        }

        Schema::create('registration_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('wallet_reserved', 10, 2)->default(0);
            $table->boolean('wallet_debited')->default(false);
            $table->boolean('payfast_paid')->default(false);
            $table->string('payfast_pf_payment_id')->nullable();
            $table->decimal('payfast_amount_due', 10, 2)->nullable();
            $table->boolean('pay_status')->default(false);
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->decimal('total_fee', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_orders');
    }
};
