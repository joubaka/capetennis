<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_selection_invitations', function (Blueprint $table): void {
            $table->string('clothing_decision', 30)->nullable()->after('status');
            $table->timestamp('clothing_decided_at')->nullable()->after('clothing_decision');
        });
    }

    public function down(): void
    {
        Schema::table('team_selection_invitations', function (Blueprint $table): void {
            $table->dropColumn(['clothing_decision', 'clothing_decided_at']);
        });
    }
};
