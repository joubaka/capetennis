<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('team_payment_orders', function (Blueprint $table) {
      $table->id();

      // Core relations
      $table->unsignedBigInteger('user_id');
      $table->unsignedBigInteger('team_id');
      $table->unsignedBigInteger('player_id');
      $table->unsignedBigInteger('event_id');

      // Amounts
      $table->decimal('total_amount', 10, 2)->default(0);
      $table->decimal('wallet_reserved', 10, 2)->default(0);
      $table->decimal('payfast_amount_due', 10, 2)->default(0);

      // Payment state
      $table->boolean('wallet_debited')->default(false);
      $table->boolean('payfast_paid')->default(false);
      $table->boolean('pay_status')->default(false);

      // PayFast
      $table->string('payfast_pf_payment_id')->nullable();
      $table->json('payfast_raw_data')->nullable();

      $table->timestamps();

      // Indexes
      $table->index('user_id');
      $table->index('team_id');
      $table->index('player_id');
      $table->index('event_id');

      // Prevent duplicate orders
      $table->unique(['team_id', 'player_id', 'event_id'], 'unique_team_player_event');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('team_payment_orders');
  }
};
