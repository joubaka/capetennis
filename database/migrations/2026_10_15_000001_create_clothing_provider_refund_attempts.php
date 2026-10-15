<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clothing_provider_refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clothing_refund_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('clothing_order_id')->index();
            $table->string('pf_payment_id', 120);
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('requested_by');
            $table->string('status', 20)->default('dispatching');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clothing_provider_refund_attempts');
    }
};
