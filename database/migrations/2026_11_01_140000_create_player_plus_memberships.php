<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_plus_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('request_token')->unique();
            $table->unsignedInteger('price_cents')->default(49900);
            $table->string('status')->default('pending')->index();
            $table->boolean('pay_status')->default(false);
            $table->boolean('payfast_paid')->default(false);
            $table->decimal('payfast_amount_due', 10, 2)->default(499);
            $table->decimal('wallet_reserved', 10, 2)->default(0);
            $table->boolean('wallet_debited')->default(false);
            $table->string('payfast_pf_payment_id', 120)->nullable()->unique();
            $table->timestamp('payfast_handed_off_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('term_starts_at')->nullable();
            $table->timestamp('term_ends_at')->nullable();
            $table->text('reconciliation_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('player_plus_memberships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id')->unique();
            $table->unsignedBigInteger('order_id')->unique();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_plus_memberships');
        Schema::dropIfExists('player_plus_orders');
    }
};
