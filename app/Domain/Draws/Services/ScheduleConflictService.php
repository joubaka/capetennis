<?php

namespace App\Domain\Draws\Services;

use App\Models\Draw;
use App\Models\Fixture;
use Carbon\Carbon;

final class ScheduleConflictService
{
    public function conflict(Draw $draw, Fixture $fixture, int $venueId, string $court, string $start, int $duration = 75): ?string
    {
        // Manual edits retain the booking's existing turnaround gap.
        $duration += (int) ($fixture->orderOfPlay?->gap_minutes ?? 0);
        if ($draw->usesFlexibleMonrad()) {
            $conflict = app(\App\Services\Draw\FlexibleMonradScheduler::class)->conflict($draw, $fixture, $start, $duration);
            if ($conflict) return $conflict;
        } else {
            $conflict = app(\App\Services\ScheduleEngine::class)->dependencyConflict($draw, $fixture, $start, $duration);
            if ($conflict) return $conflict;
        }
        if ($draw->venues->isNotEmpty() && ! $draw->venues->contains('id', $venueId)) {
            return 'The selected venue is not assigned to this draw.';
        }

        $from = Carbon::parse($start);
        $candidateIds = [$fixture->registration1_id, $fixture->registration2_id];
        if ($draw->usesFlexibleMonrad()) {
            $matches = (array) app(\App\Services\Draw\FlexibleMonradService::class)->state($draw)['matches'];
            foreach (ScheduleAvailability::participants($matches) as $key => $ids) {
                if ($matches[$key]['id'] === $fixture->id) $candidateIds = $ids;
            }
        } else {
            $candidateIds = ScheduleAvailability::legacyParticipants($draw->drawFixtures()->get()->keyBy('id'))[$fixture->id];
        }
        $calendar = ScheduleAvailability::load([$venueId], $candidateIds, [$fixture->id], $draw);
        if ($calendar->nextAvailable($from, $duration, $venueId, $court, $candidateIds)->gt($from)) {
            return 'Schedule conflict: the court or a participant is already booked during this time.';
        }
        return null;
    }
}
