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
        Schema::create('pilot_draw_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draw_id')->unique()->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('approved_by_email', 191)->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('status', 20)->default('approved');
            $table->text('notes')->nullable();
            $table->integer('player_count')->default(0);
            $table->boolean('is_rr')->default(true);
            $table->boolean('has_consolation')->default(false);
            $table->boolean('has_feed_in')->default(false);
            $table->boolean('is_national')->default(false);
            $table->string('engine_mode_before', 20)->default('hybrid');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
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
        Schema::dropIfExists('pilot_draw_approvals');
    }
};
