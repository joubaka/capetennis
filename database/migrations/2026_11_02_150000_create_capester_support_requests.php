<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capester_support_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->string('kind', 20);
            $table->text('capester_description');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('receiver_token_id')->nullable();
            $table->string('lease_token_hash', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'id'], 'capester_actor_queue');
            $table->index(['event_id', 'user_id', 'id'], 'capester_event_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capester_support_requests');
    }
};
