<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Collection;

/** Shared, publication-safe event source for public calendar consumers. */
class PublicEventCalendarService
{
    public function upcoming(int $limit = 100): Collection
    {
        return Event::query()
            ->select(['id', 'name', 'information', 'start_date', 'end_date', 'updated_at'])
            ->whereIn('published', [1, 'published', true])
            ->whereDate('end_date', '>=', today())
            ->orderBy('start_date')
            ->orderBy('id')
            ->limit(max(1, min(100, $limit)))
            ->get();
    }
}
