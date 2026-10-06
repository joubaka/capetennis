<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_age_group_venue_defaults', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedSmallInteger('age');
            $table->string('gender', 10);
            $table->json('venues');
            $table->timestamps();
            $table->unique(['event_id', 'age', 'gender'], 'event_age_gender_venue_default_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_age_group_venue_defaults');
    }
};
