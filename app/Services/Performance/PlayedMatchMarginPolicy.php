<?php

namespace App\Services\Performance;

/** Bounded score evidence; this is a heuristic target, not a win probability. */
final class PlayedMatchMarginPolicy
{
    public const VERSION = 2;

    public function target(array $sets, ?int $winner, ?array $types = null): float
    {
        if (!in_array($winner, [1, 2], true) || !$sets) { return 0.75; }
        $margins = [];
        foreach ($sets as $index => [$first, $second]) {
            if ($first < 0 || $second < 0 || $first === $second) { return 0.75; }
            $type = $types[$index] ?? null;
            // Custom/unknown large scores cannot safely be classified as games or points.
            if (($types !== null && ($type === null || $type === 'custom')) || ($types === null && max($first, $second) >= 10)) { return 0.75; }
            $margin = ($first - $second) / ($first + $second);
            // Average each set's dimensionless share, never add tiebreak points to games.
            $margins[] = $winner === 1 ? $margin : -$margin;
        }
        return 0.75 + 0.25 * max(0.0, min(1.0, array_sum($margins) / count($margins)));
    }
}
