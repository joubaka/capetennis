<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** Evidence sufficiency heuristic, never calibrated accuracy or win probability. */
class SharedAbilityConfidencePolicy
{
    public const VERSION = 1;

    public function decay(string $date, CarbonImmutable $asOf): float
    {
        return pow(0.5, max(0, CarbonImmutable::parse($date)->diffInDays($asOf, false)) / 90);
    }

    public function evaluate(array $own, array $component, CarbonImmutable $asOf): array
    {
        $played = $own['confidence_matches'] ?? [];
        $effective = 0; $recent = 0; $opponents = []; $events = []; $last = null; $proxy = 0;
        foreach ($played as $match) {
            if ($match['date'] > $asOf->toDateString()) { continue; }
            $weight = $this->decay($match['date'], $asOf);
            $effective += $weight;
            if (CarbonImmutable::parse($match['date'])->diffInDays($asOf, false) <= 90) { $recent++; }
            $opponents[$match['opponent']] = max($opponents[$match['opponent']] ?? 0, $weight);
            $events[$match['event']] = max($events[$match['event']] ?? 0, $weight);
            $last = max($last ?? '', $match['date']);
            if ($match['basis'] !== 'scheduled match date') { $proxy++; }
        }
        $finish = ($own['last_finish'] ?? '') <= $asOf->toDateString() ? ($own['last_finish'] ?? null) : null;
        $lastEvidence = max($last ?? '', $finish ?? '') ?: null;
        $finishFloor = $finish ? 15 * $this->decay($finish, $asOf) : 0;
        if (!$last) {
            $index = $finishFloor;
        } else {
            $index = (50 * min(1, $effective / 12) + 25 * min(1, array_sum($opponents) / 6)
                + 25 * min(1, array_sum($events) / 3)) * $this->decay($last, $asOf);
            $cap = $effective < 3 ? 25 : ($effective < 5 ? 45 : 100);
            if (array_sum($opponents) < 3 || array_sum($events) < 2) { $cap = min($cap, 65); }
            if (($component['inferred'] ?? 0) || ($component['bridge_count'] ?? 0)) { $cap = min($cap, 75); }
            $index = min($index, $cap);
        }
        $index = max($index, $finishFloor);
        $index = (int) round(max(0, min(100, $index)));
        return ['confidence_index' => $index, 'confidence_band' => $index < 20 ? 'Very low' : ($index < 40 ? 'Low' : ($index < 70 ? 'Moderate' : 'Stronger')),
            'confidence_as_of' => $asOf->toDateString(), 'last_direct_match' => $last, 'last_eligible_activity' => $lastEvidence,
            'recent_played' => $recent, 'effective_played' => round($effective, 4), 'direct_opponents' => count($opponents),
            'played_events' => count($events), 'proxy_dated_matches' => $proxy];
    }
}
