<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctr_event_certifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->unique();
            $table->boolean('certified')->default(false);
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
        });
        Schema::create('ctr_draw_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('actor_id');
            $table->string('fingerprint', 64);
            $table->json('proposal');
            $table->json('draw_ids')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctr_draw_plans');
        Schema::dropIfExists('ctr_event_certifications');
    }
};
