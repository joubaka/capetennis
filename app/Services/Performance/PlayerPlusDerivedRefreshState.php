<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\File;

/** Private bounded operational cursor. It does not grant access or change financial records. */
class PlayerPlusDerivedRefreshState
{
    public const VERSION = 1;
    public const MAX_BYTES = 16384;

    public function path(): string { return storage_path('app/private/player-plus-derived-refresh.json'); }

    public function read(): ?array
    {
        try {
            if (!File::exists($this->path()) || File::size($this->path()) > self::MAX_BYTES) return null;
            $state = json_decode(File::get($this->path()), true, 16, JSON_THROW_ON_ERROR);
            return $this->valid($state) ? $state : null;
        } catch (\Throwable) { return null; }
    }

    public function write(array $state): void
    {
        $state = array_intersect_key($state, array_flip(['version', 'cycle_date', 'status', 'stage', 'context', 'cursor', 'start_cursor', 'wrapped', 'player_id',
            'processed', 'progress_failures', 'event_failures', 'ability_attempts', 'history_attempts', 'reference', 'reason', 'started_at', 'updated_at']));
        if (!$this->valid($state)) throw new \RuntimeException('Invalid derived-refresh cursor.');
        $json = json_encode($state, JSON_THROW_ON_ERROR);
        if (strlen($json) > self::MAX_BYTES) throw new \OverflowException('Derived-refresh cursor exceeds its private limit.');
        File::ensureDirectoryExists(dirname($this->path())); File::replace($this->path(), $json, 0600);
    }

    private function valid(mixed $state): bool
    {
        if (!is_array($state) || ($state['version'] ?? null) !== self::VERSION || !is_string($state['cycle_date'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $state['cycle_date'])
            || !in_array($state['status'] ?? null, ['pending', 'complete', 'complete_with_errors', 'failed', 'context_changed'], true)
            || !in_array($state['stage'] ?? null, ['ability', 'history', 'progress', 'events', 'done'], true) || !is_bool($state['wrapped'] ?? null)
            || !array_key_exists('player_id', $state) || ($state['player_id'] !== null && (!is_int($state['player_id']) || $state['player_id'] < 1))
            || !is_string($state['reference'] ?? null) || !\Illuminate\Support\Str::isUuid($state['reference'])
            || !is_string($state['started_at'] ?? null) || !is_string($state['updated_at'] ?? null)
            || !array_key_exists('reason', $state) || ($state['reason'] !== null && !in_array($state['reason'], ['ability_failed', 'history_failed', 'source_context_changed', 'base_context_unavailable'], true))) return false;
        foreach (['cursor', 'start_cursor', 'processed', 'progress_failures', 'event_failures'] as $key) if (!is_int($state[$key] ?? null) || $state[$key] < 0) return false;
        foreach (['ability_attempts', 'history_attempts'] as $key) if (!is_int($state[$key] ?? null) || $state[$key] < 0 || $state[$key] > 3) return false;
        if (!array_key_exists('context', $state)) return false;
        if ($state['context'] !== null) {
            $context = $state['context'];
            if (!is_array($context) || array_keys($context) !== ['as_of', 'snapshot_version', 'source_fingerprint', 'policy_hash']
                || !is_string($context['as_of']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $context['as_of'])) return false;
            foreach (['snapshot_version', 'source_fingerprint', 'policy_hash'] as $key) if (!is_string($context[$key]) || !preg_match('/^[a-f0-9]{64}$/', $context[$key])) return false;
        }
        return true;
    }
}
