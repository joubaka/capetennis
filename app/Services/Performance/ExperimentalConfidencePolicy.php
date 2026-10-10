<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** Reporting experiment only: remove the extra latest-match decay, retain all caps. */
final class ExperimentalConfidencePolicy
{
    public function evaluate(array $own, array $component, CarbonImmutable $asOf): array
    {
        $policy = app(SharedAbilityConfidencePolicy::class);
        $current = $policy->evaluate($own, $component, $asOf);
        $effective = 0.0; $opponents = []; $events = [];
        foreach ($own['confidence_matches'] ?? [] as $match) {
            if ($match['date'] > $asOf->toDateString()) { continue; }
            $weight = $policy->decay($match['date'], $asOf);
            $effective += $weight;
            $opponents[$match['opponent']] = max($opponents[$match['opponent']] ?? 0, $weight);
            $events[$match['event']] = max($events[$match['event']] ?? 0, $weight);
        }
        if (!$current['last_direct_match']) { return $current; }
        $index = 50 * min(1, $effective / 12) + 25 * min(1, array_sum($opponents) / 6) + 25 * min(1, array_sum($events) / 3);
        $cap = $effective < 3 ? 25 : ($effective < 5 ? 45 : 100);
        if (array_sum($opponents) < 3 || array_sum($events) < 2) { $cap = min($cap, 65); }
        if (($component['inferred'] ?? 0) || ($component['bridge_count'] ?? 0)) { $cap = min($cap, 75); }
        $finish = $own['last_finish'] ?? null;
        $floor = $finish && $finish <= $asOf->toDateString() ? 15 * $policy->decay($finish, $asOf) : 0;
        return array_replace($current, ['confidence_index' => (int) round(max($floor, min($index, $cap)))]);
    }
}
