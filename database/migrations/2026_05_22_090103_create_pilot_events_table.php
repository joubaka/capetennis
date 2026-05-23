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
        Schema::create('pilot_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('scenario');          // rr | playoff | consolation | payment
            $table->string('engine_mode', 20)->default('hybrid');
            $table->unsignedSmallInteger('player_count')->default(0);
            $table->unsignedSmallInteger('draw_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->unsignedInteger('fallback_count')->default(0);
            $table->unsignedInteger('rollback_count')->default(0);
            $table->unsignedInteger('score_delete_count')->default(0);
            $table->unsignedInteger('canonical_exception_count')->default(0);
            $table->json('notes')->nullable();
            $table->string('status', 20)->default('active'); // active | complete | failed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pilot_events');
    }
};
