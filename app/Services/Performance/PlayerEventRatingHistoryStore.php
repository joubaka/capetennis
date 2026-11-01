<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/** Private target-player materialization; never an official historical ranking. */
class PlayerEventRatingHistoryStore
{
    public const VERSION = 1;
    public const MAX_EVENTS = 200;
    public const MAX_BYTES = 2 * 1024 * 1024;

    public function path(int $player): string
    {
        if ($player < 1) throw new \InvalidArgumentException('Invalid player.');
        return storage_path('app/private/player-event-ratings/player-'.$player.'.json');
    }

    public function policyHash(): string
    {
        return hash('sha256', self::VERSION.':'.app(PlayerAbilitySnapshotStore::class)->policyHash().':published-native-events:completion-date');
    }

    public function read(int $player): ?array
    {
        try {
            $path = $this->path($player);
            if (!File::exists($path) || File::size($path) > self::MAX_BYTES) return null;
            $saved = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            return $this->valid($saved, $player) ? $saved : null;
        } catch (\Throwable) { return null; }
    }

    public function replace(int $player, array $saved, string $fingerprint): void
    {
        if (!$this->valid($saved, $player) || !hash_equals($fingerprint, $saved['source_fingerprint'])) throw new \RuntimeException('Event history sources or policy changed.');
        $json = json_encode($saved, JSON_THROW_ON_ERROR);
        if (strlen($json) > self::MAX_BYTES) throw new \OverflowException('Event history exceeds its private storage limit.');
        $path = $this->path($player);
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, $json, 0600);
        chmod($path, 0600);
    }

    private function valid(mixed $saved, int $player): bool
    {
        if (!is_array($saved) || ($saved['version'] ?? null) !== self::VERSION || ($saved['player_id'] ?? null) !== $player
            || ($saved['policy_hash'] ?? null) !== $this->policyHash() || !preg_match('/^[a-f0-9]{64}$/', $saved['source_fingerprint'] ?? '')
            || !$this->date($saved['as_of'] ?? null) || $saved['as_of'] > CarbonImmutable::today('Africa/Johannesburg')->toDateString()
            || !is_string($saved['built_at'] ?? null) || !is_array($saved['events'] ?? null) || !array_is_list($saved['events'])
            || count($saved['events']) > self::MAX_EVENTS) return false;
        $seen = [];
        foreach ($saved['events'] as $row) {
            if (!is_array($row) || !is_int($row['event_id'] ?? null) || $row['event_id'] < 1 || isset($seen[$row['event_id']])
                || !is_string($row['event'] ?? null) || mb_strlen($row['event']) > 255 || !$this->date($row['date'] ?? null)
                || $row['date'] > $saved['as_of'] || !is_bool($row['has_eligible_matches'] ?? null)
                || !is_array($row['entry_stages'] ?? null) || !array_is_list($row['entry_stages']) || count($row['entry_stages']) > 3
                || count(array_unique($row['entry_stages'])) !== count($row['entry_stages'])
                || array_diff($row['entry_stages'], array_keys(BallRatingModel::LABELS)) || !is_array($row['finishes'] ?? null)
                || !array_is_list($row['finishes']) || count($row['finishes']) > 256
                || !is_array($row['levels'] ?? null) || array_keys($row['levels']) !== array_keys(BallRatingModel::LABELS)) return false;
            $seen[$row['event_id']] = true;
            $played = 0;
            foreach ($row['levels'] as $level) {
                if (!is_array($level) || !array_key_exists('score', $level)
                    || ($level['score'] !== null && (!is_numeric($level['score']) || !is_finite((float) $level['score']) || $level['score'] < 0 || $level['score'] > 100))
                    || !is_bool($level['local'] ?? null) || !is_int($level['matches_in_event'] ?? null) || $level['matches_in_event'] < 0
                    || !is_int($level['cape_events'] ?? null) || $level['cape_events'] < 0 || !is_string($level['reason'] ?? null)
                    || strlen($level['reason']) > 250 || (!is_null($level['confidence_label'] ?? null) && !in_array($level['confidence_label'], ['Low', 'Medium', 'High'], true))) return false;
                $played += $level['matches_in_event'];
                if (array_key_exists('evidence_basis', $level) && !in_array($level['evidence_basis'], [null, 'matches', 'matches_and_finishes', 'finish_only'], true)) return false;
                if (($level['evidence_basis'] ?? null) === 'finish_only'
                    && ($level['score'] === null || !$level['local'] || ($level['confidence_label'] ?? null) !== 'Low' || $level['matches_in_event'] !== 0)) return false;
            }
            if ($row['has_eligible_matches'] !== ($played > 0)) return false;
            foreach ($row['finishes'] as $finish) {
                if (!is_array($finish) || !is_string($finish['category'] ?? null) || mb_strlen($finish['category']) > 255
                    || !is_int($finish['position'] ?? null) || $finish['position'] < 1 || $finish['position'] > 256) return false;
            }
        }
        return true;
    }

    private function date(mixed $date): bool
    {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
        try { return CarbonImmutable::createFromFormat('!Y-m-d', $date)->toDateString() === $date; }
        catch (\Throwable) { return false; }
    }
}
