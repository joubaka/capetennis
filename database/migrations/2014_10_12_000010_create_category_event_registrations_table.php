<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('category_event_registrations')) {
            return;
        }

        Schema::create('category_event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();

            // Payment
            $table->string('pf_transaction_id')->nullable();
            $table->unsignedTinyInteger('payment_status_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();

            // Withdrawal
            $table->string('status')->default('active');
            $table->timestamp('withdrawn_at')->nullable();
            $table->unsignedBigInteger('withdrawn_by')->nullable();
            $table->string('withdrawal_reason')->nullable();

            // Refund core
            $table->string('refund_method')->nullable();
            $table->string('refund_status')->default('not_refunded');
            $table->decimal('refund_gross', 10, 2)->default(0);
            $table->decimal('refund_fee', 10, 2)->default(0);
            $table->decimal('refund_net', 10, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();

            // Bank refund details
            $table->string('refund_account_name')->nullable();
            $table->string('refund_bank_name')->nullable();
            $table->text('refund_account_number')->nullable();
            $table->string('refund_branch_code')->nullable();
            $table->string('refund_account_type')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_event_registrations');
    }
};
