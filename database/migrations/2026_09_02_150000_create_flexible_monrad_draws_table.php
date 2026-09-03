<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Older installations/test schemas can have the settings table without this column.
        if (! Schema::hasColumn('draw_settings', 'draw_format_id')) {
            Schema::table('draw_settings', fn (Blueprint $table) => $table->unsignedBigInteger('draw_format_id')->nullable());
        }
        Schema::create('flexible_monrad_draws', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draw_id')->unique();
            $table->unsignedInteger('revision')->default(0);
            $table->json('draft');
            $table->json('graph')->nullable();
            $table->json('fixture_map')->nullable();
            $table->timestamps();
        });
        if (! DB::table('draw_formats')->where('name', 'Flexible Monrad')->exists()) {
            DB::table('draw_formats')->insert(['name' => 'Flexible Monrad']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flexible_monrad_draws');
        // Preserve the format record: an existing draw setting may reference it.
    }
};
