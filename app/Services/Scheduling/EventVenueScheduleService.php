<?php

namespace App\Services\Scheduling;

use App\Domain\Draws\Services\ScheduleAvailability;
use App\Models\{Draw, DrawAuditLog, Event, Fixture, OrderOfPlay, Venue};
use App\Services\Draw\FlexibleMonradService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EventVenueScheduleService
{
    public function preview(Event $event, array $options): array
    {
        $start = Carbon::parse($options['start']);
        $end = ! empty($options['end']) ? Carbon::parse($options['end']) : null;
        $duration = (int) ($options['duration'] ?? 75);
        $waveMinutes = (int) ($options['wave_minutes'] ?? 90);
        $courtGap = (int) ($options['court_gap'] ?? 0);
        $playerRest = (int) ($options['player_rest'] ?? 60);
        $selectedDraws = array_map('intval', $options['draw_ids'] ?? []);
        $selectedVenues = array_map('intval', $options['venue_ids'] ?? []);
        $replanVenues = array_values(array_unique(array_map('intval', $options['replan_venue_ids'] ?? [])));
        $drawStarts = collect($options['draw_starts'] ?? [])->filter(fn ($row) => ! empty($row['start']))
            ->mapWithKeys(fn ($row) => [(int) $row['draw_id'] => Carbon::parse($row['start'])])->sortKeys();
        if (array_key_exists('draw_ids', $options) && ! $selectedDraws) {
            throw new \InvalidArgumentException('Select at least one age group or draw.');
        }
        if (array_key_exists('venue_ids', $options) && ! $selectedVenues) {
            throw new \InvalidArgumentException('Select at least one venue.');
        }

        $draws = $event->draws()->with([
            'venues' => fn ($query) => $query->withPivot('num_courts'),
            'drawFixtures.orderOfPlay', 'drawFixtures.fixtureResults', 'flexibleMonrad', 'groups',
        ])->when($selectedDraws, fn ($query) => $query->whereIn('id', $selectedDraws))->get();

        $foreignDraws = array_diff($selectedDraws, $draws->pluck('id')->map(fn ($id) => (int) $id)->all());
        if ($foreignDraws) throw new \InvalidArgumentException('One or more selected draws do not belong to this event.');
        if ($drawStarts->keys()->diff($draws->pluck('id')->map(fn ($id) => (int) $id))->isNotEmpty()) {
            throw new \InvalidArgumentException('An age-group start time belongs to an unselected draw.');
        }
        if ($drawStarts->contains(fn (Carbon $drawStart) => $drawStart->lt($start))) {
            throw new \InvalidArgumentException('An age-group start time cannot be earlier than the event schedule start.');
        }

        $venueIds = $draws->flatMap(fn (Draw $draw) => $draw->venues->pluck('id'))->unique()->values();
        if (array_diff($selectedVenues, $venueIds->map(fn ($id) => (int) $id)->all())) {
            throw new \InvalidArgumentException('One or more selected venues are not assigned to the selected draws.');
        }
        if ($selectedVenues) $venueIds = $venueIds->filter(fn ($id) => in_array((int) $id, $selectedVenues, true))->values();
        if (array_diff($replanVenues, $venueIds->map(fn ($id) => (int) $id)->all())) {
            throw new \InvalidArgumentException('One or more venues selected for replanning are not available in this preview.');
        }
        $venues = Venue::whereIn('id', $venueIds)->orderBy('name')->get()->keyBy('id');
        $courtLabels = $this->courtLabels($event, $draws, $venues);
        $allocations = DB::table('draw_venue_court_allocations')->whereIn('draw_id', $draws->pluck('id'))
            ->get()->groupBy(fn ($row) => $row->draw_id.'|'.$row->venue_id);

        $nodes = [];
        $excluded = [];
        $warnings = [];
        foreach ($draws as $draw) {
            if ($draw->locked || $draw->published) {
                $warnings[] = "{$draw->drawName} was not changed because the draw is locked or published.";
                continue;
            }
            foreach ($this->nodesForDraw($draw, $start, $waveMinutes) as $id => $node) {
                $node['draw_start'] = $drawStarts[$draw->id] ?? $start->copy();
                $node['venue_courts'] = [];
                foreach ($draw->venues as $venue) {
                    $venueId = (int) $venue->id;
                    if (! isset($courtLabels[$venueId])) continue;
                    $restricted = ($allocations[$draw->id.'|'.$venueId] ?? collect())->pluck('court_label')
                        ->filter(fn ($label) => in_array((string) $label, $courtLabels[$venueId], true))->values()->all();
                    $node['venue_courts'][$venueId] = $restricted ?: $courtLabels[$venueId];
                }
                $slot = $node['fixture']->orderOfPlay;
                $node['fixed'] = ! $node['played'] && $slot?->time
                    && ! in_array((int) $slot->venue_id, $replanVenues, true);
                $nodes[$id] = $node;
                if (! $node['played'] && ! $node['fixed']) $excluded[] = $id;
            }
        }

        $allRegistrations = collect($nodes)->flatMap(fn ($node) => $node['participants'])->unique()->values()->all();
        $calendar = ScheduleAvailability::load(array_keys($courtLabels), $allRegistrations, $excluded, null, $playerRest);
        $pending = [];
        $finished = [];
        foreach ($nodes as $id => &$node) {
            $node['wave'] = $this->wave($id, $nodes);
            $node['not_before'] = $node['draw_start']->copy()->addMinutes(($node['wave'] - 1) * $waveMinutes);
            if ($node['automatic']) {
                $finished[$id] = $node['not_before']->copy();
            } elseif ($node['played']) {
                $slot = $node['fixture']->orderOfPlay;
                $finished[$id] = $slot?->time
                    ? Carbon::parse($slot->time)->addMinutes($slot->occupiedMinutes($duration) + $playerRest)
                    : $node['not_before']->copy();
            } elseif ($node['fixed']) {
                $slot = $node['fixture']->orderOfPlay;
                $finished[$id] = Carbon::parse($slot->time)
                    ->addMinutes($slot->occupiedMinutes($duration) + $playerRest);
            } else {
                $pending[$id] = $node;
            }
        }
        unset($node);

        $plan = [];
        $scheduledPerDraw = [];
        $blocked = [];
        while ($pending) {
            $best = null;
            foreach ($pending as $id => $node) {
                if (! $node['venue_courts']) {
                    $blocked[$id] = 'No permitted venue with courts is selected.';
                    continue;
                }
                $release = $nodes[$id]['not_before']->copy();
                foreach ($node['dependencies'] as $dependency) {
                    if (! isset($finished[$dependency])) continue 2;
                    $release = $release->max($finished[$dependency])->copy();
                }
                foreach ($node['venue_courts'] as $venueId => $courts) {
                    foreach ($courts as $court) {
                        $at = $calendar->nextAvailableForMatch($release, $duration + $courtGap,
                            $duration + $playerRest, $venueId, (string) $court, $node['participants'],
                            $node['participant_group']);
                        if ($end && $at->copy()->addMinutes($duration)->gt($end)) continue;
                        $choice = ['id' => $id, 'time' => $at, 'venue_id' => $venueId, 'court' => (string) $court,
                            'fairness' => $scheduledPerDraw[$node['draw_id']] ?? 0];
                        if ($best === null || $this->isEarlier($choice, $best, $nodes)) $best = $choice;
                    }
                }
            }
            if ($best === null) break;
            $id = $best['id'];
            $node = $nodes[$id];
            $calendar->reserveWithRest($best['venue_id'], $best['court'], $best['time'], $duration + $courtGap,
                $duration + $playerRest, $node['participants'], $node['participant_group']);
            $finished[$id] = $best['time']->copy()->addMinutes($duration + $playerRest);
            $scheduledPerDraw[$node['draw_id']] = ($scheduledPerDraw[$node['draw_id']] ?? 0) + 1;
            $plan[] = [
                'fixture_id' => $id, 'draw_id' => $node['draw_id'], 'draw_name' => $node['draw_name'],
                'stage' => $node['stage'], 'round' => $node['round'], 'match' => $node['match'],
                'play_order' => $node['play_order'], 'wave' => $node['wave'],
                'dependencies' => $node['dependencies'],
                'scheduled_at' => $best['time']->format('Y-m-d H:i:s'), 'venue_id' => $best['venue_id'],
                'venue_name' => $venues[$best['venue_id']]->name, 'court' => $best['court'],
                'duration' => $duration, 'participants' => $node['participant_names'],
            ];
            unset($pending[$id], $blocked[$id]);
        }

        $unscheduled = [];
        foreach ($pending as $id => $node) {
            $reason = $blocked[$id] ?? ($end ? 'No valid court time remains before the scheduling window ends.'
                : 'A qualifying match is not schedulable in this plan.');
            $unscheduled[] = ['fixture_id' => $id, 'draw_id' => $node['draw_id'], 'draw_name' => $node['draw_name'],
                'round' => $node['round'], 'match' => $node['match'], 'reason' => $reason];
        }

        usort($plan, function ($a, $b) {
            $order = [$a['scheduled_at'], $a['venue_id']] <=> [$b['scheduled_at'], $b['venue_id']];
            return $order ?: strnatcasecmp((string) $a['court'], (string) $b['court']);
        });
        $displayEnd = $end?->copy() ?? collect($plan)->map(fn ($row) => Carbon::parse($row['scheduled_at'])
            ->addMinutes((int) $row['duration']))->max();
        $existingMatches = $displayEnd ? OrderOfPlay::with('fixture.draw')
            ->whereIn('venue_id', $venueIds)->whereNotNull('time')
            ->when($excluded, fn ($query) => $query->whereNotIn('fixture_id', $excluded))
            ->where('time', '>=', $start->copy()->subMinutes(600))->where('time', '<', $displayEnd)
            ->orderBy('time')->orderBy('venue_id')->orderBy('court')->limit(2000)->get()
            ->filter(fn (OrderOfPlay $slot) => Carbon::parse($slot->time)->addMinutes($slot->occupiedMinutes($duration))->gt($start))
            ->map(function (OrderOfPlay $slot) use ($duration, $nodes) {
                $startsAt = Carbon::parse($slot->time);
                $fixture = $slot->fixture;
                return [
                    'fixture_id' => $slot->fixture_id, 'draw_id' => $fixture?->draw_id ?? $slot->draw_id,
                    'draw_name' => $fixture?->draw?->drawName ?? $slot->draw?->drawName ?? 'Existing booking',
                    'round' => max(1, (int) ($fixture?->round ?? $slot->round_number)),
                    'match' => $fixture?->match_nr, 'scheduled_at' => $startsAt->format('Y-m-d H:i:s'),
                    'ends_at' => $startsAt->copy()->addMinutes($slot->occupiedMinutes($duration))->format('Y-m-d H:i:s'),
                    'venue_id' => (int) $slot->venue_id, 'court' => (string) $slot->court,
                    'duration' => (int) ($slot->duration_minutes ?: $duration),
                    'participants' => $nodes[$slot->fixture_id]['participant_names'] ?? [],
                ];
            })->values()->all() : [];
        $input = compact('duration', 'waveMinutes', 'courtGap', 'playerRest') + [
            'start' => $start->format('Y-m-d H:i:s'), 'end' => $end?->format('Y-m-d H:i:s'),
            'draw_ids' => $draws->pluck('id')->sort()->values()->all(), 'venue_ids' => $venueIds->sort()->values()->all(),
            'replan_venue_ids' => collect($replanVenues)->sort()->values()->all(),
            'draw_starts' => $drawStarts->map(fn ($time, $drawId) => ['draw_id' => (int) $drawId,
                'start' => $time->format('Y-m-d H:i:s')])->values()->all(),
        ];

        return [
            'event' => ['id' => $event->id, 'name' => $event->name],
            'venues' => $venues->map(fn ($venue) => ['id' => $venue->id, 'name' => $venue->name,
                'courts' => count($courtLabels[$venue->id]), 'court_labels' => $courtLabels[$venue->id]])->values()->all(),
            'matches' => $plan, 'existing_matches' => $existingMatches,
            'unscheduled' => $unscheduled, 'warnings' => $warnings,
            'automatic_byes' => collect($nodes)->where('automatic', true)->count(),
            'automatic_fixture_ids' => collect($nodes)->where('automatic', true)->keys()->values()->all(),
            'revision' => $this->revision($event, $input), 'input' => $input,
        ];
    }

    public function apply(Event $event, array $options, string $expectedRevision): array
    {
        return DB::transaction(function () use ($event, $options, $expectedRevision) {
            $applyVenueIds = array_values(array_unique(array_map('intval', $options['apply_venue_ids'] ?? [])));
            unset($options['apply_venue_ids']);
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $drawIds = $event->draws()->orderBy('id')->pluck('id');
            DB::table('draws')->whereIn('id', $drawIds)->orderBy('id')->lockForUpdate()->get();
            $fixtureIds = DB::table('fixtures')->whereIn('draw_id', $drawIds)->orderBy('id')->lockForUpdate()->pluck('id');
            DB::table('order_of_plays')->whereIn('fixture_id', $fixtureIds)->orderBy('fixture_id')->lockForUpdate()->get();
            DB::table('fixture_results')->whereIn('fixture_id', $fixtureIds)->orderBy('fixture_id')->orderBy('id')->lockForUpdate()->get();
            $venueIds = DB::table('draw_venues')->whereIn('draw_id', $drawIds)->pluck('venue_id')->unique()->sort()->values();
            DB::table('venues')->whereIn('id', $venueIds)->orderBy('id')->lockForUpdate()->get();

            $preview = $this->preview($event->fresh(), $options);
            if (! hash_equals($preview['revision'], $expectedRevision)) {
                throw new \InvalidArgumentException('The draws or venue schedule changed. Generate a fresh preview before applying.');
            }
            $previewVenueIds = collect($preview['venues'])->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (array_diff($applyVenueIds, $previewVenueIds)) {
                throw new \InvalidArgumentException('One or more venues selected for applying are not available in this preview.');
            }
            if (! $applyVenueIds && $preview['unscheduled']) {
                throw new \InvalidArgumentException('The preview contains unscheduled matches. Resolve them before applying.');
            }

            $matches = collect($preview['matches'])
                ->when($applyVenueIds, fn (Collection $rows) => $rows->whereIn('venue_id', $applyVenueIds))->values();
            if ($applyVenueIds && $matches->isEmpty()) {
                throw new \InvalidArgumentException('This venue has no new or changed fixtures to apply.');
            }
            if ($applyVenueIds) {
                $selectedFixtureIds = $matches->pluck('fixture_id')->all();
                $otherPlannedFixtureIds = collect($preview['matches'])->pluck('fixture_id')->diff($selectedFixtureIds)->all();
                if ($matches->contains(fn ($row) => array_intersect($row['dependencies'] ?? [], $otherPlannedFixtureIds))) {
                    throw new \InvalidArgumentException('This venue contains a match that depends on an unapplied match at another venue. Apply the prerequisite venue first or apply the combined schedule.');
                }
            }
            $scheduledFixtureIds = $matches->pluck('fixture_id')->all();
            $appliedDrawIds = $matches->pluck('draw_id')->unique()->values();
            $automaticFixtureIds = Fixture::whereIn('id', $preview['automatic_fixture_ids'])
                ->when($applyVenueIds, fn ($query) => $query->whereIn('draw_id', $appliedDrawIds))->pluck('id');
            if ($automaticFixtureIds->isNotEmpty()) {
                OrderOfPlay::whereIn('fixture_id', $automaticFixtureIds)->delete();
                Fixture::whereIn('id', $automaticFixtureIds)->update(['scheduled' => 0]);
            }
            foreach ($matches as $row) {
                $fixture = Fixture::whereKey($row['fixture_id'])->where('draw_id', $row['draw_id'])->firstOrFail();
                OrderOfPlay::updateOrCreate(['fixture_id' => $fixture->id], [
                    'draw_id' => $row['draw_id'], 'venue_id' => $row['venue_id'], 'court' => $row['court'],
                    'time' => $row['scheduled_at'], 'duration_minutes' => $row['duration'],
                    'gap_minutes' => (int) ($preview['input']['courtGap'] ?? 0), 'round_number' => $row['round'],
                ]);
                $fixture->update(['scheduled' => 1]);
            }
            foreach ($matches->groupBy('draw_id') as $drawId => $rows) {
                DrawAuditLog::record((int) $drawId, 'event_venue_schedule_applied', null, [
                    'event_id' => $event->id, 'matches' => $rows->count(), 'revision' => $expectedRevision,
                    'venue_ids' => $rows->pluck('venue_id')->unique()->values()->all(),
                    'partial' => (bool) $applyVenueIds,
                ]);
            }
            return ['count' => count($scheduledFixtureIds), 'revision' => $expectedRevision,
                'venue_ids' => $matches->pluck('venue_id')->unique()->values()->all()];
        });
    }

    public function unapply(Event $event, ?int $drawId = null, ?int $venueId = null): array
    {
        if (($drawId === null) === ($venueId === null)) {
            throw new \InvalidArgumentException('Choose either one draw or one venue to return to planning.');
        }

        return DB::transaction(function () use ($event, $drawId, $venueId) {
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $eventDrawIds = $event->draws()->orderBy('id')->pluck('id');
            if ($drawId !== null && ! $eventDrawIds->contains($drawId)) {
                throw new \InvalidArgumentException('This draw does not belong to the event.');
            }

            $fixtures = Fixture::with(['draw', 'fixtureResults'])
                ->whereIn('draw_id', $eventDrawIds)
                ->when($drawId !== null, fn ($query) => $query->where('draw_id', $drawId))
                ->when($venueId !== null, fn ($query) => $query->whereHas('orderOfPlay',
                    fn ($slots) => $slots->where('venue_id', $venueId)->whereNotNull('time')))
                ->whereHas('orderOfPlay', fn ($slots) => $slots->whereNotNull('time'))
                ->orderBy('id')->lockForUpdate()->get();

            if ($fixtures->isEmpty()) {
                throw new \InvalidArgumentException($drawId !== null
                    ? 'This draw has no applied matches to return to planning.'
                    : 'This venue has no applied matches for this event.');
            }
            if ($fixtures->contains(fn (Fixture $fixture) => $fixture->draw?->locked || $fixture->draw?->published)) {
                throw new \InvalidArgumentException('A locked or published draw is included. Unlock or unpublish it before removing scheduled times.');
            }
            if ($fixtures->contains(fn (Fixture $fixture) => $fixture->fixtureResults->isNotEmpty())) {
                throw new \InvalidArgumentException('Played matches cannot be returned to planning.');
            }

            $fixtureIds = $fixtures->pluck('id');
            $bookings = OrderOfPlay::whereIn('fixture_id', $fixtureIds)
                ->whereNotNull('time')->when($venueId !== null, fn ($query) => $query->where('venue_id', $venueId))
                ->orderBy('fixture_id')->lockForUpdate()->get();
            if ($bookings->isEmpty()) {
                throw new \InvalidArgumentException('No applied matches matched this selection.');
            }

            $bookingFixtureIds = $bookings->pluck('fixture_id')->unique()->values();
            OrderOfPlay::whereIn('id', $bookings->pluck('id'))->delete();
            Fixture::whereIn('id', $bookingFixtureIds)->update(['scheduled' => 0]);

            $fixtures->whereIn('id', $bookingFixtureIds)->groupBy('draw_id')->each(function ($drawFixtures, $affectedDrawId) use ($event, $drawId, $venueId) {
                DrawAuditLog::record((int) $affectedDrawId, 'event_venue_schedule_unapplied', null, [
                    'event_id' => $event->id,
                    'matches' => $drawFixtures->count(),
                    'scope' => $drawId !== null ? 'draw' : 'venue',
                    'venue_id' => $venueId,
                ]);
            });

            $count = $bookingFixtureIds->count();
            return [
                'count' => $count,
                'message' => $count.' '.($count === 1 ? 'match was' : 'matches were').' returned to planning. Fixtures and draw structure were preserved.',
            ];
        });
    }

    private function nodesForDraw(Draw $draw, Carbon $start, int $waveMinutes): array
    {
        if ($draw->usesFlexibleMonrad()) {
            $matches = (array) app(FlexibleMonradService::class)->state($draw)['matches'];
            $participants = ScheduleAvailability::participants($matches);
            $keyToId = collect($matches)->mapWithKeys(fn ($match, $key) => [$key => $match['id']])->all();
            $matchNumbers = collect($matches)->mapWithKeys(fn ($match, $key) => [$key => $match['number']])->all();
            $nodes = [];
            foreach ($matches as $key => $match) {
                $fixture = $draw->drawFixtures->firstWhere('id', $match['id']);
                $sourceLabels = collect($match['sources'])->map(fn ($source) => isset($source['match'])
                    ? ucfirst((string) $source['type']).' of Match '.($matchNumbers[$source['match']] ?? $source['match'])
                    : (($source['type'] ?? null) === 'bye' ? 'Bye' : 'Unassigned draw position'))->all();
                $nodes[$match['id']] = $this->node($draw, $fixture, array_values(array_filter(array_map(
                    fn ($source) => isset($source['match']) ? ($keyToId[$source['match']] ?? null) : null,
                    $match['sources']))), $participants[$key] ?? [], (bool) $match['automatic'], (bool) $match['sets'],
                    $sourceLabels);
            }
            return $nodes;
        }

        $fixtures = $draw->drawFixtures->keyBy('id');
        $participants = ScheduleAvailability::legacyParticipants($fixtures);
        $feeders = [];
        $sourceLabels = [];
        foreach ($fixtures as $fixture) {
            foreach ([
                ['target' => $fixture->parent_fixture_id, 'type' => 'Winner', 'slot' => $fixture->feeder_slot],
                ['target' => $fixture->loser_parent_fixture_id, 'type' => 'Loser',
                    'slot' => $fixture->getAttribute('loser_feeder_slot')],
            ] as $path) {
                $target = $path['target'];
                if (! $target || ! isset($fixtures[$target])) continue;
                $feeders[$target][] = $fixture->id;
                $slot = in_array((int) $path['slot'], [1, 2], true) ? (int) $path['slot'] - 1 : null;
                if ($slot === null || isset($sourceLabels[$target][$slot])) {
                    $slot = ! isset($sourceLabels[$target][0]) ? 0 : 1;
                }
                $sourceLabels[$target][$slot] = $path['type'].' of Match '.($fixture->match_nr ?: $fixture->id);
            }

            foreach ([1, 2] as $slotNumber) {
                $groupId = (int) $fixture->getAttribute("registration{$slotNumber}_source_group_id");
                $position = (int) $fixture->getAttribute("registration{$slotNumber}_source_position");
                if (! $groupId || ! $position) continue;

                foreach ($fixtures->where('draw_group_id', $groupId)->where('stage', 'RR') as $roundRobinFixture) {
                    $feeders[$fixture->id][] = $roundRobinFixture->id;
                }
                $groupName = $draw->groups->firstWhere('id', $groupId)?->name ?: $groupId;
                $sourceLabels[$fixture->id][$slotNumber - 1] = "Group {$groupName} #{$position}";
            }
        }
        $nodes = [];
        foreach ($fixtures as $fixture) {
            $automatic = $fixture->fixtureResults->isEmpty() && (! $fixture->registration1_id || ! $fixture->registration2_id)
                && ($fixture->winner_registration || (empty($feeders[$fixture->id])
                    && (int) $fixture->bracket_id === 1 && (int) $fixture->round === 1));
            $labels = $sourceLabels[$fixture->id] ?? [];
            ksort($labels);
            $dependencies = array_values(array_unique($feeders[$fixture->id] ?? []));
            $nodes[$fixture->id] = $this->node($draw, $fixture, $dependencies,
                $participants[$fixture->id] ?? [], $automatic, $fixture->fixtureResults->isNotEmpty(), $labels);
        }
        return $nodes;
    }

    private function node(Draw $draw, Fixture $fixture, array $dependencies, array $participants,
        bool $automatic, bool $played, array $sourceLabels = []): array
    {
        $names = collect([$fixture->registration1, $fixture->registration2])->map(function ($registration, $slot) use ($sourceLabels) {
            if ($registration) {
                $name = $registration->displayName();
                return $name !== 'Unassigned' ? $name : 'Entry '.$registration->id;
            }
            return $sourceLabels[$slot] ?? 'Unassigned draw position';
        })->values()->all();
        return [
            'fixture' => $fixture, 'draw_id' => $draw->id, 'draw_name' => $draw->drawName,
            'stage' => $fixture->stage, 'round' => max(1, (int) $fixture->round), 'match' => $fixture->match_nr,
            'play_order' => (int) ($fixture->play_order ?: $fixture->match_nr), 'dependencies' => $dependencies,
            'participants' => array_values(array_unique(array_filter($participants))), 'participant_names' => $names,
            // A Flexible Monrad graph already models the winner and loser paths within one draw. Its sibling
            // classification matches can therefore share a time even while their exact players are unresolved.
            'participant_group' => $draw->usesFlexibleMonrad() ? 'flexible-draw-'.$draw->id : null,
            'automatic' => $automatic, 'played' => $played,
        ];
    }

    private function wave(int $id, array &$nodes, array $visiting = []): int
    {
        if (isset($nodes[$id]['calculated_wave'])) return $nodes[$id]['calculated_wave'];
        if (isset($visiting[$id])) throw new \InvalidArgumentException('The draw contains a circular match dependency.');
        $visiting[$id] = true;
        $wave = max(1, (int) $nodes[$id]['round']);
        foreach ($nodes[$id]['dependencies'] as $dependency) {
            if (isset($nodes[$dependency])) $wave = max($wave, $this->wave($dependency, $nodes, $visiting) + 1);
        }
        return $nodes[$id]['calculated_wave'] = $wave;
    }

    private function courtLabels(Event $event, Collection $draws, Collection $venues): array
    {
        $configured = DB::table('event_venue_courts')->where('event_id', $event->id)
            ->whereIn('venue_id', $venues->keys())->where('active', true)->orderBy('id')->get()->groupBy('venue_id');
        $labels = [];
        foreach ($venues as $venue) {
            $venueLabels = ($configured[$venue->id] ?? collect())->pluck('label')->map(fn ($label) => (string) $label)->all();
            $pivotMaximum = $draws->flatMap(fn ($draw) => $draw->venues->where('id', $venue->id))
                ->max(fn ($assigned) => (int) ($assigned->pivot->num_courts ?? 0));
            $labels[$venue->id] = $venueLabels ?: array_map('strval', range(1, max(1, (int) $pivotMaximum)));
        }
        return $labels;
    }

    private function isEarlier(array $candidate, array $best, array $nodes): bool
    {
        return ([$candidate['time']->timestamp, $candidate['fairness'], $nodes[$candidate['id']]['wave'],
            $nodes[$candidate['id']]['play_order'], $candidate['id']]
            <=> [$best['time']->timestamp, $best['fairness'], $nodes[$best['id']]['wave'],
                $nodes[$best['id']]['play_order'], $best['id']]) < 0;
    }

    private function revision(Event $event, array $input): string
    {
        $drawIds = $event->draws()->pluck('id');
        $fixtures = DB::table('fixtures')->whereIn('draw_id', $drawIds)->orderBy('id')
            ->get(['id', 'draw_id', 'registration1_id', 'registration2_id', 'winner_registration',
                'parent_fixture_id', 'loser_parent_fixture_id', 'round', 'stage', 'play_order', 'updated_at']);
        $state = [
            'input' => $input,
            'draws' => DB::table('draws')->whereIn('id', $drawIds)->orderBy('id')
                ->get(['id', 'locked', 'published', 'updated_at'])->all(),
            'venues' => DB::table('draw_venues')->whereIn('draw_id', $drawIds)->orderBy('draw_id')->orderBy('venue_id')->get()->all(),
            'courts' => DB::table('event_venue_courts')->where('event_id', $event->id)->orderBy('venue_id')->orderBy('id')->get()->all(),
            'court_allocations' => DB::table('draw_venue_court_allocations')->whereIn('draw_id', $drawIds)
                ->orderBy('draw_id')->orderBy('venue_id')->orderBy('court_label')->get()->all(),
            'fixtures' => $fixtures->all(),
            'results' => DB::table('fixture_results')->whereIn('fixture_id', $fixtures->pluck('id'))
                ->orderBy('fixture_id')->orderBy('id')->get()->all(),
            'flexible' => DB::table('flexible_monrad_draws')->whereIn('draw_id', $drawIds)
                ->orderBy('draw_id')->get()->all(),
            'bookings' => DB::table('order_of_plays')->whereIn('fixture_id', $fixtures->pluck('id'))
                ->orderBy('fixture_id')->get()->all(),
        ];
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }
}
