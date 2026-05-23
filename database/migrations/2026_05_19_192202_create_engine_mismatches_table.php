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
        if (Schema::hasTable('engine_mismatches')) {
            return;
        }

        Schema::create('engine_mismatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draw_id')->nullable()->index();
            $table->string('operation_type', 50);
            $table->string('mismatch_type', 80);         // standings_mismatch | progression_mismatch | bye_mismatch | playoff_mapping | consolation | fixture_count | etc.
            $table->json('legacy_output')->nullable();
            $table->json('canonical_output')->nullable();
            $table->string('severity', 10)->default('medium'); // low | medium | high
            $table->boolean('resolved')->default(false);
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
        Schema::dropIfExists('engine_mismatches');
    }
};
