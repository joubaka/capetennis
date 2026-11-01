<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_plus_orders', function (Blueprint $table) {
            $table->string('plan', 16)->default('annual');
            $table->unsignedBigInteger('subscription_order_id')->nullable()->index();
            $table->timestamp('recurring_consented_at')->nullable();
        });
        Schema::create('player_plus_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->text('provider_token');
            $table->char('provider_token_hash', 64)->unique();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('billing_anchor_at');
            $table->unsignedInteger('paid_cycles')->default(1);
            $table->timestamp('next_billing_at');
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
        Schema::create('player_plus_subscription_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id')->index();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('provider_payment_id', 120)->unique();
            $table->unsignedInteger('cycle_number')->nullable();
            $table->unique(['subscription_id', 'cycle_number'], 'plus_subscription_cycle_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_plus_subscription_charges');
        Schema::dropIfExists('player_plus_subscriptions');
        Schema::table('player_plus_orders', function (Blueprint $table) {
            $table->dropColumn(['plan', 'subscription_order_id', 'recurring_consented_at']);
        });
    }
};
