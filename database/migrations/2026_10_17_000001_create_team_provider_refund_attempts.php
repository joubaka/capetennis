<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_provider_refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('team_payment_orders')->restrictOnDelete();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('payer_id');
            $table->unsignedBigInteger('beneficiary_player_id');
            $table->string('pf_payment_id', 120);
            $table->string('fee_mode', 20);
            $table->string('review_fingerprint', 64);
            $table->timestamp('authorized_withdrawn_at');
            $table->timestamp('authorized_deadline');
            foreach (['gross', 'fee', 'net', 'amount', 'wallet_amount'] as $column) $table->decimal($column, 10, 2);
            $table->string('status', 20)->default('ready');
            $table->string('provider_reference', 120)->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('team_provider_refund_attempts')->exists()) {
            throw new RuntimeException('Retain team provider refund attempt history before rollback.');
        }
        Schema::dropIfExists('team_provider_refund_attempts');
    }
};
