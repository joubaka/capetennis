<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_of_plays', 'gap_minutes')) {
            Schema::table('order_of_plays', fn (Blueprint $table) => $table->unsignedSmallInteger('gap_minutes')->default(0));
        }
    }

    public function down(): void
    {
        Schema::table('order_of_plays', fn (Blueprint $table) => $table->dropColumn('gap_minutes'));
    }
};
