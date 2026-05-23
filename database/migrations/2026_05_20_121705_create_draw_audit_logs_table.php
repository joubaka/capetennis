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
        if (Schema::hasTable('draw_audit_logs')) {
            return;
        }

        Schema::create('draw_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draw_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 64);       // e.g. score_saved, score_deleted, lock_toggled
            $table->unsignedBigInteger('fixture_id')->nullable();
            $table->json('payload')->nullable(); // extra context
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
        Schema::dropIfExists('draw_audit_logs');
    }
};
