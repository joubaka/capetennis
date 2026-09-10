<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_selection_invitations', function (Blueprint $table): void {
            $table->dropUnique('team_selection_import_player_unique');
            $table->unique(
                ['import_id', 'team_id', 'player_id'],
                'team_selection_import_team_player_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('team_selection_invitations', function (Blueprint $table): void {
            $table->dropUnique('team_selection_import_team_player_unique');
            $table->unique(['import_id', 'player_id'], 'team_selection_import_player_unique');
        });
    }
};
