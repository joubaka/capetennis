<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('no_profile_team_players', function (Blueprint $table): void {
            if (! Schema::hasColumn('no_profile_team_players', 'date_of_birth')) $table->date('date_of_birth')->nullable()->after('surname');
            if (! Schema::hasColumn('no_profile_team_players', 'email')) $table->string('email')->nullable()->after('date_of_birth');
            if (! Schema::hasColumn('no_profile_team_players', 'cell_nr')) $table->string('cell_nr', 50)->nullable()->after('email');
            if (! Schema::hasColumn('no_profile_team_players', 'claimed_by_user_id')) $table->unsignedBigInteger('claimed_by_user_id')->nullable()->after('player_profile');
            if (! Schema::hasColumn('no_profile_team_players', 'claimed_at')) $table->timestamp('claimed_at')->nullable()->after('claimed_by_user_id');
        });

        if (! Schema::hasIndex('no_profile_team_players', 'no_profile_team_players_team_rank_unique')) {
            $duplicates = DB::table('no_profile_team_players')->select('team_id', 'rank')
                ->groupBy('team_id', 'rank')->havingRaw('COUNT(*) > 1')->exists();
            if ($duplicates) {
                throw new RuntimeException('Duplicate external roster ranks must be resolved before adding the team/rank uniqueness constraint.');
            }
            Schema::table('no_profile_team_players', function (Blueprint $table): void {
                $table->unique(['team_id', 'rank'], 'no_profile_team_players_team_rank_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('no_profile_team_players', 'no_profile_team_players_team_rank_unique')) {
            Schema::table('no_profile_team_players', fn (Blueprint $table) => $table->dropUnique('no_profile_team_players_team_rank_unique'));
        }
        Schema::table('no_profile_team_players', function (Blueprint $table): void {
            foreach (['claimed_at', 'claimed_by_user_id', 'cell_nr', 'email', 'date_of_birth'] as $column) {
                if (Schema::hasColumn('no_profile_team_players', $column)) $table->dropColumn($column);
            }
        });
    }
};
