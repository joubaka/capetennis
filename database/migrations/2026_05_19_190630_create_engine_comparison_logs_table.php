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
        if (Schema::hasTable('engine_comparison_logs')) {
            return;
        }

        Schema::create('engine_comparison_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 60)->index();
            $table->unsignedBigInteger('draw_id')->nullable()->index();
            $table->string('mismatch_type', 80);
            $table->string('engine_mode', 20)->default('hybrid');
            $table->json('legacy_result')->nullable();
            $table->json('canonical_result')->nullable();
            $table->boolean('was_fallback')->default(false);
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
        Schema::dropIfExists('engine_comparison_logs');
    }
};
