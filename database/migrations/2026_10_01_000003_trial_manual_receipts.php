<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('trial_payment_proofs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('order_id')->index(); $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('payer_id'); $table->string('path'); $table->string('mime_type', 100); $table->unsignedInteger('size');
            $table->string('status')->default('pending'); $table->unsignedBigInteger('reviewed_by_user_id')->nullable(); $table->timestamp('reviewed_at')->nullable(); $table->timestamps();
        });
        Schema::create('registration_manual_receipts', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('order_id')->unique(); $table->unsignedBigInteger('event_id')->index();
            $table->decimal('amount', 12, 2); $table->string('method', 20); $table->string('reference', 120);
            $table->unsignedBigInteger('verified_by_user_id'); $table->unsignedBigInteger('proof_id')->nullable(); $table->timestamp('paid_at'); $table->timestamps();
        });
    }
    public function down(): void {
        if (\Illuminate\Support\Facades\DB::table('registration_manual_receipts')->exists() || \Illuminate\Support\Facades\DB::table('trial_payment_proofs')->exists()) {
            throw new RuntimeException('Retain payment receipts and proof audit records before rollback.');
        }
        Schema::dropIfExists('registration_manual_receipts'); Schema::dropIfExists('trial_payment_proofs');
    }
};
