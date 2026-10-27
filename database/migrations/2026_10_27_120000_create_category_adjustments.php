<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entry_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('source_category_id');
            $table->unsignedBigInteger('target_category_id');
            $table->unsignedBigInteger('original_order_item_id')->nullable();
            $table->uuid('request_token')->unique();
            $table->string('status', 40)->default('pending')->index();
            $table->json('payment_before');
            $table->json('payment_after')->nullable();
            $table->decimal('target_fee', 12, 2);
            $table->decimal('difference', 12, 2);
            $table->decimal('wallet_credit', 12, 2)->default(0);
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->decimal('wallet_reserved', 12, 2)->default(0);
            $table->boolean('wallet_debited')->default(false);
            $table->boolean('pay_status')->default(false);
            $table->boolean('payfast_paid')->default(false);
            $table->decimal('payfast_amount_due', 12, 2)->default(0);
            $table->string('payfast_pf_payment_id', 120)->nullable()->unique();
            $table->string('payment_method')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('payfast_handed_off_at')->nullable();
            $table->timestamp('applied_at')->nullable()->index();
            $table->string('reconciliation_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_adjustments');
    }
};
