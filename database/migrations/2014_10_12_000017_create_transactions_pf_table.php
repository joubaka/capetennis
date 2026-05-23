<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transactions_pf')) {
            return;
        }

        Schema::create('transactions_pf', function (Blueprint $table) {
            $table->id();
            $table->string('pf_payment_id')->nullable()->index();
            $table->string('transaction_type')->nullable()->index();
            $table->decimal('amount_gross', 10, 2)->nullable();
            $table->decimal('amount_fee', 10, 2)->nullable();
            $table->decimal('amount_net', 10, 2)->nullable();
            $table->string('payment_status')->nullable();
            // Custom int fields (PayFast ITN fields)
            $table->unsignedBigInteger('custom_int1')->nullable(); // category_event_id
            $table->unsignedBigInteger('custom_int2')->nullable(); // player_id
            $table->unsignedBigInteger('custom_int3')->nullable(); // event_id
            $table->unsignedBigInteger('custom_int4')->nullable(); // user_id
            $table->unsignedBigInteger('custom_int5')->nullable(); // order_id
            // Custom string fields
            $table->string('custom_str1')->nullable();
            $table->string('custom_str2')->nullable();
            $table->string('custom_str3')->nullable();
            $table->string('custom_str4')->nullable();
            // Email
            $table->string('email_address')->nullable();
            // Resolved FK shortcuts
            $table->unsignedBigInteger('category_event_id')->nullable()->index();
            $table->unsignedBigInteger('player_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions_pf');
    }
};
