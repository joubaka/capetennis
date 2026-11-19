<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_beneficiary_corrections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('entry_id')->unique();
            $table->unsignedBigInteger('original_player_id');
            $table->unsignedBigInteger('target_player_id');
            $table->unsignedBigInteger('source_invitation_id');
            $table->unsignedBigInteger('target_invitation_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('reason', 1000);
            $table->char('fingerprint', 64);
            $table->json('payment_snapshot');
            $table->json('invitation_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_beneficiary_corrections');
    }
};
