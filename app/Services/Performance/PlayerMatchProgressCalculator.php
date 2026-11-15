<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** Offline chronological prefix reconstructions; never archived or leave-one-out ratings. */
class PlayerMatchProgressCalculator
{
    public const LIMIT = 5;
    public const MAX_MATCHES = 50000;

    public function __construct(private AllAgeRatingModel $model) {}

    public function latest(int $player, array $matches, array $chronology): array
    {
        $own = array_values(array_filter($matches, fn ($m) => (int) $m['winner'] === $player || (int) $m['loser'] === $player));
        usort($own, function ($a, $b) use ($chronology) {
            $day = strcmp($b['confidence_date'], $a['confidence_date']);
            if ($day) return $day;
            $aTime = $chronology[$a['source_id']]['time'] ?? null;
            $bTime = $chronology[$b['source_id']]['time'] ?? null;
            if (($aTime !== null) !== ($bTime !== null)) return ($bTime !== null) <=> ($aTime !== null);
            if ($aTime !== null && $bTime !== null && $aTime !== $bTime) return strcmp($bTime, $aTime);
            // Ambiguous rows remain explicitly unavailable; this tie-break only stabilizes their display.
            return strcmp($a['source_id'], $b['source_id']);
        });
        return array_slice($own, 0, self::LIMIT);
    }

    public function pair(int $player, string $source, array $matches, array $chronology, CarbonImmutable $day): array
    {
        $empty = ['time' => $chronology[$source]['time'] ?? null, 'before_score' => null, 'after_score' => null, 'delta' => null, 'reason' => null, 'explanation' => null, 'own_sources' => []];
        if (count($matches) > self::MAX_MATCHES) throw new \OverflowException('Historical evidence exceeds its bounded limit.');
        $target = null;
        foreach ($matches as $match) if ($match['source_id'] === $source) { $target = $match; break; }
        $time = $empty['time'];
        if (!$target || ((int) $target['winner'] !== $player && (int) $target['loser'] !== $player)
            || !$time || substr($time, 0, 10) !== $day->toDateString() || $target['confidence_date'] !== $day->toDateString()) {
            return array_replace($empty, ['reason' => 'A unique scheduled match time is unavailable.']);
        }
        $before = [];
        foreach ($matches as $match) {
            if ($match['source_id'] === $source || $match['confidence_date'] > $day->toDateString()) continue;
            $order = $chronology[$match['source_id']] ?? null;
            if (!$order) return array_replace($empty, ['reason' => 'Other eligible evidence has no reliable ordering information.']);
            if ($order['time'] !== null) {
                if ($order['time'] === $time) return array_replace($empty, ['reason' => 'Eligible matches share this scheduled time; their order is ambiguous.']);
                if ($order['time'] < $time) $before[] = $match;
            } elseif ($order['latest_date'] < $day->toDateString()) {
                $before[] = $match;
            } elseif ($order['earliest_date'] <= $day->toDateString()) {
                return array_replace($empty, ['reason' => 'Other eligible evidence may fall before or after this match; its order is unknown.']);
            }
        }
        try {
            // Identical day-based recency policy on both sides; no later match enters either prefix.
            $pre = $this->model->fit($before, $day);
            $post = $this->model->fit([...$before, $target], $day);
            $preScore = $this->score($pre, $player); $postScore = $this->score($post, $player);
            if ($preScore === null || $postScore === null || $this->context($pre, $player) !== $this->context($post, $player)) {
                return array_replace($empty, ['reason' => 'A stable converged comparison scale is unavailable on both sides of this match.']);
            }
            $acceptedBefore = $pre['history_evidence']['matches'] ?? [];
            $acceptedAfter = $post['history_evidence']['matches'] ?? [];
            $beforeIds = array_column($acceptedBefore, 'source_id'); $afterIds = array_column($acceptedAfter, 'source_id');
            $expectedIds = [...$beforeIds, $source]; sort($expectedIds); $sortedAfter = $afterIds; sort($sortedAfter);
            if (count(array_unique($beforeIds)) !== count($beforeIds) || count(array_unique($afterIds)) !== count($afterIds) || $expectedIds !== $sortedAfter) return array_replace($empty, ['reason' => 'The accepted evidence change could not be verified.']);
            $own = array_filter($acceptedBefore, fn ($m) => (int) $m['winner'] === $player || (int) $m['loser'] === $player);
            $opponents = array_unique(array_map(fn ($m) => (int) $m['winner'] === $player ? (int) $m['loser'] : (int) $m['winner'], $own));
            $targetOpponent = (int) $target['winner'] === $player ? (int) $target['loser'] : (int) $target['winner'];
            $opponentPairs = [];
            return array_replace($empty, ['before_score' => $preScore, 'after_score' => $postScore, 'delta' => round($postScore - $preScore, 1),
                'own_sources' => array_map(function ($m) use ($player, $source, $pre, $post, &$opponentPairs) {
                    $opponent = (int) $m['winner'] === $player ? (int) $m['loser'] : (int) $m['winner'];
                    if (!isset($opponentPairs[$opponent])) {
                        $beforeOpponent = $this->score($pre, $opponent); $afterOpponent = $this->score($post, $opponent);
                        if ($beforeOpponent === null || $afterOpponent === null || $this->context($pre, $opponent) !== $this->context($post, $opponent)) $beforeOpponent = $afterOpponent = null;
                        $opponentPairs[$opponent] = [$beforeOpponent, $afterOpponent];
                    }
                    [$beforeOpponent, $afterOpponent] = $opponentPairs[$opponent];
                    return ['source_id' => $m['source_id'], 'date' => $m['confidence_date'], 'before' => $m['source_id'] !== $source, 'after' => true,
                        'opponent_id' => $opponent, 'opponent_before' => $beforeOpponent, 'opponent_after' => $afterOpponent];
                },
                    array_values(array_filter($acceptedAfter, fn ($m) => (int) $m['winner'] === $player || (int) $m['loser'] === $player))),
                'explanation' => ['network_before' => count($acceptedBefore), 'network_after' => count($acceptedAfter),
                    'own_before' => count($own), 'own_after' => count($own) + 1,
                    'opponents_before' => count($opponents), 'opponents_after' => count(array_unique([...$opponents, $targetOpponent]))]]);
        } catch (\Throwable) {
            return array_replace($empty, ['reason' => 'A comparable chronological reconstruction is unavailable.']);
        }
    }

    private function score(array $graph, int $player): ?float
    {
        $r = $graph['ratings'][$player] ?? []; $s = $r['score'] ?? null;
        if (($r['comparable'] ?? false) !== true || ($graph['components'][$r['component'] ?? '']['converged'] ?? false) !== true
            || !is_numeric($s) || !is_finite((float) $s) || $s < 0 || $s > 100) return null;
        return round((float) $s, 1);
    }

    private function context(array $graph, int $player): array
    {
        $r = $graph['ratings'][$player] ?? []; $ref = $graph['reference_component'] ?? null;
        $sorted = static function ($ids) { $ids = array_map('intval', $ids); sort($ids, SORT_NUMERIC); return $ids; };
        return [$r['component'] ?? null, $sorted($graph['components'][$r['component'] ?? '']['members'] ?? []), $ref,
            $sorted($graph['components'][$ref ?? '']['members'] ?? []), $sorted($graph['recent_backbone']['members'] ?? []),
            $r['connection_status'] ?? null, $r['historical_connection'] ?? null];
    }
}
