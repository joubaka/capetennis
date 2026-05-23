<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flag_event_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('flag', 60)->index();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique(['event_id', 'flag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_event_overrides');
    }
};
