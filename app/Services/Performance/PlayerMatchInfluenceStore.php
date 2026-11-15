<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/** Bounded private per-player artifact; never a canonical result, rating or ledger. */
class PlayerMatchInfluenceStore
{
    public const VERSION = 1;
    public const MAX_BYTES = 32768;

    public function path(int $player): string
    {
        if ($player < 1) throw new \InvalidArgumentException('Invalid player.');
        return storage_path('app/private/player-match-influence/player-'.$player.'.json');
    }

    public function policyHash(): string
    {
        return hash('sha256', self::VERSION.':'.app(PlayerAbilitySnapshotStore::class)->policyHash().':overall:last-five:leave-one-source-out:same-reference-and-backbone');
    }

    public function read(int $player): ?array
    {
        try {
            $path = $this->path($player);
            if (!File::exists($path) || File::size($path) > self::MAX_BYTES) return null;
            $saved = json_decode(File::get($path), true, 32, JSON_THROW_ON_ERROR);
            return $this->valid($saved, $player) ? $saved : null;
        } catch (\Throwable) { return null; }
    }

    public function replace(int $player, array $saved, string $fingerprint): void
    {
        if (!$this->valid($saved, $player) || !hash_equals($fingerprint, $saved['source_fingerprint'])) throw new \RuntimeException('Match influence sources or policy changed.');
        $existing = $this->read($player);
        if ($existing && [$existing['as_of'], $existing['built_at']] > [$saved['as_of'], $saved['built_at']]) throw new \RuntimeException('An older calculation cannot replace the saved match influence.');
        $json = json_encode($saved, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        if (strlen($json) > self::MAX_BYTES) throw new \OverflowException('Match influence exceeds its private storage limit.');
        $path = $this->path($player);
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, $json, 0600);
    }

    private function valid(mixed $saved, int $player): bool
    {
        if (!is_array($saved) || ($saved['version'] ?? null) !== self::VERSION || ($saved['player_id'] ?? null) !== $player
            || ($saved['policy_hash'] ?? null) !== $this->policyHash() || !$this->hash($saved['source_fingerprint'] ?? null)
            || !$this->hash($saved['snapshot_version'] ?? null) || !$this->date($saved['as_of'] ?? null)
            || $saved['as_of'] > CarbonImmutable::today('Africa/Johannesburg')->toDateString() || !is_string($saved['built_at'] ?? null)
            || !$this->score($saved['baseline_score'] ?? null) || !is_array($saved['rows'] ?? null) || !array_is_list($saved['rows'])
            || count($saved['rows']) > PlayerMatchInfluenceCalculator::LIMIT) return false;
        $seen = [];
        foreach ($saved['rows'] as $row) {
            if (!is_array($row) || !is_string($row['source_id'] ?? null) || strlen($row['source_id']) > 150 || isset($seen[$row['source_id']])
                || !$this->date($row['date'] ?? null) || $row['date'] > $saved['as_of'] || !is_bool($row['won'] ?? null)
                || !is_string($row['opponent'] ?? null) || mb_strlen($row['opponent']) > 255 || !is_string($row['score'] ?? null) || strlen($row['score']) > 150
                || !array_key_exists('delta', $row) || !array_key_exists('without_score', $row) || !array_key_exists('reason', $row)) return false;
            if ($row['delta'] !== null && (!is_numeric($row['delta']) || !is_finite((float) $row['delta']) || abs($row['delta']) > 100 || !$this->score($row['without_score']) || $row['reason'] !== null)) return false;
            if ($row['delta'] !== null && abs(round((float) $saved['baseline_score'] - (float) $row['without_score'], 1) - (float) $row['delta']) > 0.00001) return false;
            if ($row['delta'] === null && ($row['without_score'] !== null || !is_string($row['reason']) || strlen($row['reason']) > 250)) return false;
            $seen[$row['source_id']] = true;
        }
        return true;
    }

    private function hash(mixed $value): bool { return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1; }
    private function score(mixed $value): bool { return is_numeric($value) && is_finite((float) $value) && $value >= 0 && $value <= 100; }
    private function date(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
        try { return CarbonImmutable::createFromFormat('!Y-m-d', $value)->toDateString() === $value; }
        catch (\Throwable) { return false; }
    }
}
