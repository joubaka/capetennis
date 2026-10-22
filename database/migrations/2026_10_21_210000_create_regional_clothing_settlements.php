<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regional_clothing_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->foreignId('event_region_id')->constrained('event_regions')->restrictOnDelete();
            $table->unsignedBigInteger('region_id')->index();
            $table->string('region_name');
            $table->string('kind', 20);
            $table->decimal('amount', 12, 2);
            $table->string('recipient_name', 150);
            $table->dateTime('occurred_at');
            $table->string('reference', 150);
            $table->text('evidence');
            $table->decimal('funding_shortfall', 12, 2)->default(0);
            $table->string('overpayment_reason', 1000)->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->string('recorded_by_name');
            $table->uuid('request_token');
            $table->char('request_fingerprint', 64);
            $table->unsignedBigInteger('counterparty_event_region_id')->nullable();
            $table->boolean('offset_agreement_confirmed')->default(false);
            $table->timestamps();
            $table->unique(['request_token', 'kind'], 'clothing_settlement_request_leg_unique');
            $table->index(['event_id', 'event_region_id', 'occurred_at'], 'clothing_settlement_region_date');
        });
        Schema::table('clothing_refunds', function (Blueprint $table) {
            $table->string('requested_by_name')->nullable();
            $table->string('completed_by_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regional_clothing_settlements');
        Schema::table('clothing_refunds', function (Blueprint $table) {
            $table->dropColumn(['requested_by_name', 'completed_by_name']);
        });
    }
};
