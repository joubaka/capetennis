<?php

namespace App\Services\Performance;

use App\Models\{Player, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Builds explicitly off-request. Authorized page reads only validate and project a saved artifact. */
class PlayerMatchInfluence
{
    public function __construct(private PlayerMatchInfluenceCalculator $calculator, private PlayerMatchInfluenceStore $store,
        private PlayerAbilitySnapshotStore $snapshots, private PlayerRatingHistoryStore $history, private PlayerSharedAbilityService $ability,
        private PlayerRatingAccess $access) {}

    public function forPlayer(User $user, Player $player): array
    {
        abort_unless($this->access->allows($user, $player), 403);
        $empty = ['rows' => [], 'as_of' => null, 'baseline_score' => null, 'restricted' => 0,
            'reason' => 'Estimated match influence is awaiting a saved update.'];
        $saved = $this->store->read((int) $player->id);
        if (!$saved || $saved['player_id'] !== (int) $player->id) return $empty;
        try {
            if (!$this->current($saved)) return $empty;
        } catch (\Throwable) { return $empty; }
        $rows = [];
        $restricted = 0;
        foreach ($saved['rows'] as $row) {
            // Match history already withholds private staged TeamSheet evidence from ordinary members.
            if (str_starts_with($row['source_id'], 'teamsheet:') && !$user->hasRole('super-user')) { $restricted++; continue; }
            $rows[] = array_intersect_key($row, array_flip(['date', 'opponent', 'score', 'won', 'delta', 'without_score', 'reason']));
        }

        return ['rows' => $rows, 'as_of' => $saved['as_of'], 'baseline_score' => $saved['baseline_score'], 'restricted' => $restricted, 'reason' => null];
    }

    /** Only the explicit offline command may call this expensive calculation. */
    public function build(Player $player): array
    {
        $fingerprint = $this->ability->fingerprint();
        $snapshot = $this->snapshots->current();
        $history = $this->history->read();
        $asOf = $snapshot['snapshot_as_of'] ?? null;
        if (($snapshot['reason'] ?? null) !== null || !$asOf || !$history || $history['as_of'] !== $asOf
            || !hash_equals($fingerprint, $history['source_fingerprint'])
            || !hash_equals($fingerprint, (string) DB::table('player_ability_snapshots')->where('snapshot_key', 'current')->value('source_fingerprint'))
            || !isset($history['publication_manifest']) || !$this->snapshots->published($history['publication_manifest'])) {
            throw new \RuntimeException('Current matching saved ratings and history are required before estimating match influence.');
        }
        $calculated = $this->calculator->calculate((int) $player->id, $snapshot['all_age'] ?? [], CarbonImmutable::parse($asOf, 'Africa/Johannesburg'));
        $details = [];
        foreach ($history['evidence'] as $row) {
            if (($row['kind'] ?? null) !== 'match' || ($row['cohort'] ?? null) !== AllAgeRatingModel::COHORT
                || !in_array((int) $player->id, $row['players'] ?? [], true) || !(($row['months'] ?? 0) & (1 << 11))) continue;
            $details[$row['source_id']] = $row;
        }
        $rows = [];
        foreach ($calculated['rows'] as $row) {
            $detail = $details[$row['source_id']] ?? null;
            $side = $detail ? array_search($row['opponent_id'], $detail['players'], true) : false;
            $row['opponent'] = $side !== false ? (string) ($detail['names'][$side] ?? 'Opponent') : 'Match details unavailable';
            $row['score'] = $detail ? (string) $detail['score'] : '';
            if ($side === 0) $row['score'] = preg_replace_callback('/(\d+)-(\d+)/', fn ($pair) => $pair[2].'-'.$pair[1], $row['score']);
            if (!$detail || $side === false || $detail['date'] !== $row['date'] || (((int) $detail['winner_side'] === 2 - $side) !== $row['won'])) {
                $row['delta'] = $row['without_score'] = null;
                $row['reason'] = 'Matching saved result details are unavailable.';
            }
            unset($row['opponent_id']);
            $rows[] = $row;
        }
        if (!hash_equals($fingerprint, $this->ability->fingerprint())) throw new \RuntimeException('Sources changed during the match influence calculation.');

        return ['version' => PlayerMatchInfluenceStore::VERSION, 'player_id' => (int) $player->id, 'policy_hash' => $this->store->policyHash(),
            'source_fingerprint' => $fingerprint, 'snapshot_version' => $snapshot['snapshot_version'], 'as_of' => $asOf,
            'built_at' => CarbonImmutable::now('Africa/Johannesburg')->toIso8601String(),
            'baseline_score' => $calculated['baseline_score'], 'rows' => $rows];
    }

    /** Also checked immediately before an offline atomic replacement. Never calculates a rating. */
    public function current(array $saved, bool $freshSnapshot = false): bool
    {
        // The container scopes its reader. A writer must bypass that cached baseline before replacement.
        $snapshot = $freshSnapshot ? $this->snapshots->freshCurrent() : $this->snapshots->current();
        return ($snapshot['reason'] ?? null) === null
            && $saved['as_of'] === CarbonImmutable::today('Africa/Johannesburg')->toDateString()
            && $saved['as_of'] === ($snapshot['snapshot_as_of'] ?? null)
            && hash_equals($saved['snapshot_version'], $snapshot['snapshot_version'] ?? '')
            && hash_equals($saved['source_fingerprint'], $this->ability->fingerprint())
            && (float) $saved['baseline_score'] === round((float) ($snapshot['all_age']['ratings'][$saved['player_id']]['score'] ?? -1), 1);
    }
}
