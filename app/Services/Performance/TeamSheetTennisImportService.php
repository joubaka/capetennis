<?php

namespace App\Services\Performance;

use App\Models\{Player, User};
use App\Services\PlatformAuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Illuminate\Validation\ValidationException;

/** Restricted native evidence only; never imports TeamSheet's Cape Tennis snapshots or ratings. */
class TeamSheetTennisImportService
{
    public const CAP = 10000;
    public const MAX_AGE_HOURS = 24;

    public function ready(): bool { return Schema::hasTable('teamsheet_tennis_matches'); }

    public function origin(): ?string
    {
        $url = config('teamsheet.url');
        if (!is_string($url) || strlen($url) > 500 || !is_string(config('teamsheet.token')) || trim(config('teamsheet.token')) === '') return null;
        $parts = parse_url($url);
        if (!$parts || !isset($parts['scheme'], $parts['host']) || isset($parts['user'], $parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) return null;
        $local = app()->environment('local') && config('teamsheet.allow_local_http') && $parts['scheme'] === 'http'
            && in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '[::1]'], true);
        return $parts['scheme'] === 'https' || $local ? rtrim($url, '/') : null;
    }

    public function fresh(): bool
    {
        if (!$this->ready() || !$origin = $this->origin()) return false;
        $feed = DB::table('teamsheet_tennis_feeds')->where('id', 1)->first();
        if (!$feed || $feed->origin !== $origin || !$feed->last_success_at) return false;
        $time = CarbonImmutable::parse($feed->last_success_at);
        return !$time->isFuture() && $time->greaterThan(CarbonImmutable::now()->subHours(self::MAX_AGE_HOURS));
    }

    public function sync(?User $actor = null): int
    {
        if (!$this->ready() || !$origin = $this->origin()) $this->fail('Configure the TeamSheet HTTPS results origin and read-only token before syncing.');
        $startVersion = (int) DB::table('teamsheet_tennis_feeds')->where('id', 1)->value('version');
        $rows = []; $hash = null; $total = null; $last = null;
        for ($page = 1; ; $page++) {
            $response = Http::withToken(config('teamsheet.token'))->acceptJson()->withoutRedirecting()->connectTimeout(5)->timeout(20)
                ->get($origin.'/api/v1/integrations/cape-tennis/results', ['page' => $page, 'per_page' => 100]);
            if (!$response->successful() || strlen($response->body()) > 2 * 1024 * 1024) $this->fail('The verified results snapshot could not be read. Earlier results were retained.');
            $payload = $response->json(); $meta = $payload['meta'] ?? [];
            if (!is_array($payload['data'] ?? null) || !array_is_list($payload['data']) || count($payload['data']) > 100
                || ($meta['snapshot'] ?? null) !== true || ($meta['current_page'] ?? null) !== $page
                || !is_int($meta['last_page'] ?? null) || $meta['last_page'] < $page || $meta['last_page'] > 100
                || !is_int($meta['total'] ?? null) || $meta['total'] < 0 || $meta['total'] > self::CAP
                || !is_string($meta['snapshot_hash'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $meta['snapshot_hash'])
                || ($hash !== null && $hash !== $meta['snapshot_hash']) || ($total !== null && $total !== $meta['total'])
                || ($last !== null && $last !== $meta['last_page'])) $this->fail('The verified snapshot is incomplete or changed during download. Earlier results were retained.');
            $hash = $meta['snapshot_hash']; $total = $meta['total']; $last = $meta['last_page'];
            foreach ($payload['data'] as $row) {
                $this->validateRow($row);
                if (isset($rows[$row['source_match_id']])) $this->fail('Duplicate source match in verified snapshot.');
                $rows[$row['source_match_id']] = $row;
            }
            if ($page === $last) break;
        }
        if (count($rows) !== $total) $this->fail('Incomplete verified snapshot. Earlier results were retained.');
        ksort($rows, SORT_STRING);
        if (!hash_equals($hash, hash('sha256', json_encode(array_values($rows), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)))) $this->fail('The snapshot contents do not match its revision. Earlier results were retained.');
        DB::transaction(function () use ($rows, $hash, $origin, $startVersion, $actor) {
            $feed = DB::table('teamsheet_tennis_feeds')->where('id', 1)->lockForUpdate()->first();
            if ((int) $feed->version !== $startVersion || $origin !== $this->origin()) $this->fail('The connection changed during syncing. Retry the sync.');
            // A different configured installation must never inherit identity mappings.
            if ($feed->origin !== null && $feed->origin !== $origin) $this->fail('This import belongs to another TeamSheet origin. Review the connection before changing it.');
            $existingIds = DB::table('teamsheet_tennis_matches')->limit(self::CAP + 1)->pluck('source_match_id')->all();
            $existingCompetitions = DB::table('teamsheet_tennis_competitions')->limit(self::CAP + 1)->pluck('source_key')->all();
            $incomingCompetitions = array_map(fn ($row) => $row['competition']['type'].':'.$row['competition']['id'], $rows);
            if (count(array_unique([...$existingIds, ...array_keys($rows)])) > self::CAP
                || count(array_unique([...$existingCompetitions, ...$incomingCompetitions])) > self::CAP) $this->fail('The retained import history exceeds its safe limit. Earlier results were retained for review.');
            DB::table('teamsheet_tennis_matches')->where('retracted', false)->update(['retracted' => true]);
            foreach ($rows as $row) {
                $key = $row['competition']['type'].':'.$row['competition']['id'];
                DB::table('teamsheet_tennis_competitions')->insertOrIgnore(['source_key' => $key]);
                $competition = DB::table('teamsheet_tennis_competitions')->where('source_key', $key)->value('id');
                $existing = DB::table('teamsheet_tennis_matches')->where('source_match_id', $row['source_match_id'])->first();
                $values = ['source_version' => $row['source_version'], 'competition_id' => $competition, 'payload' => json_encode($row, JSON_THROW_ON_ERROR), 'retracted' => false, 'updated_at' => now()];
                if ($existing) DB::table('teamsheet_tennis_matches')->where('id', $existing->id)->update($values);
                else DB::table('teamsheet_tennis_matches')->insert($values + ['source_match_id' => $row['source_match_id'], 'created_at' => now()]);
            }
            DB::table('teamsheet_tennis_feeds')->where('id', 1)->update(['origin' => $origin, 'snapshot_hash' => $hash, 'version' => $startVersion + 1, 'last_success_at' => now()]);
            PlatformAuditLogger::log('teamsheet.tennis.synced', meta: ['count' => count($rows), 'snapshot_hash' => $hash, 'restricted_source' => true], userId: $actor?->id);
        });
        return $total;
    }

    public function validateRow(mixed $row): void
    {
        if (!is_array($row) || ($row['source'] ?? null) !== 'teamsheet' || ($row['discipline'] ?? null) !== 'singles'
            || ($row['status'] ?? null) !== 'verified' || !is_string($row['source_match_id'] ?? null)
            || !preg_match('/^ts-(tournament-rubber|fixture-rubber|challenge)-[1-9][0-9]{0,14}$/', $row['source_match_id'])
            || !is_string($row['source_version'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $row['source_version'])
            || !in_array($row['competition']['type'] ?? null, ['tournament', 'league', 'ladder'], true)
            || !is_int($row['competition']['id'] ?? null) || $row['competition']['id'] < 1
            || !in_array($row['date_precision'] ?? null, ['actual_date', 'actual_time', 'scheduled_date', 'recorded_time'], true)
            || (isset($row['ball_type']) && !in_array($row['ball_type'], ['red', 'orange', 'green', 'yellow', 'standard'], true))) $this->fail('Invalid native tennis result. Earlier results were retained.');
        $prefix = match ($row['competition']['type']) { 'tournament' => 'ts-tournament-rubber-', 'league' => 'ts-fixture-rubber-', 'ladder' => 'ts-challenge-' };
        if (!str_starts_with($row['source_match_id'], $prefix)) $this->fail('Result source and competition do not agree.');
        $unsigned = $row; unset($unsigned['source_version']);
        if (!hash_equals($row['source_version'], hash('sha256', json_encode($unsigned, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)))) $this->fail('The result contents do not match its revision.');
        $a = $row['side1']['teamsheet_player_id'] ?? null; $b = $row['side2']['teamsheet_player_id'] ?? null;
        if (!is_int($a) || !is_int($b) || $a < 1 || $b < 1 || $a === $b || !in_array($row['winner_player_id'] ?? null, [$a, $b], true)) $this->fail('Invalid result participants.');
        if (!is_array($row['sets'] ?? null) || !array_is_list($row['sets']) || count($row['sets']) < 1 || count($row['sets']) > 5) $this->fail('Invalid singles score.');
        $wins = [0, 0];
        foreach ($row['sets'] as $set) {
            $x = $set['side1_games'] ?? null; $y = $set['side2_games'] ?? null;
            if (!is_int($x) || !is_int($y) || min($x, $y) < 0 || max($x, $y) > 100 || $x === $y) $this->fail('Invalid singles score.');
            $wins[$x > $y ? 0 : 1]++;
        }
        if ($wins[0] === $wins[1] || ($row['winner_player_id'] === $a) !== ($wins[0] > $wins[1])) $this->fail('Winner does not agree with the score.');
        try {
            if (!is_string($row['played_at'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}(?:T|$)/', $row['played_at'])) throw new \InvalidArgumentException;
            $date = CarbonImmutable::parse($row['played_at']);
            if ($date->toDateString() !== substr($row['played_at'], 0, 10) || $date->startOfDay()->isAfter(CarbonImmutable::today())) throw new \InvalidArgumentException;
        } catch (\Throwable) { $this->fail('Invalid match date.'); }
    }

    public function link(User $actor, int $teamsheetId, int $playerId, string $reason): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        Player::findOrFail($playerId);
        if ($teamsheetId < 1 || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 1000) $this->fail('Record the identity evidence for this connection.');
        DB::transaction(function () use ($actor, $teamsheetId, $playerId, $reason) {
            DB::table('teamsheet_tennis_feeds')->where('id', 1)->lockForUpdate()->first();
            if (DB::table('teamsheet_tennis_player_links')->where('teamsheet_player_id', $teamsheetId)->orWhere('player_id', $playerId)->exists()) $this->fail('This identity is already linked. Remove the reviewed connection first.');
            if (DB::table('teamsheet_tennis_player_links')->count() >= self::CAP) $this->fail('The reviewed identity connection limit has been reached.');
            $found = false;
            foreach (DB::table('teamsheet_tennis_matches')->where('retracted', false)->orderBy('id')->lazyById(200) as $row) {
                $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                if (in_array($teamsheetId, [$payload['side1']['teamsheet_player_id'], $payload['side2']['teamsheet_player_id']], true)) { $found = true; break; }
            }
            if (!$found) $this->fail('Choose a TeamSheet identity present in the current verified results.');
            DB::table('teamsheet_tennis_player_links')->insert(['teamsheet_player_id' => $teamsheetId, 'player_id' => $playerId, 'reviewed_by' => $actor->id, 'reason' => trim($reason), 'created_at' => now(), 'updated_at' => now()]);
            PlatformAuditLogger::log('teamsheet.tennis.player-linked', after: ['teamsheet_player_id' => $teamsheetId, 'player_id' => $playerId], meta: ['reason' => trim($reason)], userId: $actor->id);
        });
    }

    public function unlink(User $actor, int $id, string $reason): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        if (mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 1000) $this->fail('Record a reason for removing the connection.');
        DB::transaction(function () use ($actor, $id, $reason) {
            DB::table('teamsheet_tennis_feeds')->where('id', 1)->lockForUpdate()->first();
            $link = DB::table('teamsheet_tennis_player_links')->where('id', $id)->first(); abort_unless($link, 404);
            DB::table('teamsheet_tennis_player_links')->where('id', $id)->delete();
            PlatformAuditLogger::log('teamsheet.tennis.player-unlinked', before: (array) $link, meta: ['reason' => trim($reason)], userId: $actor->id);
        });
    }

    public function reviewDate(User $actor, int $id, string $revision, string $date, string $reason): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        if (mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 1000) $this->fail('Record evidence for the actual playing date.');
        try { $day = CarbonImmutable::createFromFormat('!Y-m-d', $date); if ($day->toDateString() !== $date || $day->isFuture()) throw new \InvalidArgumentException; }
        catch (\Throwable) { $this->fail('Choose an actual match date that is not in the future.'); }
        DB::transaction(function () use ($actor, $id, $revision, $date, $reason) {
            DB::table('teamsheet_tennis_feeds')->where('id', 1)->lockForUpdate()->first();
            $match = DB::table('teamsheet_tennis_matches')->where('id', $id)->first(); abort_unless($match, 404);
            if ($match->retracted || $match->source_version !== $revision) $this->fail('The source result changed. Reload before reviewing.');
            DB::table('teamsheet_tennis_matches')->where('id', $id)->update(['reviewed_played_on' => $date, 'reviewed_version' => $revision, 'reviewed_by' => $actor->id, 'review_reason' => trim($reason), 'updated_at' => now()]);
            PlatformAuditLogger::log('teamsheet.tennis.date-reviewed', after: ['source_match_id' => $match->source_match_id, 'source_version' => $revision, 'played_on' => $date], meta: ['reason' => trim($reason)], userId: $actor->id);
        });
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['teamsheet' => $message]); }
}
