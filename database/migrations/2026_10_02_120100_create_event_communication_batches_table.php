<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_communication_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->uuid('token')->unique();
            $table->string('source_key')->nullable()->unique();
            $table->string('subject');
            $table->longText('body');
            $table->json('options');
            $table->json('recipients');
            $table->json('issues');
            $table->string('fingerprint', 64);
            $table->string('status')->default('preview');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_communication_batches');
    }
};
