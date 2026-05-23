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
        Schema::create('pilot_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draw_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('engine_mode', 20)->default('hybrid');
            $table->string('category', 40);
            $table->text('description');
            $table->json('reproduction_steps')->nullable();
            $table->string('reporter_email', 191)->nullable();
            $table->string('status', 20)->default('open');
            $table->json('attachments')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
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
        Schema::dropIfExists('pilot_feedback');
    }
};
