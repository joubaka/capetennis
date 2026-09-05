<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_convenors', function (Blueprint $table) {
            $table->foreignId('venue_id')
                ->nullable()
                ->after('user_id')
                ->constrained('venues')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_convenors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
        });
    }
};
