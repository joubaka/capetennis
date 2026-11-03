<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('category_events', fn (Blueprint $table) => $table->unsignedInteger('entry_limit')->nullable());
    }

    public function down(): void
    {
        Schema::table('category_events', fn (Blueprint $table) => $table->dropColumn('entry_limit'));
    }
};
