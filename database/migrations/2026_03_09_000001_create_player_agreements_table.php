<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_agreements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('agreement_id');

            $table->enum('accepted_by_type', ['player', 'guardian']);

            // Guardian fields (nullable if player is adult)
            $table->string('guardian_name')->nullable();
            $table->string('guardian_email')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_relationship')->nullable();

            $table->timestamp('accepted_at');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Legal snapshot of the agreement content at time of acceptance
            $table->longText('content_snapshot')->nullable();

            $table->timestamps();

            $table->index(['player_id', 'agreement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_agreements');
    }
};
