<?php

namespace App\Services\Performance;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\{DB, Log, Schema};

/** Durable work marker only; scoring requests never calculate ability. */
class PlayerAbilityRefreshState
{
    private array $available = [];
    private const SOURCES = ['events', 'draws', 'team_ties', 'teams', 'team_players', 'no_profile_team_players',
        'team_fixture_players', 'team_fixtures', 'event_regions', 'team_regions',
        'eventtypes', 'category_events', 'categories', 'fixtures',
        'category_event_registrations', 'player_registrations', 'category_results',
        'fixture_results', 'players', 'draw_settings', 'order_of_plays', 'team_fixture_results'];

    public function observe(QueryExecuted $query): void
    {
        if (!preg_match('/^\s*(?:insert(?:\s+ignore)?\s+into|replace\s+into|update|delete\s+from)\s+[`"]?([A-Za-z0-9_]+)/i', $query->sql, $match)
            || !in_array(strtolower($match[1]), self::SOURCES, true)) {
            return;
        }
        try {
            $connection = $query->connectionName;
            if (!isset($this->available[$connection])) {
                if (!$query->connection->getSchemaBuilder()->hasTable('player_ability_refresh_state')) { return; }
                $this->available[$connection] = true;
            }
            $mark = function () use ($query): void {
                try {
                    // UPDATE only: preserve LAST_INSERT_ID for Eloquent creates.
                    $query->connection->table('player_ability_refresh_state')->where('id', 1)
                        ->update(['generation' => DB::raw('generation + 1'), 'changed_at' => now()]);
                } catch (\Throwable $error) {
                    Log::warning('Ability refresh marker unavailable.', ['exception_type' => get_class($error)]);
                }
            };
            // Never hold a shared work-marker lock inside score transactions.
            // Rollback discards the callback. Fingerprinting recovers crash gaps.
            if ($query->connection->transactionLevel() > 0) { $query->connection->afterCommit($mark); }
            else { $mark(); }
        } catch (\Throwable $error) {
            // The minute refresh also fingerprints sources, so marker failures
            // cannot prevent score entry or silently lose the eventual refresh.
            Log::warning('Ability refresh marker unavailable.', ['exception_type' => get_class($error)]);
        }
    }

    public function generation(): ?int
    {
        return Schema::hasTable('player_ability_refresh_state')
            ? DB::table('player_ability_refresh_state')->where('id', 1)->value('generation') : null;
    }

    public function completed(int $generation): void
    {
        DB::table('player_ability_refresh_state')->where('id', 1)->update([
            'completed_generation' => $generation, 'last_failed_at' => null,
        ]);
    }

    public function checkDue(): bool
    {
        if (!Schema::hasTable('player_ability_refresh_state')) { return true; }
        $state = DB::table('player_ability_refresh_state')->where('id', 1)->first();
        if ($state?->last_failed_at && \Carbon\CarbonImmutable::parse($state->last_failed_at)->greaterThan(now()->subMinutes(5))) { return false; }
        return $state && ($state->generation > $state->completed_generation || !$state->last_checked_at
            || \Carbon\CarbonImmutable::parse($state->last_checked_at)->lessThanOrEqualTo(now()->subMinutes(5)));
    }

    public function checked(): void
    {
        if (Schema::hasTable('player_ability_refresh_state')) {
            DB::table('player_ability_refresh_state')->where('id', 1)->update(['last_checked_at' => now()]);
        }
    }

    public function failed(): void
    {
        if (Schema::hasTable('player_ability_refresh_state')) {
            DB::table('player_ability_refresh_state')->where('id', 1)->update(['last_failed_at' => now()]);
        }
    }

    /** Cheap private polling metadata; no calculation or source scan. */
    public function status(): array
    {
        $state = Schema::hasTable('player_ability_refresh_state') ? DB::table('player_ability_refresh_state')->where('id', 1)->first() : null;
        // The request-scoped store supplies BOTH badge data and its revision.
        // A concurrent replacement cannot label old badges with a new revision.
        $snapshot = app(PlayerAbilitySnapshotStore::class)->current();
        $pending = !$state || $state->generation > $state->completed_generation
            || $snapshot['reason'] || $snapshot['snapshot_stale'];
        return ['version' => hash('sha256', json_encode([$snapshot['snapshot_version'] ?? null, $snapshot['reason'], $state?->generation], JSON_THROW_ON_ERROR)),
            'snapshot_version' => $snapshot['snapshot_version'] ?? null,
            'last_updated' => $snapshot['built_at'] === 'Not yet updated' ? null : $snapshot['built_at'],
            'pending' => (bool) $pending, 'failed' => (bool) $state?->last_failed_at];
    }
}
