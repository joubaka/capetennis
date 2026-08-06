<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_event_formats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->string('name', 191);
            $table->unsignedTinyInteger('min_roster_size')->default(1);
            $table->unsignedTinyInteger('max_roster_size')->default(12);
            $table->boolean('allow_player_reuse')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
        });

        Schema::create('team_event_format_rubbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('format_id');
            $table->unsignedTinyInteger('sequence');
            $table->string('rubber_code', 30); // singles|reverse_singles|doubles|mixed_doubles
            $table->string('name', 100);
            $table->string('gender_rule', 20)->nullable(); // male|female|mixed|null
            $table->unsignedTinyInteger('player_count_per_team')->default(1);
            $table->unsignedTinyInteger('singles_position')->nullable();
            $table->unsignedTinyInteger('reverse_from_position')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['format_id', 'sequence']);
            $table->foreign('format_id')
                  ->references('id')->on('team_event_formats')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_event_format_rubbers');
        Schema::dropIfExists('team_event_formats');
    }
};
