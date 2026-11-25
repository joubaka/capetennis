<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->mediumText('information')->nullable()->change());
        Schema::table('announcements', fn (Blueprint $table) => $table->mediumText('message')->change());
    }

    public function down(): void
    {
        // Intentionally retain the wider columns: shrinking can truncate saved images.
    }
};
