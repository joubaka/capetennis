<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Player merges rely on database rollback. Legacy MyISAM tables ignore transactions,
     * so every table the merge service can mutate must use InnoDB first.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->tables() as $table) {
            if (Schema::hasTable($table)) {
                DB::statement('ALTER TABLE `'.str_replace('`', '``', $table).'` ENGINE=InnoDB');
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: reverting to MyISAM would remove transaction safety.
    }

    /** @return array<int, string> */
    private function tables(): array
    {
        return [
            'players',
            'user_players',
            'registration_order_items',
            'player_registrations',
            'team_players',
            'team_payment_orders',
            'transactions_pf',
            'player_subscriptions',
            'positions',
            'ranking_scores',
            'ranking_score_legs',
            'rankings',
            'series_rankings',
            'practices',
            'exercises',
            'invatations',
            'leaderboards',
            'goals',
            'clothing_orders',
            'player_violations',
            'player_suspensions',
            'event_nominations',
            'team_fixture_players',
            'fixture_players',
            'team_fixture_results',
            'player_merge_audits',
            'player_duplicate_decisions',
            'activity_log',
        ];
    }
};
