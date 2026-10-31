<?php

namespace App\Services\Scheduling;

use App\Models\{Draw, Fixture};

/** Resolve playing time separately from the court's changeover reservation. */
final class MatchTimingService
{
    public function forDraw(Draw $draw, int $defaultDuration = 60, int $defaultGap = 5, bool $explicitBookingTiming = false): array
    {
        $draw->loadMissing('settings');
        if ($draw->settings?->score_format !== 'timed' || $draw->isTeamDraw()) {
            return ['duration_minutes' => $defaultDuration, 'court_gap_minutes' => $defaultGap];
        }

        $duration = $explicitBookingTiming ? $defaultDuration : (int) $draw->settings->match_duration_minutes;
        $gap = $explicitBookingTiming ? $defaultGap : $draw->settings->changeover_minutes;
        if ($duration < 1 || $duration > 240 || $gap === null || (int) $gap < 0 || (int) $gap > 60) {
            throw new \InvalidArgumentException('Save a valid playing time and changeover gap in this draw’s timed match settings before scheduling.');
        }

        return ['duration_minutes' => $duration, 'court_gap_minutes' => (int) $gap];
    }

    public function forFixture(Fixture $fixture, int $defaultDuration = 60, int $defaultGap = 5): array
    {
        return $this->forDraw($fixture->draw, $defaultDuration, $defaultGap);
    }

    /** Private scoring uses the saved match booking; unscheduled matches use draw defaults. */
    public function forBookedFixture(Fixture $fixture): array
    {
        $fixture->loadMissing('draw.settings', 'orderOfPlay');
        $slot = $fixture->orderOfPlay;
        if ($fixture->draw->settings?->score_format === 'timed' && $slot && (int) $slot->duration_minutes > 0) {
            return ['duration_minutes' => (int) $slot->duration_minutes,
                // Published projections carry their published duration, but older
                // snapshots have no gap. Never fetch the private working booking.
                'court_gap_minutes' => array_key_exists('gap_minutes', $slot->getAttributes())
                    ? (int) ($slot->gap_minutes ?? 0) : null];
        }
        return $this->forFixture($fixture);
    }
}
