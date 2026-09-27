<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_payment_recoveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_order_id')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('amount_due', 10, 2);
            $table->string('status', 30)->default('prepared')->index();
            $table->json('before_state');
            $table->char('before_state_checksum', 64);
            $table->char('preview_state_hash', 64);
            $table->json('mail_snapshot');
            $table->char('mail_snapshot_checksum', 64);
            $table->timestamp('link_expires_at');
            $table->unsignedBigInteger('prepared_by');
            $table->timestamp('prepared_at');
            $table->timestamp('mail_queued_at')->nullable();
            $table->unsignedBigInteger('mail_queued_by')->nullable();
            $table->char('mail_confirmation_hash', 64)->nullable();
            $table->timestamp('mail_authorized_at')->nullable();
            $table->uuid('mail_attempt_token')->nullable()->unique();
            $table->timestamp('mail_sending_at')->nullable();
            $table->timestamp('mail_sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_payment_recoveries');
    }
};
