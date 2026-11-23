<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('pathway_level', 16)->nullable();
        });
        Schema::table('category_events', function (Blueprint $table) {
            $table->string('pathway_level', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('category_events', fn (Blueprint $table) => $table->dropColumn('pathway_level'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('pathway_level'));
    }
};
