<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('players', 'profile_updated_at')) {
            Schema::table('players', function (Blueprint $table) {
                $table->timestamp('profile_updated_at')->nullable();
                $table->boolean('profile_complete')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['profile_updated_at', 'profile_complete']);
        });
    }
};
