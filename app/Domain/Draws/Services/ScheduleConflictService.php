<?php

namespace App\Domain\Draws\Services;

use App\Models\Draw;
use App\Models\Fixture;
use Carbon\Carbon;

final class ScheduleConflictService
{
    public function conflict(Draw $draw, Fixture $fixture, int $venueId, string $court, string $start,
        int $duration = 75, ?int $participantRest = null, ?int $courtGap = null,
        bool $requireCourtDurationFit = true): ?string
    {
        $timing = app(\App\Services\Scheduling\MatchTimingService::class)->forDraw($draw, $duration, $courtGap ?? (int) ($fixture->orderOfPlay?->gap_minutes ?? 0));
        $duration = $timing['duration_minutes'];
        $courtGap = $timing['court_gap_minutes'];
        if ($draw->settings?->score_format === 'timed') $requireCourtDurationFit = true;
        // Existing non-timed bookings retain their turnaround gap.
        $dailyLimit = \App\Services\Scheduling\DailyMatchLimit::configured($draw->event);
        if ($dailyLimit && ($error = $dailyLimit->error('individual:'.$fixture->id, $start))) return $error;
        $savedGap = (int) ($fixture->orderOfPlay?->gap_minutes ?? 0);
        $courtMinutes = $requireCourtDurationFit ? $duration + ($courtGap ?? $savedGap) : 1;
        $participantMinutes = $duration + ($participantRest ?? $savedGap);
        if ($draw->usesFlexibleMonrad()) {
            $conflict = app(\App\Services\Draw\FlexibleMonradScheduler::class)->conflict($draw, $fixture, $start, $participantMinutes, $participantRest);
            if ($conflict) return $conflict;
        } else {
            $conflict = app(\App\Services\ScheduleEngine::class)->dependencyConflict($draw, $fixture, $start, $participantMinutes, $participantRest);
            if ($conflict) return $conflict;
        }
        if ($error = app(\App\Services\Scheduling\RoundVenueSetup::class)->individualCourtError($fixture, $venueId, $court)) return $error;

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
        $calendar = ScheduleAvailability::load([$venueId], $candidateIds, [$fixture->id], $draw, $participantRest);
        if ($calendar->nextAvailableForMatch($from, $courtMinutes, $participantMinutes,
            $venueId, $court, $candidateIds)->gt($from)) {
            return 'Schedule conflict: the court or a participant is already booked during this time.';
        }
        return null;
    }
}
