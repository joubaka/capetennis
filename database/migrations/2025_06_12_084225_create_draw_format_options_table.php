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
        Schema::create('draw_format_options', function (Blueprint $table) {
                  $table->id();
            $table->foreignId('draw_format_id');
            $table->boolean('supports_boxes')->default(false);
            $table->boolean('supports_playoff')->default(false);
            $table->unsignedTinyInteger('default_boxes')->nullable();
            $table->unsignedTinyInteger('default_playoff_size')->nullable();
            $table->unsignedTinyInteger('default_num_sets')->nullable();
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
        Schema::dropIfExists('draw_format_options');
    }
};
