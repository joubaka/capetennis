<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_camp_plans', function (Blueprint $table) {
            $table->id();
            $table->string('camp_key')->unique();
            $table->unsignedBigInteger('event_id')->nullable()->unique();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->uuid('revision');
            $table->boolean('dates_provisional')->default(true);
            $table->decimal('entry_fee', 10, 2)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->json('candidates');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_camp_plans');
    }
};
