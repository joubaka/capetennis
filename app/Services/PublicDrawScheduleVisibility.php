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

        return app(\App\Services\Scheduling\SchedulePublicationService::class)->publishedRows($draw->event)
            ->where('draw_id', $draw->id)->where('fixture_kind', 'individual')->pluck('fixture_id')
            ->map(fn ($id) => (int) $id)->values();
    }

    public function restrictRoundRobinHub(Draw $draw, array $hub): array
    {
        $visibleIds = $this->visibleFixtureIds($draw);
        $published = app(\App\Services\Scheduling\SchedulePublicationService::class)->publishedRows($draw->event)
            ->where('draw_id', $draw->id)->where('fixture_kind', 'individual')->keyBy('fixture_id');
        $project = function (array $fixture) use ($published, $visibleIds): array {
            $row = $published->get((int) ($fixture['id'] ?? 0));
            if (! $row || ($visibleIds !== null && ! $visibleIds->contains((int) ($fixture['id'] ?? 0)))) {
                $fixture['time'] = null; $fixture['court'] = null; $fixture['venue_id'] = null;
                $fixture['venue'] = null; $fixture['venue_name'] = null; $fixture['scheduled_at'] = null; $fixture['scheduled_date'] = null; $fixture['schedule_hidden'] = true;
                return $fixture;
            }
            $fixture['time'] = $row['scheduled_at']; $fixture['court'] = $row['court'];
            $fixture['venue_id'] = $row['venue_id']; $fixture['venue'] = $row['venue_name']; $fixture['venue_name'] = $row['venue_name']; $fixture['scheduled_at'] = $row['scheduled_at'];
            $fixture['scheduled_date'] = substr($row['scheduled_at'], 0, 10);
            $fixture['schedule_hidden'] = false;
            return $fixture;
        };
        foreach ($hub['rrFixtures'] as &$groupFixtures) {
            foreach ($groupFixtures as &$fixture) $fixture = $project($fixture);
            unset($fixture);
        }
        unset($groupFixtures);
        $hub['oops'] = collect($hub['oops'])->map($project);

        return $hub;
    }

    private function hideScheduledTime(array $fixture): array
    {
        if (empty($fixture['time'])) {
            return $fixture;
        }

        $fixture['schedule_hidden'] = true;
        $fixture['scheduled_date'] = date('Y-m-d', strtotime((string) $fixture['time']));
        $fixture['time'] = null;

        if (array_key_exists('court', $fixture)) {
            $fixture['court'] = null;
        }

        return $fixture;
    }
}
