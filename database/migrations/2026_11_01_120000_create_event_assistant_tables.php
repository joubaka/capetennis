<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['event_id', 'user_id', 'expires_at'], 'event_assistant_conversation_scope');
        });
        Schema::create('event_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('event_assistant_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('event_assistant_proposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained('event_assistant_conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 30);
            $table->json('payload');
            $table->string('fingerprint', 64);
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('applied_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'user_id']);
        });
        Schema::create('event_assistant_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->json('preferences');
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
        Schema::create('event_assistant_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('event_assistant_messages')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('rating', 20);
            $table->string('reason', 40)->nullable();
            $table->string('category', 40);
            $table->string('comment', 1000)->nullable();
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['event_assistant_feedback', 'event_assistant_preferences', 'event_assistant_proposals', 'event_assistant_messages', 'event_assistant_conversations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
