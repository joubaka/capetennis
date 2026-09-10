<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_selection_imports', function (Blueprint $table): void {
            $table->json('communication_snapshot')->nullable()->after('communication_hash');
        });
    }

    public function down(): void
    {
        Schema::table('team_selection_imports', function (Blueprint $table): void {
            $table->dropColumn('communication_snapshot');
        });
    }
};
