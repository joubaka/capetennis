<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** Offline leave-one-source-out estimates on the canonical Overall CTR scale. */
class PlayerMatchInfluenceCalculator
{
    public const LIMIT = 5;
    public const MAX_MATCHES = 50000;

    public function __construct(private AllAgeRatingModel $model) {}

    public function calculate(int $player, array $savedGraph, CarbonImmutable $asOf): array
    {
        $matches = $savedGraph['history_evidence']['matches'] ?? [];
        if (count($matches) > self::MAX_MATCHES || ($savedGraph['history_evidence']['fields'] ?? []) !== []) {
            throw new \RuntimeException('Saved Overall CTR evidence is unavailable for this calculation.');
        }
        // Refit all canonical sources first, including canonical recency and event budgeting.
        $baseline = $this->model->fit($matches, $asOf);
        $score = $this->score($savedGraph, $player);
        if ($score === null || $this->score($baseline, $player) !== $score || $this->context($savedGraph, $player) !== $this->context($baseline, $player)) {
            throw new \RuntimeException('Saved Overall CTR cannot be reproduced on the same comparison scale.');
        }
        $eligible = array_values(array_filter($baseline['history_evidence']['matches'] ?? [],
            fn ($match) => (int) $match['winner'] === $player || (int) $match['loser'] === $player));
        // Dates may be day-level evidence. The source ID is a deterministic tie-break, not a playing-time claim.
        usort($eligible, fn ($a, $b) => strcmp($b['confidence_date'], $a['confidence_date']) ?: strcmp($a['source_id'], $b['source_id']));
        $rows = [];
        foreach (array_slice($eligible, 0, self::LIMIT) as $match) {
            $row = ['source_id' => $match['source_id'], 'date' => $match['confidence_date'],
                'won' => (int) $match['winner'] === $player, 'opponent_id' => (int) ((int) $match['winner'] === $player ? $match['loser'] : $match['winner']),
                'delta' => null, 'without_score' => null, 'reason' => null];
            try {
                $without = $this->model->fit(array_values(array_filter($matches, fn ($candidate) => $candidate['source_id'] !== $match['source_id'])), $asOf);
                $withoutScore = $this->score($without, $player);
                if ($withoutScore === null || $this->context($without, $player) !== $this->context($baseline, $player)) {
                    $row['reason'] = 'Removing this match changes the comparison scale or leaves no converged comparable estimate.';
                } else {
                    $row['without_score'] = $withoutScore;
                    $row['delta'] = round($score - $withoutScore, 1);
                }
            } catch (\Throwable) {
                $row['reason'] = 'A comparable estimate without this match is unavailable.';
            }
            $rows[] = $row;
        }

        return ['baseline_score' => $score, 'rows' => $rows];
    }

    private function score(array $graph, int $player): ?float
    {
        $rating = $graph['ratings'][$player] ?? [];
        $component = $graph['components'][$rating['component'] ?? ''] ?? [];
        $score = $rating['score'] ?? null;
        if (($rating['comparable'] ?? false) !== true || ($component['converged'] ?? false) !== true
            || !is_numeric($score) || !is_finite((float) $score) || $score < 0 || $score > 100) return null;

        return round((float) $score, 1);
    }

    private function context(array $graph, int $player): array
    {
        $rating = $graph['ratings'][$player] ?? [];
        $component = $rating['component'] ?? null;
        $reference = $graph['reference_component'] ?? null;
        $members = static function (array $ids): array { $ids = array_map('intval', $ids); sort($ids, SORT_NUMERIC); return $ids; };

        return ['component' => $component, 'members' => $members($graph['components'][$component ?? '']['members'] ?? []),
            'reference' => $reference, 'reference_members' => $members($graph['components'][$reference ?? '']['members'] ?? []),
            'backbone_members' => $members($graph['recent_backbone']['members'] ?? []),
            'connection_status' => $rating['connection_status'] ?? null, 'historical_connection' => $rating['historical_connection'] ?? null];
    }
}
