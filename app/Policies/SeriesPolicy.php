<?php

namespace App\Policies;

use App\Models\Series;
use App\Models\User;

final class SeriesPolicy
{
    public function view(User $user, Series $series): bool
    {
        return $this->managesAnySeriesEvent($user, $series);
    }

    public function update(User $user, Series $series): bool
    {
        return $this->managesAnySeriesEvent($user, $series);
    }

    private function managesAnySeriesEvent(User $user, Series $series): bool
    {
        if (! $user->hasAnyRole(['admin', 'convenor'])) {
            return false;
        }

        return $series->events()
            ->pluck('id')
            ->contains(fn ($eventId) => $user->is_event_admin($eventId) || $user->is_convenor($eventId));
    }
}
