<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            return;
        }

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('information')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('email')->nullable();
            $table->string('organizer')->nullable();
            $table->decimal('entryFee', 8, 2)->nullable();
            $table->integer('deadline')->nullable();
            $table->dateTime('withdrawal_deadline')->nullable();
            $table->unsignedBigInteger('eventType')->nullable();
            $table->string('status')->default('active');
            $table->text('venue_notes')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('published')->default(false);
            $table->boolean('signUp')->default(false);
            $table->unsignedBigInteger('series_id')->nullable();
            $table->unsignedBigInteger('admin')->nullable();
            $table->decimal('budget_cap', 10, 2)->nullable();
            $table->integer('target_entries')->nullable();
            $table->decimal('target_income', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
