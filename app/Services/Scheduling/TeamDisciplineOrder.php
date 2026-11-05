<?php

namespace App\Services\Scheduling;

use App\Models\{Draw, Event, TeamFixture};
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Completion barriers are local to an event, age group and qualification stage. */
final class TeamDisciplineOrder
{
    public function phase(TeamFixture $fixture): int
    {
        $code = $fixture->rubber_code ?: ($fixture->draw?->team_draw_selection['rubber_code'] ?? null);
        if (! $code) $code = $fixture->draw?->drawName;
        $code = strtolower((string) $code);
        if (str_contains($code, 'mixed')) return 4;
        if (str_contains($code, 'double')) return 3;
        if (str_contains($code, 'reverse') && str_contains($code, 'single')) return 2;
        if (str_contains($code, 'single')) return 1;
        return match ((int) $fixture->fixture_type) { 2 => 3, 3 => 4, 4 => 2, default => 1 };
    }

    public function group(Draw $draw): string
    {
        $age = app(ScheduleProgramme::class)->age($draw);
        $competition = $draw->team_draw_selection['competition'] ?? [];
        // Qualification must finish before its playoff, even when the playoff starts with singles.
        $stage = ($competition['phase'] ?? null) === 'playoff'
            ? 'playoff:'.($competition['source_draw_id'] ?? $draw->id) : 'main';
        return $draw->event_id.':'.($age === null ? 'draw:'.$draw->id : 'age:'.$age).':'.$stage;
    }

