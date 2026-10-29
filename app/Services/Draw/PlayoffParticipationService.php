<?php

namespace App\Services\Draw;

use App\Models\Draw;

class PlayoffParticipationService
{
    /** Keep template stages for planning, but omit unused stages once players are assigned. */
    public function isDormant(Draw $draw, array $config): bool
    {
        if (! $draw->exists) return false;
        $draw->loadMissing('groups.groupRegistrations');
        $counts = $draw->groups->map(fn ($group) => $group->groupRegistrations->count());
        if ($counts->sum() === 0 || collect($config['positions'] ?? [])->contains(fn ($position) => $counts->contains(fn ($count) => $count >= (int) $position))) {
            return false;
        }

        // Existing matches retain their identity, results and schedule even if a roster changes.
        return ! $draw->drawFixtures()->where('stage', strtoupper($config['slug'] ?? 'MAIN'))->exists();
    }
}
