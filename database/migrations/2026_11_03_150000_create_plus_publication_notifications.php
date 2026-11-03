<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plus_publication_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('draw_id')->unique();
            $table->unsignedInteger('generation')->default(0);
            $table->json('snapshot');
            $table->json('changes');
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamps();
        });
        Schema::create('plus_publication_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('state_id')->index();
            $table->unsignedInteger('generation');
            $table->unsignedBigInteger('player_id');
            $table->string('recipient');
            $table->json('changes');
            $table->string('deduplication_key', 64)->unique();
            $table->string('status')->default('pending')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plus_publication_deliveries');
        Schema::dropIfExists('plus_publication_states');
    }
};
