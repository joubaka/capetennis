<?php

namespace App\Domain\Draws\Services;

use App\Models\Draw;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use Carbon\Carbon;

final class ScheduleConflictService
{
    public function conflict(Draw $draw, Fixture $fixture, int $venueId, string $court, string $start, int $duration = 75): ?string
    {
        if ($draw->venues->isNotEmpty() && ! $draw->venues->contains('id', $venueId)) {
            return 'The selected venue is not assigned to this draw.';
        }

        $from = Carbon::parse($start);
        $to = $from->copy()->addMinutes($duration);
        $candidateIds = [$fixture->registration1_id, $fixture->registration2_id];

        $existing = OrderOfPlay::with('fixture')
            ->where('draw_id', $draw->id)
            ->where('fixture_id', '!=', $fixture->id)
            ->whereNotNull('time')
            ->get();

        foreach ($existing as $slot) {
            $slotFrom = Carbon::parse($slot->time);
            $slotTo = $slotFrom->copy()->addMinutes((int) ($slot->duration_minutes ?: 75));
            $overlap = $from->lt($slotTo) && $to->gt($slotFrom);
            if (! $overlap) continue;
            $sameCourt = (int) $slot->venue_id === $venueId && (string) $slot->court === (string) $court;
            $slotPlayers = [$slot->fixture?->registration1_id, $slot->fixture?->registration2_id];
            $samePlayer = array_intersect(array_filter($candidateIds), array_filter($slotPlayers)) !== [];
            if ($sameCourt || $samePlayer) {
                return $sameCourt
                    ? 'Schedule conflict: the court is already occupied during this time.'
                    : 'Schedule conflict: a participant is scheduled in an overlapping match.';
            }
        }

        return null;
    }
}
