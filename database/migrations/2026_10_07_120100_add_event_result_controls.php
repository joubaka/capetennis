<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('result_notifications_enabled')->default(false);
            $table->boolean('result_auto_refresh_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['result_notifications_enabled', 'result_auto_refresh_enabled']);
        });
    }
};
