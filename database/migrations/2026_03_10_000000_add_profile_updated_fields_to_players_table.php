<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->timestamp('profile_updated_at')->nullable()->after('coach');
            $table->boolean('profile_complete')->default(false)->after('profile_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['profile_updated_at', 'profile_complete']);
        });
    }
};
