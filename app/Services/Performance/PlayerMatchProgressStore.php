<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/** Separate private chronology artifact; cannot be mistaken for leave-one-out influence. */
class PlayerMatchProgressStore
{
    public const VERSION = 6;
    public const LEGACY_VERSION = 5;
    public const EVIDENCE_LIMIT = 100;
    public const MAX_BYTES = 524288;

    public function path(int $player): string
    {
        if ($player < 1) throw new \InvalidArgumentException('Invalid player.');
        return storage_path('app/private/player-match-progress/player-'.$player.'.json');
    }

    public function policyHash(int $version = self::VERSION): string
    {
        return hash('sha256', $version.':'.app(PlayerAbilitySnapshotStore::class)->policyHash().':overall:strictly-before-scheduled-prefix:full-historical-window:simultaneous-unrelated-excluded:participant-ties-unavailable'
            .($version === self::VERSION ? ':historical-input-context-v'.PlayerMatchHistoricalContext::VERSION : ''));
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
        if (!$this->valid($saved, $player) || !hash_equals($fingerprint, $saved['source_fingerprint'])) throw new \RuntimeException('Chronological reconstruction sources or policy changed.');
        $old = $this->read($player);
        if ($old && [$old['as_of'], $old['built_at']] > [$saved['as_of'], $saved['built_at']]) throw new \RuntimeException('An older reconstruction cannot replace the saved artifact.');
        $json = json_encode($saved, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        if (strlen($json) > self::MAX_BYTES) throw new \OverflowException('Chronological reconstruction exceeds its private limit.');
        $path = $this->path($player); File::ensureDirectoryExists(dirname($path)); File::replace($path, $json, 0600);
    }

    private function valid(mixed $saved, int $player): bool
    {
        if (!is_array($saved) || !in_array($saved['version'] ?? null, [self::VERSION, self::LEGACY_VERSION], true) || ($saved['player_id'] ?? null) !== $player
            || ($saved['basis'] ?? null) !== 'scheduled_match_order' || ($saved['policy_hash'] ?? null) !== $this->policyHash($saved['version'])
            || !$this->hash($saved['source_fingerprint'] ?? null) || !$this->hash($saved['snapshot_version'] ?? null)
            || !$this->date($saved['as_of'] ?? null) || $saved['as_of'] > CarbonImmutable::today('Africa/Johannesburg')->toDateString()
            || !is_string($saved['built_at'] ?? null) || !$this->score($saved['current_score'] ?? null)
            || !is_array($saved['rows'] ?? null) || !array_is_list($saved['rows']) || count($saved['rows']) > PlayerMatchProgressCalculator::LIMIT) return false;
        if ($saved['version'] === self::VERSION && !app(PlayerMatchHistoricalContext::class)->valid($saved['historical_context'] ?? null, $saved['as_of'])) return false;
        if (!is_array($saved['own_catalog'] ?? null) || count($saved['own_catalog']) > (self::EVIDENCE_LIMIT + 1) * PlayerMatchProgressCalculator::LIMIT) return false;
        foreach ($saved['own_catalog'] as $id => $detail) {
            if (!is_string($id) || $id === '' || strlen($id) > 150 || !is_array($detail) || count($detail) !== 4
                || !$this->date($detail['date'] ?? null) || $detail['date'] > $saved['as_of'] || !is_string($detail['opponent'] ?? null) || mb_strlen($detail['opponent']) > 255
                || !is_string($detail['score'] ?? null) || strlen($detail['score']) > 150 || !is_bool($detail['won'] ?? null)) return false;
        }
        $seen = [];
        foreach ($saved['rows'] as $row) {
            if (!is_array($row) || !is_string($row['source_id'] ?? null) || $row['source_id'] === '' || strlen($row['source_id']) > 150 || isset($seen[$row['source_id']])
                || !$this->date($row['date'] ?? null) || $row['date'] > $saved['as_of'] || !is_bool($row['won'] ?? null)
                || !is_string($row['opponent'] ?? null) || mb_strlen($row['opponent']) > 255 || !is_string($row['score'] ?? null) || strlen($row['score']) > 150
                || !array_key_exists('time', $row) || !array_key_exists('before_score', $row) || !array_key_exists('after_score', $row)
                || !array_key_exists('delta', $row) || !array_key_exists('reason', $row)) return false;
            if ($row['time'] !== null && !$this->time($row['time'], $row['date'])) return false;
            if ($row['delta'] === null) {
                if ($row['before_score'] !== null || $row['after_score'] !== null || !is_string($row['reason']) || $row['reason'] === '' || strlen($row['reason']) > 250) return false;
            } elseif (!$this->score($row['before_score']) || !$this->score($row['after_score']) || !is_numeric($row['delta']) || !is_finite((float) $row['delta'])
                || abs(round((float) $row['after_score'] - (float) $row['before_score'], 1) - (float) $row['delta']) > .00001 || !$row['time'] || $row['reason'] !== null) return false;
            if (!$this->explanation($row['explanation'] ?? null, $row['delta'] !== null)) return false;
            if (!is_array($row['evidence_refs'] ?? null) || !array_is_list($row['evidence_refs']) || count($row['evidence_refs']) > self::EVIDENCE_LIMIT + 1
                || !is_int($row['omitted_matches'] ?? null) || $row['omitted_matches'] < 0 || $row['omitted_matches'] > PlayerMatchProgressCalculator::MAX_MATCHES) return false;
            $refs = []; $added = 0;
            foreach ($row['evidence_refs'] as $ref) {
                if (!is_array($ref) || count($ref) !== 6 || !is_int($ref['opponent_id'] ?? null) || $ref['opponent_id'] < 1 || $ref['opponent_id'] === $player
                    || !is_string($ref['source_id'] ?? null) || $ref['source_id'] === '' || strlen($ref['source_id']) > 150
                    || isset($refs[$ref['source_id']]) || !is_bool($ref['before'] ?? null) || ($ref['after'] ?? null) !== true) return false;
                if (!array_key_exists('opponent_before', $ref) || !array_key_exists('opponent_after', $ref)
                    || (($ref['opponent_before'] === null || $ref['opponent_after'] === null)
                        ? ($ref['opponent_before'] !== null || $ref['opponent_after'] !== null)
                        : (!$this->score($ref['opponent_before']) || !$this->score($ref['opponent_after'])))) return false;
                if (!$ref['before']) { $added++; if ($ref['source_id'] !== $row['source_id']) return false; }
                if (isset($saved['own_catalog'][$ref['source_id']]) && $saved['own_catalog'][$ref['source_id']]['date'] > $row['date']) return false;
                $refs[$ref['source_id']] = true;
            }
            if ($row['delta'] === null) { if ($refs || $row['omitted_matches'] !== 0) return false; }
            elseif ($added !== 1 || count($refs) + $row['omitted_matches'] !== $row['explanation']['own_after']) return false;
            $seen[$row['source_id']] = true;
        }
        return true;
    }

    private function score(mixed $s): bool { return is_numeric($s) && is_finite((float) $s) && $s >= 0 && $s <= 100; }
    private function explanation(mixed $value, bool $available): bool
    {
        if (!$available) return $value === null;
        $keys = ['network_before', 'network_after', 'own_before', 'own_after', 'opponents_before', 'opponents_after'];
        if (!is_array($value) || count($value) !== count($keys)) return false;
        foreach ($keys as $key) if (!isset($value[$key]) || !is_int($value[$key]) || $value[$key] < 0 || $value[$key] > PlayerMatchProgressCalculator::MAX_MATCHES) return false;
        return $value['network_after'] === $value['network_before'] + 1 && $value['own_after'] === $value['own_before'] + 1
            && $value['own_before'] <= $value['network_before'] && $value['opponents_before'] <= $value['own_before']
            && $value['opponents_after'] <= $value['own_after'] && in_array($value['opponents_after'] - $value['opponents_before'], [0, 1], true);
    }
    private function hash(mixed $s): bool { return is_string($s) && preg_match('/^[a-f0-9]{64}$/', $s) === 1; }
    private function date(mixed $s): bool
    {
        if (!is_string($s) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
        try { return CarbonImmutable::createFromFormat('!Y-m-d', $s)->toDateString() === $s; } catch (\Throwable) { return false; }
    }

    private function time(mixed $value, string $date): bool
    {
        if (!is_string($value) || !preg_match('/^'.preg_quote($date, '/').' \d{2}:\d{2}:\d{2}$/', $value)) return false;
        try { return CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $value, 'Africa/Johannesburg')->format('Y-m-d H:i:s') === $value && substr($value, 11) !== '00:00:00'; }
        catch (\Throwable) { return false; }
    }
}
