<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('convenor_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->string('payment_method')->default('bank_transfer'); // bank_transfer, cash, eft, other
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable(); // super-admin user_id
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('convenor_id')->references('id')->on('event_convenors')->onDelete('set null');
            $table->foreign('paid_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payouts');
    }
};
