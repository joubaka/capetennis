<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/** Durable private derived data; atomic replacement retains the last good build on failure. */
class PlayerRatingHistoryStore
{
    public const VERSION = 3;

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
            || !is_string($saved['built_at'] ?? null) || !is_array($saved['months'] ?? null) || !array_is_list($saved['months']) || count($saved['months']) !== 12
            || !is_array($saved['evidence'] ?? null) || !array_is_list($saved['evidence']) || count($saved['evidence']) > 50000) return false;
        $today = CarbonImmutable::createFromFormat('!Y-m-d', $saved['as_of'], 'Africa/Johannesburg');
        if ($today->toDateString() !== $saved['as_of']) return false;
        foreach ($saved['months'] as $index => $month) {
            $offset = 11 - $index;
            $expected = $offset === 0 ? $today : $today->startOfMonth()->subMonths($offset)->endOfMonth();
            if (($month['date'] ?? null) !== $expected->toDateString() || !is_array($month['cohorts'] ?? null) || count($month['cohorts']) > 1000) return false;
            $count = 0;
            foreach ($month['cohorts'] as $cohort => $players) {
                if (!is_array($players) || ($count += count($players)) > 50000) return false;
                foreach ($players as $rating) {
                    if (!is_array($rating) || !array_key_exists('score', $rating) || !is_string($rating['component'] ?? null)
                        || !preg_match('/^[a-f0-9]{64}$/', $rating['group'] ?? '')
                        || ($rating['score'] !== null && (!is_numeric($rating['score']) || !is_finite((float) $rating['score']) || $rating['score'] < 0 || $rating['score'] > 100))) return false;
                    if ($cohort === AllAgeRatingModel::COHORT && !$this->validSharedConnection($rating, $month['date'])) return false;
                }
            }
        }
        foreach ($saved['evidence'] as $row) {
            if (!is_array($row) || !in_array($row['kind'] ?? null, ['match', 'finish'], true)
                || !is_string($row['source_id'] ?? null) || strlen($row['source_id']) > 350 || !is_string($row['cohort'] ?? null) || strlen($row['cohort']) > 255
                || !is_int($row['event_id'] ?? null) || $row['event_id'] < 1 || !is_string($row['event'] ?? null) || mb_strlen($row['event']) > 255
                || !is_string($row['date'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['date'])
                || !is_string($row['completed_at'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['completed_at'])
                || !is_string($row['date_basis'] ?? null) || strlen($row['date_basis']) > 150
                || !is_int($row['months'] ?? null) || $row['months'] < 1 || $row['months'] > 4095
                || !is_int($row['baseline_months'] ?? null) || $row['baseline_months'] < 0 || ($row['baseline_months'] & ~$row['months']) !== 0
                || !is_array($row['players'] ?? null) || !array_is_list($row['players']) || count($row['players']) < 2 || count($row['players']) > 256
                || count(array_unique($row['players'])) !== count($row['players']) || !is_array($row['names'] ?? null) || !array_is_list($row['names']) || count($row['names']) !== count($row['players'])) return false;
            foreach ($row['players'] as $id) if (!is_int($id) || $id < 1) return false;
            foreach ($row['names'] as $name) if (!is_string($name) || mb_strlen($name) > 255) return false;
            if ($row['kind'] === 'match' && (count($row['players']) !== 2 || !in_array($row['winner_side'] ?? null, [1, 2], true)
                || !is_string($row['score'] ?? null) || strlen($row['score']) > 150)) return false;
        }
        return true;
    }

    private function validSharedConnection(array $rating, string $date): bool
    {
        $status = $rating['connection_status'] ?? null;
        if (!in_array($status, ['recent', 'historical', 'disconnected'], true)
            || !is_bool($rating['historical_connection'] ?? null)
            || !array_key_exists('connection_age_days', $rating) || !array_key_exists('connection_reference_date', $rating)) return false;
        if ($status === 'disconnected') {
            return $rating['score'] === null && $rating['historical_connection'] === false
                && $rating['connection_age_days'] === null && $rating['connection_reference_date'] === null;
        }
        $age = $rating['connection_age_days'];
        return $rating['score'] !== null && is_int($age) && $age >= 0 && $age <= AllAgeRatingModel::WINDOW_DAYS
            && $rating['historical_connection'] === ($status === 'historical')
            && ($status === 'historical' ? $age > AllAgeRatingModel::RECENT_WINDOW_DAYS : $age <= AllAgeRatingModel::RECENT_WINDOW_DAYS)
            && is_string($rating['connection_reference_date'])
            && $rating['connection_reference_date'] === CarbonImmutable::parse($date)->subDays($age)->toDateString();
    }
}
