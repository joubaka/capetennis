<?php

namespace App\Domain\Draws\Services;

use App\Models\Fixture;

final class ScoreValidationService
{
    /**
     * Validates the safe common denominator for configured tennis formats.
     * Format-specific overrides can be added without changing controllers.
     */
    public function validate(Fixture $fixture, array $sets): array
    {
        if ($sets === [] || count($sets) > 3) {
            return ['valid' => false, 'message' => 'Enter between one and three sets.'];
        }

        foreach ($sets as $index => [$home, $away]) {
            $exceedsRange = $index === 2
                ? $home > 999 || $away > 999
                : $home > 20 || $away > 20;
            if ($home < 0 || $away < 0 || $exceedsRange || $home === $away) {
                return ['valid' => false, 'message' => 'Each set must have different, non-negative scores within the valid range.'];
            }
        }

        $homeSets = collect($sets)->where(fn ($set) => $set[0] > $set[1])->count();
        $awaySets = count($sets) - $homeSets;
        if ($homeSets === $awaySets) {
            return ['valid' => false, 'message' => 'The entered sets do not produce a match winner.'];
        }

        return ['valid' => true, 'message' => null];
    }
}
