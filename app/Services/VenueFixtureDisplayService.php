<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Services\Scheduling\TeamFixtureOrder;

/** Read-only grouping of the fixtures assigned to one venue. */
class VenueFixtureDisplayService
{
    public function groups(Collection $fixtures): Collection
    {
        $order = app(TeamFixtureOrder::class);
        $groups = $fixtures->groupBy(function ($fixture) {
            $tie = $fixture->teamTie;
            if ($tie && (int) $tie->draw_id === (int) $fixture->draw_id) {
                return 'tie:'.$fixture->draw_id.':'.$tie->id;
            }
            if (!$fixture->region1 || !$fixture->region2) {
                return 'fixture:'.$fixture->id;
            }
            $regions = [(int) $fixture->region1, (int) $fixture->region2];
            sort($regions);
            return implode(':', ['legacy', $fixture->draw_id, $fixture->round_nr, ...$regions]);
        })->map(function ($rows) use ($order) {
            $rows = $order->sort($rows);
            return [
                'fixture' => $rows->first(),
                'start' => $rows->first()->scheduled_at,
                'waves' => $rows->groupBy(fn ($fixture) => $fixture->scheduled_at?->format('Y-m-d H:i:s') ?? 'TBC')->values(),
            ];
        })->sort(fn ($left, $right) => $order->compare($left['fixture'], $right['fixture']))->values();

        return $groups->map(function ($group) use ($groups) {
            // Simultaneous ties are not the next start; find the next later wave of ties.
            $group['next_start'] = $group['start'] ? $groups->first(fn ($candidate) => $candidate['start']
                && $candidate['start']->greaterThan($group['start']))['start'] ?? null : null;
            return $group;
        });
    }
}
