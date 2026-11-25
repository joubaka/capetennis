<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_payout_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->unique();
            $table->unsignedBigInteger('completed_by');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('event_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('requested_by');
            $table->string('kind', 20);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->uuid('request_token')->unique();
            $table->char('fingerprint', 64);
            $table->json('snapshot');
            $table->text('reason');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('event_payout_requests');
        Schema::dropIfExists('event_payout_completions');
    }
};
