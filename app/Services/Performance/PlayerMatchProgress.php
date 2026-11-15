<?php

namespace App\Services\Performance;

use App\Models\{Player, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlayerMatchProgress
{
    public function __construct(private PlayerMatchProgressCalculator $calculator, private PlayerMatchChronologySources $chronology,
        private PlayerMatchProgressStore $store, private PlayerAbilitySnapshotStore $snapshots, private PlayerRatingHistoryStore $history,
        private PlayerSharedAbilityService $ability, private PlayerRatingAccess $access) {}

    public function forPlayer(User $user, Player $player): array
    {
        abort_unless($this->access->allows($user, $player), 403);
        $empty = ['rows' => [], 'as_of' => null, 'restricted' => 0, 'reason' => 'Before/after reconstruction is awaiting a nightly saved update. The overnight refresh starts at 03:02 SAST.'];
        $saved = $this->store->read((int) $player->id);
        if (!$saved || $saved['player_id'] !== (int) $player->id) return $empty;
        try { if (!$this->current($saved)) return $empty; } catch (\Throwable) { return $empty; }
        $rows = []; $restricted = 0; $permitted = [];
        $opponentIds = [];
        foreach ($saved['rows'] as $row) foreach ($row['evidence_refs'] as $ref) $opponentIds[] = $ref['opponent_id'];
        foreach (array_chunk(array_values(array_unique($opponentIds)), 100) as $chunk) {
            try { foreach (app(PlayerRatingTopTen::class)->forMatchPlayers($user, $player, $chunk) as $id => $_current) $permitted[(int) $id] = true; }
            catch (\Throwable) { /* Current comparison permission is unavailable: withhold numbers. */ }
        }
        foreach ($saved['rows'] as $row) {
            if (str_starts_with($row['source_id'], 'teamsheet:') && !$user->hasRole('super-user')) { $restricted++; continue; }
            $rows[] = array_intersect_key($row, array_flip(['date', 'time', 'opponent', 'score', 'won', 'before_score', 'after_score', 'delta', 'reason', 'explanation']));
            $index = array_key_last($rows); $rows[$index]['used_matches'] = []; $rows[$index]['hidden_matches'] = $row['omitted_matches'];
            foreach ($row['evidence_refs'] as $ref) {
                $detail = $saved['own_catalog'][$ref['source_id']] ?? null;
                if (!$detail || (str_starts_with($ref['source_id'], 'teamsheet:') && !$user->hasRole('super-user'))) { $rows[$index]['hidden_matches']++; continue; }
                $projected = $detail + array_intersect_key($ref, array_flip(['before', 'after', 'opponent_before', 'opponent_after']));
                if (!isset($permitted[$ref['opponent_id']])) $projected['opponent_before'] = $projected['opponent_after'] = null;
                $rows[$index]['used_matches'][] = $projected;
            }
        }
        return ['rows' => $rows, 'as_of' => $saved['as_of'], 'restricted' => $restricted, 'reason' => null];
    }

    /** Expensive historical reconstruction: explicit offline command only. */
    public function build(Player $player): array
    {
        $fingerprint = $this->ability->fingerprint(); $snapshot = $this->snapshots->current(); $history = $this->history->read();
        $asOf = $snapshot['snapshot_as_of'] ?? null;
        if (($snapshot['reason'] ?? null) !== null || !$asOf || !$history || $history['as_of'] !== $asOf
            || !hash_equals($fingerprint, $history['source_fingerprint'])
            || !hash_equals($fingerprint, (string) DB::table('player_ability_snapshots')->where('snapshot_key', 'current')->value('source_fingerprint'))
            || !isset($history['publication_manifest']) || !$this->snapshots->published($history['publication_manifest'])) throw new \RuntimeException('Current matching saved ratings and history are required.');
        $currentScore = $snapshot['all_age']['ratings'][$player->id]['score'] ?? null;
        if (!is_numeric($currentScore) || ($snapshot['all_age']['ratings'][$player->id]['comparable'] ?? false) !== true) throw new \RuntimeException('A comparable current Overall CTR is required.');
        $matches = $snapshot['all_age']['history_evidence']['matches'] ?? [];
        $selected = $this->calculator->latest((int) $player->id, $matches, $this->chronology->resolve($matches));
        $dates = array_values(array_unique(array_column($selected, 'confidence_date'))); sort($dates, SORT_STRING);
        $days = []; $sources = [];
        foreach ($dates as $date) {
            // A current 730-day graph cannot reconstruct an earlier date's complete 730-day window.
            $day = CarbonImmutable::parse($date, 'Africa/Johannesburg');
            $historical = $this->ability->historySnapshot($day, $sources);
            if (($historical['reason'] ?? null) !== null) throw new \RuntimeException('Historical canonical evidence is unavailable.');
            $pool = $historical['all_age']['history_evidence']['matches'] ?? [];
            $builder = new PlayerRatingEvidenceBuilder;
            try { $builder->append(['all_age' => $historical['all_age'], 'names' => $historical['names'] ?? []], $sources, 0); }
            catch (\RuntimeException) { $builder = new PlayerRatingEvidenceBuilder; }
            $historicalDetails = [];
            foreach ($builder->all() as $detail) if ($detail['kind'] === 'match' && in_array((int) $player->id, $detail['players'], true)) $historicalDetails[$detail['source_id']] = $detail;
            $days[$date] = ['matches' => $pool, 'order' => $this->chronology->resolve($pool), 'details' => $historicalDetails];
        }
        $details = [];
        foreach ($history['evidence'] as $row) if (($row['kind'] ?? null) === 'match' && ($row['cohort'] ?? null) === AllAgeRatingModel::COHORT
            && in_array((int) $player->id, $row['players'] ?? [], true) && (($row['months'] ?? 0) & (1 << 11))) $details[$row['source_id']] = $row;
        $rows = []; $catalog = [];
        foreach ($selected as $match) {
            $day = $days[$match['confidence_date']];
            $pair = $this->calculator->pair((int) $player->id, $match['source_id'], $day['matches'], $day['order'], CarbonImmutable::parse($match['confidence_date'], 'Africa/Johannesburg'));
            $won = (int) $match['winner'] === (int) $player->id;
            $opponent = (int) ($won ? $match['loser'] : $match['winner']);
            $detail = $details[$match['source_id']] ?? null; $side = $detail ? array_search($opponent, $detail['players'], true) : false;
            $score = $detail ? (string) $detail['score'] : '';
            if ($side === 0) $score = preg_replace_callback('/(\d+)-(\d+)/', fn ($m) => $m[2].'-'.$m[1], $score);
            if (!$detail || $side === false || $detail['date'] !== $match['confidence_date'] || (((int) $detail['winner_side'] === 2 - $side) !== $won)) {
                $pair['before_score'] = $pair['after_score'] = $pair['delta'] = null; $pair['reason'] = 'Matching saved result details are unavailable.';
                $pair['explanation'] = null;
                $pair['own_sources'] = [];
            }
            $members = $pair['own_sources'] ?? []; unset($pair['own_sources']);
            usort($members, fn ($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['source_id'], $b['source_id']));
            $selectedMembers = [...array_slice(array_values(array_filter($members, fn ($m) => $m['before'])), 0, PlayerMatchProgressStore::EVIDENCE_LIMIT),
                ...array_values(array_filter($members, fn ($m) => !$m['before']))];
            $omitted = count($members) - count($selectedMembers);
            $refs = [];
            foreach ($selectedMembers as $member) {
                $id = $member['source_id']; $refs[] = array_intersect_key($member, array_flip(['source_id', 'before', 'after', 'opponent_id', 'opponent_before', 'opponent_after']));
                $entry = $day['details'][$id] ?? null;
                if (!$entry) continue;
                $ownSide = array_search((int) $player->id, $entry['players'], true); if ($ownSide === false) continue;
                if ((int) ($entry['players'][1 - $ownSide] ?? 0) !== $member['opponent_id'] || $entry['date'] !== $member['date']) continue;
                $name = $entry['names'][1 - $ownSide] ?? '';
                if ($name === '' || preg_match('/^Player #\d+$/', $name)) continue;
                $ownScore = (string) $entry['score'];
                if ($ownSide === 1) $ownScore = preg_replace_callback('/(\d+)-(\d+)/', fn ($m) => $m[2].'-'.$m[1], $ownScore);
                $catalog[$id] = ['date' => $entry['date'], 'opponent' => $name, 'score' => $ownScore, 'won' => $entry['winner_side'] === $ownSide + 1];
            }
            $pair['evidence_refs'] = $refs; $pair['omitted_matches'] = $omitted;
            $rows[] = ['source_id' => $match['source_id'], 'date' => $match['confidence_date'], 'won' => $won,
                'opponent' => $side !== false ? (string) ($detail['names'][$side] ?? 'Opponent') : 'Match details unavailable', 'score' => $score] + $pair;
        }
        if (!hash_equals($fingerprint, $this->ability->fingerprint())) throw new \RuntimeException('Sources changed during reconstruction.');
        return ['version' => PlayerMatchProgressStore::VERSION, 'basis' => 'scheduled_match_order', 'player_id' => (int) $player->id,
            'policy_hash' => $this->store->policyHash(), 'source_fingerprint' => $fingerprint, 'snapshot_version' => $snapshot['snapshot_version'],
            'as_of' => $asOf, 'built_at' => CarbonImmutable::now('Africa/Johannesburg')->toIso8601String(), 'current_score' => round((float) $currentScore, 1), 'own_catalog' => $catalog, 'rows' => $rows];
    }

    public function current(array $saved, bool $freshSnapshot = false): bool
    {
        $snapshot = $freshSnapshot ? $this->snapshots->freshCurrent() : $this->snapshots->current();
        return ($snapshot['reason'] ?? null) === null && $saved['as_of'] === CarbonImmutable::today('Africa/Johannesburg')->toDateString()
            && $saved['as_of'] === ($snapshot['snapshot_as_of'] ?? null) && hash_equals($saved['snapshot_version'], $snapshot['snapshot_version'] ?? '')
            && hash_equals($saved['source_fingerprint'], $this->ability->fingerprint())
            && (float) $saved['current_score'] === round((float) ($snapshot['all_age']['ratings'][$saved['player_id']]['score'] ?? -1), 1);
    }
}
