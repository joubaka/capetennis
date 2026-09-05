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

        if (! $draw->settings?->showsFirstMatchOnly()) {
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

        if ($fixtures->isEmpty()) {
            return collect();
        }

        $isRoundRobin = in_array($draw->settings?->workflow, [
            'round_robin',
            'round_robin_playoffs',
        ], true) || $fixtures->contains(fn (Fixture $fixture) =>
            strtoupper((string) $fixture->stage) === 'RR' || $fixture->draw_group_id !== null
        );
        $seenRegistrationIds = [];
        $visibleFixtureIds = [];

        foreach ($fixtures as $fixture) {
            $registrationIds = collect([
                $fixture->registration1_id,
                $fixture->registration2_id,
            ])->filter(fn ($id) => (int) $id > 0)->map(fn ($id) => (int) $id)->unique();

            // Odd round robins require the union of every player's earliest
            // upcoming fixture: the player with the opening-round bye otherwise
            // receives no time because their first match is an opponent's second.
            // Other formats retain the stricter all-participants-first rule.
            if ($registrationIds->isNotEmpty()
                && ($isRoundRobin
                    ? $registrationIds->contains(fn (int $id) => ! isset($seenRegistrationIds[$id]))
                    : $registrationIds->every(fn (int $id) => ! isset($seenRegistrationIds[$id])))) {
                $visibleFixtureIds[] = (int) $fixture->id;
            }

            foreach ($registrationIds as $registrationId) {
                $seenRegistrationIds[$registrationId] = true;
            }
        }

        return collect($visibleFixtureIds)->unique()->values();
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
                    $fixture['schedule_hidden'] = true;
                    $fixture['time'] = null;
                    $fixture['venue_name'] = null;
                }
            }
            unset($fixture);
        }
        unset($groupFixtures);

        $hub['oops'] = collect($hub['oops'])->map(function (array $fixture) use ($isVisible): array {
            if (! $isVisible($fixture['id'] ?? null)) {
                $fixture['schedule_hidden'] = true;
                $fixture['time'] = null;
                $fixture['venue_name'] = null;
                $fixture['court'] = null;
            }

            return $fixture;
        });

        return $hub;
    }
}
