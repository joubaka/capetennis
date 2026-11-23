<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\{DB, Schema};

/** Private saved projections only. Callers retain current access and source checks. */
class PlayerHistoryProjectionStore
{
    public const VERSION = 1;
    public const MAX_PLAYERS = 10000;
    public const MAX_PLAYER_BYTES = 2 * 1024 * 1024;
    public const MAX_METADATA_BYTES = 20 * 1024 * 1024;

    public function ready(): bool
    {
        return Schema::hasTable('player_history_projection_builds') && Schema::hasTable('player_history_projections');
    }

    public function invalidate(): void
    {
        if ($this->ready()) DB::table('player_history_projection_builds')->where('slot', 'current')->delete();
    }

    /** Called by the offline history writer after its canonical validation. */
    public function replace(array $saved, array $playerIds): bool
    {
        if (!$this->ready()) return false;
        $ids = array_values(array_unique(array_filter($playerIds, fn ($id) => is_int($id) && $id > 0)));
        sort($ids);
        if (count($ids) > self::MAX_PLAYERS) throw new \OverflowException('Player history projection audience exceeds its bound.');
        $wanted = array_fill_keys($ids, true);
        $evidence = [];
        // Index once, rather than repeatedly scanning all evidence for each player.
        foreach ($saved['evidence'] as $row) {
            foreach ($row['players'] as $id) if (isset($wanted[$id])) $evidence[$id][] = $row;
        }
        $metadata = array_diff_key($saved, ['months' => true, 'evidence' => true]);
        $header = json_encode($metadata, JSON_THROW_ON_ERROR);
        if (strlen($header) > self::MAX_METADATA_BYTES) throw new \OverflowException('Player history metadata exceeds its bound.');
        $build = hash('sha256', self::VERSION.':'.json_encode([$saved, $ids], JSON_THROW_ON_ERROR));
        DB::transaction(function () use ($saved, $ids, $evidence, $header, $build) {
            // Serialize activation, retaining the previous coherent build on any failure.
            $current = DB::table('player_history_projection_builds')->where('slot', 'current')->lockForUpdate()->first();
            if ($current) {
                $previous = json_decode($current->metadata, true, flags: JSON_THROW_ON_ERROR);
                if ([$previous['as_of'], $previous['built_at']] > [$saved['as_of'], $saved['built_at']]) {
                    throw new \RuntimeException('An older player history cannot replace the current projection.');
                }
            }
            foreach ($ids as $id) {
                $months = [];
                foreach ($saved['months'] as $month) {
                    $cohorts = [];
                    foreach ($month['cohorts'] as $cohort => $ratings) $cohorts[$cohort] = isset($ratings[$id]) ? [$id => $ratings[$id]] : [];
                    $months[] = array_replace($month, ['cohorts' => $cohorts]);
                }
                $payload = json_encode(['player_id' => $id, 'months' => $months, 'evidence' => $evidence[$id] ?? []], JSON_THROW_ON_ERROR);
                if (strlen($payload) > self::MAX_PLAYER_BYTES) throw new \OverflowException('A player history exceeds its projection bound.');
                DB::table('player_history_projections')->updateOrInsert(['build_id' => $build, 'player_id' => $id],
                    ['payload' => $payload, 'checksum' => hash('sha256', $payload)]);
            }
            DB::table('player_history_projection_builds')->updateOrInsert(['slot' => 'current'],
                ['build_id' => $build, 'version' => self::VERSION, 'metadata' => $header, 'checksum' => hash('sha256', $header)]);
            DB::table('player_history_projections')->where('build_id', '!=', $build)->delete();
        }, 3);
        return true;
    }

    public function read(int $playerId): ?array
    {
        if ($playerId < 1) return null;
        return app(PlayerRatingReadScope::class)->remember('player-history-projection:'.$playerId, function () use ($playerId) {
            try {
                if (!$this->ready() || !app(PlayerRatingReadBudget::class)->ensure()) return null;
                $row = DB::table('player_history_projection_builds as build')
                    ->join('player_history_projections as player', 'player.build_id', '=', 'build.build_id')
                    ->where('build.slot', 'current')->where('build.version', self::VERSION)->where('player.player_id', $playerId)
                    ->whereRaw('LENGTH(player.payload) <= ?', [self::MAX_PLAYER_BYTES])
                    ->whereRaw('LENGTH(build.metadata) <= ?', [self::MAX_METADATA_BYTES])
                    ->first(['build.metadata', 'build.checksum as metadata_checksum', 'player.payload', 'player.checksum']);
                if (!$row || !hash_equals($row->metadata_checksum, hash('sha256', $row->metadata))
                    || !hash_equals($row->checksum, hash('sha256', $row->payload))) return null;
                $metadata = json_decode($row->metadata, true, 512, JSON_THROW_ON_ERROR);
                $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($metadata) || !is_array($payload) || ($payload['player_id'] ?? null) !== $playerId
                    || !is_array($payload['months'] ?? null) || !is_array($payload['evidence'] ?? null)) return null;
                if (array_intersect_key($metadata, ['months' => true, 'evidence' => true, 'player_id' => true])) return null;
                if (array_diff_key($metadata, array_flip(['version', 'policy_hash', 'source_fingerprint', 'as_of', 'built_at', 'publication_manifest']))) return null;
                if (array_diff_key($payload, array_flip(['player_id', 'months', 'evidence']))) return null;
                foreach ($payload['months'] as $month) {
                    foreach ($month['cohorts'] ?? [] as $ratings) {
                        foreach (array_keys($ratings) as $id) if ((int) $id !== $playerId) return null;
                    }
                }
                foreach ($payload['evidence'] as $item) if (!in_array($playerId, $item['players'] ?? [], true)) return null;
                return $metadata + ['months' => $payload['months'], 'evidence' => $payload['evidence']];
            } catch (\Throwable) { return null; }
        });
    }
}
