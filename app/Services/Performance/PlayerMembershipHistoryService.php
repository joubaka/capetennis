<?php

namespace App\Services\Performance;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};

/** Verified saved-source evidence only. No recomputation, cache or mutation. */
class PlayerMembershipHistoryService
{
    public function __construct(private PlayerAbilitySnapshotStore $store, private PlayerMembershipPreviewService $preview,
        private PlayerAbilityConsistentRead $read, private PlayerSharedAbilityService $shared,
        private PlayerPerformanceHistoryService $history, private PlayerPerformancePilotService $pilot) {}

    public function load(string $cohort, array $ids, ?string $version): array
    {
        $withheld = ['available' => false, 'reason' => 'Match evidence is withheld while the saved rating and current source results cannot be verified together. A saved update is needed; this page does not request one.', 'players' => []];
        if (!isset(PlayerMembershipPreviewService::COHORTS[$cohort]) || !$version || count($ids) < 1 || count($ids) > 2 || count(array_unique($ids)) !== count($ids)) { return $withheld; }
        try {
            return $this->read->run(function () use ($cohort, $ids, $version, $withheld) {
                $preview = $this->preview->build($cohort);
                $snapshot = $this->store->current();
                $allowed = array_column($preview['rows'], null, 'id');
                if ($preview['reason'] || ($preview['snapshot_version'] ?? null) !== $version || ($snapshot['snapshot_version'] ?? null) !== $version
                    || array_diff($ids, array_keys($allowed))) { return $withheld; }
                $meta = DB::table('player_ability_snapshots')->where('snapshot_key', 'current')->first();
                $state = Schema::hasTable('player_ability_refresh_state') ? DB::table('player_ability_refresh_state')->where('id', 1)->first() : null;
                if (!$meta || !$state || $state->generation > $state->completed_generation || $state->last_failed_at) { return $withheld; }
                $actualVersion = hash('sha256', json_encode([$meta->built_at, $meta->as_of, $meta->source_fingerprint,
                    $meta->publication_fingerprint, $meta->policy_hash, $meta->model_version], JSON_THROW_ON_ERROR));
                if (!hash_equals($version, $actualVersion) || !hash_equals($meta->source_fingerprint, $this->shared->fingerprint())) { return $withheld; }
                $asOf = CarbonImmutable::parse($preview['as_of'], 'Africa/Johannesburg');
                $sources = $snapshot['source_matches'] ?? null;
                if (!is_array($sources) || count($sources) > 50000) { return $withheld; }
                $sources = array_fill_keys($sources, true);
                $expected = []; $events = []; $players = [];
                foreach ($ids as $id) {
                    $matches = $snapshot['cohorts'][$cohort]['players'][$id]['confidence_matches'] ?? [];
                    if (count($matches) > 500) { return $withheld; }
                    $players[$id] = ['matches' => [], 'wins' => 0, 'losses' => 0, 'played_total' => count($matches)];
                    foreach ($matches as $m) {
                        $key = $this->key($m['event'], $m['opponent'], $m['date'], $m['basis']);
                        $expected[$id][$key] = ($expected[$id][$key] ?? 0) + 1;
                        $events[(int) $m['event']] = true;
                    }
                }
                if (count($events) > 50) { return $withheld; }
                $scanned = 0; $seen = [];
                foreach (Event::query()->whereIn('id', array_keys($events))->where('published', true)->orderBy('id')->get() as $event) {
                    // Match readers use the same in-memory end-date clamp as the saved model.
                    if (CarbonImmutable::parse($event->end_date)->greaterThan($asOf)) { $event->end_date = $asOf->toDateString(); }
                    foreach ([$this->history->individualMatches($event, null, $this->pilot, $asOf, true), $this->history->teamMatches($event, null, $asOf)] as $generator) {
                        foreach ($generator as $r) {
                            if (++$scanned > 10000) { return $withheld; }
                            $source = ($r['source'] === 'Team match' ? 'team-match:' : 'match:').$r['fixture_id'];
                            if (isset($seen[$source]) || !isset($sources[$source]) || $r['reason'] !== null || $r['discipline'] !== 'singles' || ($r['confidence_future'] ?? false)) { continue; }
                            $key = $r['source'] === 'Team match' ? ($r['shared_cohort'] ?? '') : $this->shared->cohort($r['cohort']);
                            if ($key !== $cohort || $r['confidence_date'] > $asOf->toDateString() || !in_array($r['winner_side'], [1, 2], true)) { continue; }
                            $seen[$source] = true;
                            foreach ($ids as $id) {
                                $first = (int) $r['player1_id'] === $id;
                                if (!$first && (int) $r['player2_id'] !== $id) { continue; }
                                $opponent = (int) ($first ? $r['player2_id'] : $r['player1_id']);
                                $evidenceKey = $this->key($r['event_id'], $opponent, $r['confidence_date'], $r['confidence_date_basis']);
                                if (($expected[$id][$evidenceKey] ?? 0) <= 0) { return $withheld; }
                                $score = $this->score($r['score'], $first);
                                if ($score === null || !isset($snapshot['names'][$opponent])) { return $withheld; }
                                $expected[$id][$evidenceKey]--;
                                $won = $r['winner_side'] === ($first ? 1 : 2);
                                $players[$id][$won ? 'wins' : 'losses']++;
                                $players[$id]['matches'][] = ['source_id' => $source, 'fixture_id' => (int) $r['fixture_id'], 'event_id' => (int) $r['event_id'],
                                    'event' => $r['event'], 'date' => $r['confidence_date'], 'date_basis' => $r['confidence_date_basis'],
                                    'opponent_id' => $opponent, 'opponent' => $snapshot['names'][$opponent], 'opponent_detail' => isset($allowed[$opponent]),
                                    'won' => $won, 'score' => $score];
                            }
                        }
                    }
                }
                foreach ($ids as $id) {
                    if (array_sum($expected[$id] ?? []) !== 0 || count($players[$id]['matches']) !== $players[$id]['played_total']) { return $withheld; }
                    usort($players[$id]['matches'], fn ($a, $b) => strcmp($b['date'], $a['date']) ?: $b['event_id'] <=> $a['event_id'] ?: strcmp($a['source_id'], $b['source_id']));
                }
                return ['available' => true, 'reason' => null, 'players' => $players, 'snapshot_version' => $version];
            });
        } catch (\Throwable) { return $withheld; }
    }

    private function key(mixed $event, mixed $opponent, string $date, string $basis): string
    {
        return json_encode([(int) $event, (int) $opponent, $date, $basis], JSON_THROW_ON_ERROR);
    }

    private function score(string $score, bool $first): ?string
    {
        if (!preg_match('/^\d+-\d+(?: \d+-\d+)*$/', $score)) { return null; }
        if ($first) { return $score; }
        return implode(' ', array_map(function ($set) { [$a, $b] = explode('-', $set); return $b.'-'.$a; }, explode(' ', $score)));
    }
}
