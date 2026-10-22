<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_expenses', function (Blueprint $table) {
            $table->uuid('batch_key')->nullable();
            $table->unsignedInteger('venue_id')->nullable();
            $table->unique(['event_id', 'batch_key']);
        });
    }

    public function down(): void
    {
        Schema::table('event_expenses', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'batch_key']);
            $table->dropColumn(['batch_key', 'venue_id']);
        });
    }
};
