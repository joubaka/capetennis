<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_payment_orders')) {
            return;
        }

        Schema::create('team_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('player_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('wallet_reserved', 10, 2)->default(0);
            $table->decimal('payfast_amount_due', 10, 2)->nullable();
            $table->boolean('wallet_debited')->default(false);
            $table->boolean('payfast_paid')->default(false);
            $table->boolean('pay_status')->default(false);
            $table->string('payfast_pf_payment_id')->nullable();
            $table->json('payfast_raw_data')->nullable();
            // Refund fields
            $table->string('refund_method')->nullable();
            $table->string('refund_status')->default('not_refunded');
            $table->decimal('refund_gross', 10, 2)->default(0);
            $table->decimal('refund_fee', 10, 2)->default(0);
            $table->decimal('refund_net', 10, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_account_name')->nullable();
            $table->string('refund_bank_name')->nullable();
            $table->text('refund_account_number')->nullable();
            $table->string('refund_branch_code')->nullable();
            $table->string('refund_account_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_payment_orders');
    }
};