    public function inventory(Event $event): Collection
    {
        return TeamFixture::without(['teamResults', 'fixturePlayers'])->with('draw.categoryEvent.category')
            ->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))->get();
    }

    public function savedOrders(Event $event): array
    {
        $stored = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        return $stored['team_discipline_order'] ?? [];
    }

    public function programmeOrders(Collection $draws, array $programme, array $savedOrders): array
    {
        if (! isset($programme['discipline_order'])) return $savedOrders;
        $age = app(ScheduleProgramme::class)->age($draws->first());
        $savedOrders[$age] = $programme['discipline_order'];
        return $savedOrders;
    }

    public function dependencies(Collection $fixtures, array $orders = [], array $dates = []): array
    {
        $drawGroups = $fixtures->unique('draw_id')->mapWithKeys(function ($fixture) use ($orders) {
            $group = $this->group($fixture->draw);
            $age = $orders ? app(ScheduleProgramme::class)->age($fixture->draw) : null;
            return [$fixture->draw_id => [$group, ($orders[$age] ?? null) === 'by_day']];
        });
        $groups = $fixtures->groupBy(function ($fixture) use ($drawGroups, $dates) {
            [$group, $byDay] = $drawGroups[$fixture->draw_id];
            if (! $byDay) return $group;
            $date = $dates[$fixture->id] ?? ($fixture->scheduled_at ? Carbon::parse($fixture->scheduled_at)->toDateString() : null);
            // An unplaced fixture cannot block a different date.
            return $group.':'.($date ?? 'unplaced:'.$fixture->id);
        });
        $dependencies = [];
        foreach ($groups as $group) {
            $phases = $group->groupBy(fn ($fixture) => $this->phase($fixture))->sortKeys();
            $previous = [];
            foreach ($phases as $phaseFixtures) {
                foreach ($phaseFixtures as $fixture) $dependencies['team:'.$fixture->id] = $previous;
                $previous = array_merge($previous, $phaseFixtures->map(fn ($fixture) => 'team:'.$fixture->id)->all());
            }
        }
        return $dependencies;
    }

    public function configureNodes(array &$nodes, Event $event, ?array $dependencies = null): void
    {
        $dependencies ??= $this->dependencies($this->inventory($event), $this->savedOrders($event));
        $drawGroups = [];
        foreach ($nodes as $key => &$node) {
            if (($node['fixture_kind'] ?? null) !== 'team') continue;
            $phaseDependencies = $dependencies[$key] ?? [];
            $node['discipline_group'] = $drawGroups[$node['draw_id']] ??= $this->group($node['fixture']->draw);
            $node['discipline_phase'] = $this->phase($node['fixture']);
            $node['discipline_dependencies'] = $phaseDependencies;
            $node['dependencies'] = array_values(array_unique(array_merge($node['dependencies'], $phaseDependencies)));
            // Finish barriers do not add rest for unrelated players. The calendar handles player rest.
            $node['day_finish_dependencies'] = $phaseDependencies;
        }
        unset($node);
    }

    public function manualError(TeamFixture $fixture, ?Carbon $start, int $duration, ?Collection $fixtures = null, ?array $dependencies = null): ?string
    {
        $fixtures ??= $this->inventory($fixture->draw->event);
        $dependencies ??= $this->dependencies($fixtures, $this->savedOrders($fixture->draw->event),
            $start ? [$fixture->id => $start->toDateString()] : []);
        $key = 'team:'.$fixture->id;
        foreach ($fixtures as $other) {
            if ($other->id === $fixture->id) continue;
            if (in_array('team:'.$other->id, $dependencies[$key] ?? [], true) && $start) {
                if (! $other->scheduled_at || Carbon::parse($other->scheduled_at)->addMinutes((int) ($other->duration_min ?: 120))->gt($start)) {
                    return 'Finish all earlier disciplines in this age group first: singles, reverse singles, doubles, then mixed.';
                }
            }
            if (in_array($key, $dependencies['team:'.$other->id] ?? [], true) && $other->scheduled_at
                && (! $start || $start->copy()->addMinutes($duration)->gt(Carbon::parse($other->scheduled_at)))) {
                return 'This change would overlap a saved later discipline in this age group. Return that discipline to planning first.';
            }
        }
        return null;
    }

    public function validateAssignments(Event $event, Collection $assignments, ?array $orders = null): void
    {
        $fixtures = $this->inventory($event)->keyBy('id');
        $changed = [];
        foreach ($assignments->where('fixture_kind', 'team') as $row) {
            $fixture = $fixtures[$row['fixture_id']] ?? null;
            if (! $fixture || (int) $fixture->draw_id !== (int) $row['draw_id']) throw new \InvalidArgumentException('A team assignment does not belong to this event draw.');
            $fixture->scheduled_at = $row['scheduled_at'];
            $fixture->duration_min = $row['duration'];
            $changed['team:'.$fixture->id] = true;
        }
        $savedOrders = $this->savedOrders($event);
        $orders ??= $savedOrders;
        $restoredStrict = array_keys(array_filter($savedOrders, fn ($mode, $age) => $mode === 'by_day'
            && ($orders[$age] ?? 'event') === 'event', ARRAY_FILTER_USE_BOTH));
        $strictDraws = $restoredStrict ? $fixtures->unique('draw_id')
            ->filter(fn ($fixture) => in_array(app(ScheduleProgramme::class)->age($fixture->draw), $restoredStrict, true))
            ->pluck('draw_id')->flip() : collect();
        $dependencies = $this->dependencies($fixtures, $orders);
        foreach ($fixtures as $later) {
            if (! $later->scheduled_at) continue;
            foreach ($dependencies['team:'.$later->id] ?? [] as $key) {
                if (! $strictDraws->has($later->draw_id) && ! isset($changed[$key]) && ! isset($changed['team:'.$later->id])) continue;
                $earlier = $fixtures[(int) substr($key, 5)];
                if (! $earlier->scheduled_at || Carbon::parse($earlier->scheduled_at)->addMinutes((int) ($earlier->duration_min ?: 120))->gt(Carbon::parse($later->scheduled_at))) {
                    throw new \InvalidArgumentException('The saved batch must finish singles, reverse singles, doubles, then mixed within each age group. Include dependent disciplines or keep their saved times valid.');
                }
            }
        }
    }
}
