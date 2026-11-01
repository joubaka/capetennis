<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_assistant_timing_observations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('draw_id');
            $table->string('fixture_kind', 12);
            $table->unsignedBigInteger('fixture_id');
            $table->unsignedBigInteger('verified_by');
            $table->dateTime('actual_start');
            $table->dateTime('actual_finish');
            $table->unsignedSmallInteger('actual_minutes');
            $table->string('format', 64);
            $table->string('age_group', 64);
            $table->boolean('verified')->default(true);
            $table->boolean('interrupted')->default(false);
            $table->boolean('retired')->default(false);
            $table->boolean('walkover')->default(false);
            $table->timestamps();
            $table->unique(['event_id', 'fixture_kind', 'fixture_id'], 'ea_timing_fixture');
            $table->index(['event_id', 'format', 'age_group'], 'ea_timing_cohort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_assistant_timing_observations');
    }
};
