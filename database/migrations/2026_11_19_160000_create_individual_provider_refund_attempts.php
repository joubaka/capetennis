<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_provider_refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entry_id')->unique();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('receipt_id')->unique();
            $table->string('pf_payment_id', 120)->unique();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20);
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('individual_provider_refund_attempts')->exists()) {
            throw new RuntimeException('Retain provider refund attempt evidence before rollback.');
        }
        Schema::dropIfExists('individual_provider_refund_attempts');
    }
};
