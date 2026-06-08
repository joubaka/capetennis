<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('registration_pairs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('category_event_id');

            // CER links — nullable until each player pays/accepts
            $table->unsignedBigInteger('player1_cer_id')->nullable();
            $table->unsignedBigInteger('player2_cer_id')->nullable();

            // The player who initiated the pair
            $table->unsignedBigInteger('owner_user_id')->nullable();

            // Lifecycle state
            $table->string('status')->default('pending_partner');
            // Values: pending_partner | invited | active | incomplete | dissolved

            // Partner invitation
            $table->string('invite_token')->nullable()->unique();
            $table->string('invite_email')->nullable();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            // Payment model for this pair
            $table->string('payment_model')->nullable();
            // Values: full (player1 pays all) | split (each pays own)

            $table->timestamps();

            $table->index('registration_id');
            $table->index('category_event_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('registration_pairs');
    }
};
