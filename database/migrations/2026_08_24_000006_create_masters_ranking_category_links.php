<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('masters_ranking_category_links')) return;
        Schema::create('masters_ranking_category_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('ranking_list_id')->index();
            $table->unsignedBigInteger('category_event_id')->nullable()->index();
            $table->boolean('enabled')->default(true);
            $table->unsignedTinyInteger('top_x')->default(8);
            $table->string('category_name', 191)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'ranking_list_id'], 'masters_event_ranking_list_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masters_ranking_category_links');
    }
};
