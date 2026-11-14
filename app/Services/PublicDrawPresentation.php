<?php

namespace App\Services;

use App\Models\Draw;
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Support\Collection;

/** Read-only summaries use exactly the rows a visitor can see in the schedule. */
final class PublicDrawPresentation
{
    public function summary(Draw $draw, ?Collection $rows = null): array
    {
        $rows ??= $draw->event->exists
            ? app(SchedulePublicationService::class)->publishedRows($draw->event, includeParticipants: false, drawId: $draw->id)
            : collect();
        $rows = $rows->where('event_id', $draw->event_id)->where('draw_id', $draw->id);
        if (! $draw->published || ! $draw->oop_published) $rows = collect();

        return [
            'available' => $rows->isNotEmpty(),
            'first_time' => $rows->min('scheduled_at'),
            'venues' => $rows->isNotEmpty()
                ? $rows->pluck('venue_name')->filter()->unique()->values()
                : $draw->venues->pluck('name')->filter()->unique()->values(),
            'provisional_venues' => $rows->isEmpty(),
        ];
    }
}
