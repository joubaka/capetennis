<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('match_result_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->string('fixture_type', 16);
            $table->unsignedBigInteger('fixture_id');
            $table->unsignedInteger('revision');
            $table->string('snapshot_hash', 64);
            $table->json('snapshot');
            $table->string('recipient');
            $table->string('status')->default('pending');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();
            $table->unique(['fixture_type', 'fixture_id', 'revision', 'recipient'], 'match_result_recipient_revision');
            $table->index(['fixture_type', 'fixture_id', 'id'], 'match_result_latest');
        });
    }

    public function down(): void { Schema::dropIfExists('match_result_notifications'); }
};
