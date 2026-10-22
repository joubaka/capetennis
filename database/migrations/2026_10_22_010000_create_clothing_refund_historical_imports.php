<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clothing_refund_historical_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_system', 80);
            $table->string('source_refund_id', 100);
            $table->char('source_fingerprint', 64);
            $table->foreignId('clothing_refund_id')->constrained('clothing_refunds')->restrictOnDelete();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('clothing_order_id')->index();
            $table->json('source_snapshot');
            $table->unsignedBigInteger('imported_by');
            $table->string('imported_by_name');
            $table->timestamp('imported_at');
            $table->unique(['source_system', 'source_refund_id'], 'clothing_refund_import_source_unique');
            $table->unique('clothing_refund_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clothing_refund_historical_imports');
    }
};
