<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('draw_settings')) {
            return;
        }

        Schema::create('draw_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draw_id');
            $table->unsignedBigInteger('draw_format_id')->nullable();
            $table->unsignedBigInteger('draw_type_id')->nullable();
            $table->unsignedInteger('boxes')->nullable();
            $table->unsignedInteger('playoff_size')->nullable();
            $table->unsignedInteger('num_sets')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_settings');
    }
};
