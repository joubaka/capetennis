<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selection_preview_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
        Schema::create('selection_preview_share_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->uuid('request_key');
            $table->string('fingerprint', 64);
            $table->json('recipient_ids');
            $table->string('event_name');
            $table->unsignedTinyInteger('age')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'actor_id', 'request_key'], 'preview_share_request_unique');
        });
        Schema::create('selection_preview_access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained('selection_preview_grants')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 20);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('selection_preview_access_events')->exists()) {
            throw new RuntimeException('Cannot remove recorded private preview access history.');
        }
        Schema::dropIfExists('selection_preview_access_events');
        Schema::dropIfExists('selection_preview_share_batches');
        Schema::dropIfExists('selection_preview_grants');
    }
};
