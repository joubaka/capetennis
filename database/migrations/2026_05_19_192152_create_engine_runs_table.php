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
        if (Schema::hasTable('engine_runs')) {
            return;
        }

        Schema::create('engine_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draw_id')->nullable()->index();
            $table->string('engine_mode', 20);           // legacy | hybrid | canonical
            $table->string('operation_type', 50);        // rr_generation | playoff_generation | standings | progression | rollback | bye_advancement
            $table->boolean('legacy_success')->nullable();
            $table->boolean('canonical_success')->nullable();
            $table->boolean('mismatch_detected')->default(false);
            $table->boolean('fallback_used')->default(false);
            $table->unsignedSmallInteger('mismatch_count')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('exception')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('engine_runs');
    }
};
