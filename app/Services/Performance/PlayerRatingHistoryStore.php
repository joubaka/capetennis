<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/** Durable private derived data; atomic replacement retains the last good build on failure. */
class PlayerRatingHistoryStore
{
    public const VERSION = 1;

    public function path(): string { return storage_path('app/private/player-rating-history.json'); }

    public function policyHash(): string
    {
        return hash('sha256', self::VERSION.':'.app(PlayerAbilitySnapshotStore::class)->policyHash().':completed-events:12-calendar-months');
    }

    public function read(): ?array
    {
        try {
            if (!File::exists($this->path()) || File::size($this->path()) > 20 * 1024 * 1024) return null;
            $saved = json_decode(File::get($this->path()), true, 512, JSON_THROW_ON_ERROR);
            if (!$this->valid($saved)) return null;
            return $saved;
        } catch (\Throwable) { return null; }
    }

    public function replace(array $saved, string $currentFingerprint): void
    {
        if (!$this->valid($saved) || $saved['source_fingerprint'] !== $currentFingerprint) throw new \RuntimeException('History sources or policy changed before save.');
        $existing = $this->read();
        if ($existing && [$existing['as_of'], $existing['built_at']] > [$saved['as_of'], $saved['built_at']]) throw new \RuntimeException('An older history cannot replace the saved build.');
        $json = json_encode($saved, JSON_THROW_ON_ERROR);
        if (strlen($json) > 20 * 1024 * 1024) throw new \RuntimeException('History exceeds storage limit.');
        File::ensureDirectoryExists(dirname($this->path()));
        File::replace($this->path(), $json);
    }

    private function valid(mixed $saved): bool
    {
        if (!is_array($saved) || ($saved['version'] ?? null) !== self::VERSION || ($saved['policy_hash'] ?? '') !== $this->policyHash()
            || !preg_match('/^[a-f0-9]{64}$/', $saved['source_fingerprint'] ?? '')
            || !is_string($saved['as_of'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saved['as_of'])
            || $saved['as_of'] > CarbonImmutable::today('Africa/Johannesburg')->toDateString()
            || !is_string($saved['built_at'] ?? null) || !is_array($saved['months'] ?? null) || count($saved['months']) !== 12) return false;
        $today = CarbonImmutable::createFromFormat('!Y-m-d', $saved['as_of'], 'Africa/Johannesburg');
        if ($today->toDateString() !== $saved['as_of']) return false;
        foreach ($saved['months'] as $index => $month) {
            $offset = 11 - $index;
            $expected = $offset === 0 ? $today : $today->startOfMonth()->subMonths($offset)->endOfMonth();
            if (($month['date'] ?? null) !== $expected->toDateString() || !is_array($month['cohorts'] ?? null) || count($month['cohorts']) > 1000) return false;
            $count = 0;
            foreach ($month['cohorts'] as $players) {
                if (!is_array($players) || ($count += count($players)) > 50000) return false;
                foreach ($players as $rating) {
                    if (!is_array($rating) || !array_key_exists('score', $rating) || !is_string($rating['component'] ?? null)
                        || !preg_match('/^[a-f0-9]{64}$/', $rating['group'] ?? '')
                        || ($rating['score'] !== null && (!is_numeric($rating['score']) || !is_finite((float) $rating['score']) || $rating['score'] < 0 || $rating['score'] > 100))) return false;
                }
            }
        }
        return true;
    }
}
