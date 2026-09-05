<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Collection;

class PublicDrawScheduleVisibility
{
    /**
     * Return the unplayed fixtures whose times may be exposed publicly.
     * A null result means the draw is configured to show its full schedule.
     */
    public function visibleFixtureIds(Draw $draw): ?Collection
    {
        if (! $draw->oop_published) {
            return collect();
        }

        if (! $draw->settings?->showsCurrentRoundOnly()) {
            return null;
        }

        $fixtures = $draw->drawFixtures()
            ->with(['fixtureResults', 'orderOfPlay'])
            ->whereHas('orderOfPlay', fn ($query) => $query->whereNotNull('time'))
            ->get()
            ->filter(fn (Fixture $fixture) => $fixture->fixtureResults->isEmpty())
            ->sortBy(fn (Fixture $fixture) => sprintf(
                '%s_%010d',
                $fixture->orderOfPlay?->time ?? '9999-12-31 23:59:59',
                $fixture->id
            ));

        $current = $fixtures->first();
        if (! $current) {
            return collect();
        }

        return $fixtures
            ->filter(fn (Fixture $fixture) => ($fixture->stage ?? '') === ($current->stage ?? '')
                && (int) $fixture->round === (int) $current->round)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public function restrictRoundRobinHub(Draw $draw, array $hub): array
    {
        $visibleIds = $this->visibleFixtureIds($draw);
        if ($visibleIds === null) {
            return $hub;
        }

        $isVisible = fn ($id): bool => $visibleIds->contains((int) $id);

        foreach ($hub['rrFixtures'] as &$groupFixtures) {
            foreach ($groupFixtures as &$fixture) {
                if (! $isVisible($fixture['id'] ?? null)) {
                    $fixture['time'] = null;
                    $fixture['venue_name'] = null;
                }
            }
            unset($fixture);
        }
        unset($groupFixtures);

        $hub['oops'] = collect($hub['oops'])->map(function (array $fixture) use ($isVisible): array {
            if (! $isVisible($fixture['id'] ?? null)) {
                $fixture['time'] = null;
                $fixture['venue_name'] = null;
                $fixture['court'] = null;
            }

            return $fixture;
        });

        return $hub;
    }
}
