<?php

namespace App\Services\Scheduling;

use App\Models\{Draw, Fixture};

/** Resolve playing time separately from the court's changeover reservation. */
final class MatchTimingService
{
    public function forDraw(Draw $draw, int $defaultDuration = 60, int $defaultGap = 5): array
    {
        $draw->loadMissing('settings');
        if ($draw->settings?->score_format !== 'timed' || $draw->isTeamDraw()) {
            return ['duration_minutes' => $defaultDuration, 'court_gap_minutes' => $defaultGap];
        }

        $duration = (int) $draw->settings->match_duration_minutes;
        $gap = $draw->settings->changeover_minutes;
        if ($duration < 1 || $duration > 240 || $gap === null || (int) $gap < 0 || (int) $gap > 60) {
            throw new \InvalidArgumentException('Save a valid playing time and changeover gap in this draw’s timed match settings before scheduling.');
        }

        return ['duration_minutes' => $duration, 'court_gap_minutes' => (int) $gap];
    }

    public function forFixture(Fixture $fixture, int $defaultDuration = 60, int $defaultGap = 5): array
    {
        return $this->forDraw($fixture->draw, $defaultDuration, $defaultGap);
    }
}
