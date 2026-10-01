<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trial_programmes', fn (Blueprint $table) => $table->timestamp('withdrawal_deadline')->nullable());
        Schema::create('trial_provider_refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('pf_payment_id');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('dispatching');
            $table->unsignedBigInteger('requested_by');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('trial_provider_refund_attempts')->exists()) {
            throw new RuntimeException('Retain provider refund attempt history before rollback.');
        }
        Schema::dropIfExists('trial_provider_refund_attempts');
        Schema::table('trial_programmes', fn (Blueprint $table) => $table->dropColumn('withdrawal_deadline'));
    }
};
