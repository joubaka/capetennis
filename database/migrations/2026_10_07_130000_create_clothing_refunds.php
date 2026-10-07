<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clothing_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clothing_order_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('payer_id');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->uuid('request_token')->unique();
            $table->char('request_fingerprint', 64);
            $table->string('refund_method', 20);
            $table->string('refund_status', 20)->default('pending');
            $table->decimal('refund_gross', 10, 2);
            $table->decimal('refund_fee', 10, 2)->default(0);
            $table->decimal('refund_net', 10, 2);
            $table->string('reason', 500);
            $table->string('external_reference', 120)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
        Schema::create('clothing_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clothing_refund_id')->constrained('clothing_refunds')->restrictOnDelete();
            $table->unsignedBigInteger('clothing_order_item_id')->index();
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 10, 2);
            $table->unique(['clothing_refund_id', 'clothing_order_item_id'], 'clothing_refund_line_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clothing_refund_items');
        Schema::dropIfExists('clothing_refunds');
    }
};
