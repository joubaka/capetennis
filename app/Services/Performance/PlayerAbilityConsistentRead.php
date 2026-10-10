<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\DB;

/** A bounded, nonlocking source read transaction, finished before materialization. */
class PlayerAbilityConsistentRead
{
    private const SOURCE_TABLES = ['events', 'draws', 'team_ties', 'teams', 'team_players', 'no_profile_team_players',
        'team_fixture_players', 'team_fixtures', 'event_regions', 'team_regions', 'eventtypes', 'category_events',
        'categories', 'draw_settings', 'fixtures', 'category_event_registrations', 'player_registrations',
        'fixture_results', 'team_fixture_results', 'category_results', 'players', 'order_of_plays', 'registrations'];

    public function run(callable $read): mixed
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $level = $connection->transactionLevel();
        $queryOnly = null;
        if ($driver === 'mysql') {
            if ($level !== 0) { throw new \RuntimeException('Consistent read requires an independent transaction.'); }
            $tables = $connection->table('information_schema.tables')->where('table_schema', $connection->getDatabaseName())
                ->whereIn('table_name', array_map(fn ($table) => $connection->getTablePrefix().$table, [...self::SOURCE_TABLES, 'player_ability_refresh_state']))
                ->selectRaw('TABLE_NAME as table_name, ENGINE as engine')->get();
            $required = $tables->reject(fn ($table) => $table->table_name === $connection->getTablePrefix().'player_ability_refresh_state');
            if ($required->count() !== count(self::SOURCE_TABLES) || $tables->contains(fn ($table) => strcasecmp($table->engine ?? '', 'InnoDB') !== 0)) {
                throw new \RuntimeException('Consistent read requires transactional source tables.');
            }
            // These apply to the next transaction only, not session/global policy.
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $connection->statement('SET TRANSACTION READ ONLY');
        } elseif ($driver === 'sqlite') {
            if ($level !== 0 && !app()->environment('testing')) { throw new \RuntimeException('Consistent read requires an independent transaction.'); }
            $queryOnly = (int) $connection->selectOne('PRAGMA query_only')->query_only;
            $connection->statement('PRAGMA query_only = ON');
        } else {
            throw new \RuntimeException('Unsupported consistent-read driver.');
        }
        try {
            $connection->beginTransaction();
            return $read();
        } finally {
            try {
                if ($connection->transactionLevel() > $level) { $connection->rollBack($level); }
            } finally {
                if ($queryOnly !== null) { $connection->statement('PRAGMA query_only = '.($queryOnly ? 'ON' : 'OFF')); }
            }
        }
    }
}
