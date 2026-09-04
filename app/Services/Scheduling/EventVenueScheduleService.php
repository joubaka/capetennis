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
        if (array_key_exists('draw_ids', $options) && ! $selectedDraws) {
            throw new \InvalidArgumentException('Select at least one age group or draw.');
        }
        if (array_key_exists('venue_ids', $options) && ! $selectedVenues) {
            throw new \InvalidArgumentException('Select at least one venue.');
        }

        $draws = $event->draws()->with([
            'venues' => fn ($query) => $query->withPivot('num_courts'),
            'drawFixtures.orderOfPlay', 'drawFixtures.fixtureResults', 'flexibleMonrad',
        ])->when($selectedDraws, fn ($query) => $query->whereIn('id', $selectedDraws))->get();

        $foreignDraws = array_diff($selectedDraws, $draws->pluck('id')->map(fn ($id) => (int) $id)->all());
        if ($foreignDraws) throw new \InvalidArgumentException('One or more selected draws do not belong to this event.');

        $venueIds = $draws->flatMap(fn (Draw $draw) => $draw->venues->pluck('id'))->unique()->values();
        if (array_diff($selectedVenues, $venueIds->map(fn ($id) => (int) $id)->all())) {
            throw new \InvalidArgumentException('One or more selected venues are not assigned to the selected draws.');
        }
        if ($selectedVenues) $venueIds = $venueIds->filter(fn ($id) => in_array((int) $id, $selectedVenues, true))->values();
        $venues = Venue::whereIn('id', $venueIds)->orderBy('name')->get()->keyBy('id');
        $courtCounts = $this->courtCounts($draws, $venues);

        $nodes = [];
        $excluded = [];
        $warnings = [];
        foreach ($draws as $draw) {
            if ($draw->locked || $draw->published) {
                $warnings[] = "{$draw->drawName} was not changed because the draw is locked or published.";
                continue;
            }
            foreach ($this->nodesForDraw($draw, $start, $waveMinutes) as $id => $node) {
                $node['venue_ids'] = $draw->venues->pluck('id')->map(fn ($venueId) => (int) $venueId)
                    ->filter(fn ($venueId) => isset($courtCounts[$venueId]))->values()->all();
                $nodes[$id] = $node;
                if (! $node['played']) $excluded[] = $id;
            }
        }

        $allRegistrations = collect($nodes)->flatMap(fn ($node) => $node['participants'])->unique()->values()->all();
        $calendar = ScheduleAvailability::load(array_keys($courtCounts), $allRegistrations, $excluded, null, $playerRest);
        $pending = [];
        $finished = [];
        foreach ($nodes as $id => &$node) {
            $node['wave'] = $this->wave($id, $nodes);
            $node['not_before'] = $start->copy()->addMinutes(($node['wave'] - 1) * $waveMinutes);
            if ($node['automatic']) {
                $finished[$id] = $node['not_before']->copy();
            } elseif ($node['played']) {
                $slot = $node['fixture']->orderOfPlay;
                $finished[$id] = $slot?->time
                    ? Carbon::parse($slot->time)->addMinutes($slot->occupiedMinutes($duration) + $playerRest)
                    : $node['not_before']->copy();
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
                if (! $node['venue_ids']) {
                    $blocked[$id] = 'No permitted venue with courts is selected.';
                    continue;
                }
                $release = $nodes[$id]['not_before']->copy();
                foreach ($node['dependencies'] as $dependency) {
                    if (! isset($finished[$dependency])) continue 2;
                    $release = $release->max($finished[$dependency])->copy();
                }
                foreach ($node['venue_ids'] as $venueId) {
                    foreach (range(1, $courtCounts[$venueId]) as $court) {
                        $at = $calendar->nextAvailableForMatch($release, $duration + $courtGap,
                            $duration + $playerRest, $venueId, (string) $court, $node['participants']);
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
                $duration + $playerRest, $node['participants']);
            $finished[$id] = $best['time']->copy()->addMinutes($duration + $playerRest);
            $scheduledPerDraw[$node['draw_id']] = ($scheduledPerDraw[$node['draw_id']] ?? 0) + 1;
            $plan[] = [
                'fixture_id' => $id, 'draw_id' => $node['draw_id'], 'draw_name' => $node['draw_name'],
                'stage' => $node['stage'], 'round' => $node['round'], 'match' => $node['match'],
                'play_order' => $node['play_order'], 'wave' => $node['wave'],
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

        usort($plan, fn ($a, $b) => [$a['scheduled_at'], $a['venue_id'], (int) $a['court']]
            <=> [$b['scheduled_at'], $b['venue_id'], (int) $b['court']]);
        $input = compact('duration', 'waveMinutes', 'courtGap', 'playerRest') + [
            'start' => $start->format('Y-m-d H:i:s'), 'end' => $end?->format('Y-m-d H:i:s'),
            'draw_ids' => $draws->pluck('id')->sort()->values()->all(), 'venue_ids' => $venueIds->sort()->values()->all(),
        ];

        return [
            'event' => ['id' => $event->id, 'name' => $event->name],
            'venues' => $venues->map(fn ($venue) => ['id' => $venue->id, 'name' => $venue->name,
                'courts' => $courtCounts[$venue->id]])->values()->all(),
            'matches' => $plan, 'unscheduled' => $unscheduled, 'warnings' => $warnings,
            'automatic_byes' => collect($nodes)->where('automatic', true)->count(),
            'automatic_fixture_ids' => collect($nodes)->where('automatic', true)->keys()->values()->all(),
            'revision' => $this->revision($event, $input), 'input' => $input,
        ];
    }

    public function apply(Event $event, array $options, string $expectedRevision): array
    {
        return DB::transaction(function () use ($event, $options, $expectedRevision) {
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $drawIds = $event->draws()->orderBy('id')->pluck('id');
            DB::table('draws')->whereIn('id', $drawIds)->orderBy('id')->lockForUpdate()->get();
            $venueIds = DB::table('draw_venues')->whereIn('draw_id', $drawIds)->pluck('venue_id')->unique()->sort()->values();
            DB::table('venues')->whereIn('id', $venueIds)->orderBy('id')->lockForUpdate()->get();

            $preview = $this->preview($event->fresh(), $options);
            if (! hash_equals($preview['revision'], $expectedRevision)) {
                throw new \InvalidArgumentException('The draws or venue schedule changed. Generate a fresh preview before applying.');
            }
            if ($preview['unscheduled']) {
                throw new \InvalidArgumentException('The preview contains unscheduled matches. Resolve them before applying.');
            }

            $fixtureIds = collect($preview['matches'])->pluck('fixture_id')->all();
            if ($preview['automatic_fixture_ids']) {
                OrderOfPlay::whereIn('fixture_id', $preview['automatic_fixture_ids'])->delete();
                Fixture::whereIn('id', $preview['automatic_fixture_ids'])->update(['scheduled' => 0]);
            }
            foreach ($preview['matches'] as $row) {
                $fixture = Fixture::whereKey($row['fixture_id'])->where('draw_id', $row['draw_id'])->firstOrFail();
                OrderOfPlay::updateOrCreate(['fixture_id' => $fixture->id], [
                    'draw_id' => $row['draw_id'], 'venue_id' => $row['venue_id'], 'court' => $row['court'],
                    'time' => $row['scheduled_at'], 'duration_minutes' => $row['duration'],
                    'gap_minutes' => (int) ($preview['input']['courtGap'] ?? 0), 'round_number' => $row['round'],
                ]);
                $fixture->update(['scheduled' => 1]);
            }
            foreach (collect($preview['matches'])->groupBy('draw_id') as $drawId => $rows) {
                DrawAuditLog::record((int) $drawId, 'event_venue_schedule_applied', null, [
                    'event_id' => $event->id, 'matches' => $rows->count(), 'revision' => $expectedRevision,
                ]);
            }
            return ['count' => count($fixtureIds), 'revision' => $expectedRevision];
        });
    }

    private function nodesForDraw(Draw $draw, Carbon $start, int $waveMinutes): array
    {
        if ($draw->usesFlexibleMonrad()) {
            $matches = (array) app(FlexibleMonradService::class)->state($draw)['matches'];
            $participants = ScheduleAvailability::participants($matches);
            $keyToId = collect($matches)->mapWithKeys(fn ($match, $key) => [$key => $match['id']])->all();
            $nodes = [];
            foreach ($matches as $key => $match) {
                $fixture = $draw->drawFixtures->firstWhere('id', $match['id']);
                $nodes[$match['id']] = $this->node($draw, $fixture, array_values(array_filter(array_map(
                    fn ($source) => isset($source['match']) ? ($keyToId[$source['match']] ?? null) : null,
                    $match['sources']))), $participants[$key] ?? [], (bool) $match['automatic'], (bool) $match['sets']);
            }
            return $nodes;
        }

        $fixtures = $draw->drawFixtures->keyBy('id');
        $participants = ScheduleAvailability::legacyParticipants($fixtures);
        $feeders = [];
        foreach ($fixtures as $fixture) {
            foreach ([$fixture->parent_fixture_id, $fixture->loser_parent_fixture_id] as $target) {
                if ($target && isset($fixtures[$target])) $feeders[$target][] = $fixture->id;
            }
        }
        $nodes = [];
        foreach ($fixtures as $fixture) {
            $automatic = $fixture->fixtureResults->isEmpty() && (! $fixture->registration1_id || ! $fixture->registration2_id)
                && ($fixture->winner_registration || (empty($feeders[$fixture->id])
                    && (int) $fixture->bracket_id === 1 && (int) $fixture->round === 1));
            $nodes[$fixture->id] = $this->node($draw, $fixture, $feeders[$fixture->id] ?? [],
                $participants[$fixture->id] ?? [], $automatic, $fixture->fixtureResults->isNotEmpty());
        }
        return $nodes;
    }

    private function node(Draw $draw, Fixture $fixture, array $dependencies, array $participants,
        bool $automatic, bool $played): array
    {
        $names = collect([$fixture->registration1, $fixture->registration2])->filter()
            ->flatMap(fn ($registration) => $registration->players?->pluck('full_name') ?? [])->values()->all();
        return [
            'fixture' => $fixture, 'draw_id' => $draw->id, 'draw_name' => $draw->drawName,
            'stage' => $fixture->stage, 'round' => max(1, (int) $fixture->round), 'match' => $fixture->match_nr,
            'play_order' => (int) ($fixture->play_order ?: $fixture->match_nr), 'dependencies' => $dependencies,
            'participants' => array_values(array_unique(array_filter($participants))), 'participant_names' => $names,
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

    private function courtCounts(Collection $draws, Collection $venues): array
    {
        $counts = [];
        foreach ($venues as $venue) {
            $pivotMaximum = $draws->flatMap(fn ($draw) => $draw->venues->where('id', $venue->id))
                ->max(fn ($assigned) => (int) ($assigned->pivot->num_courts ?? 0));
            $counts[$venue->id] = max(1, (int) ($venue->num_courts ?? 0), (int) $pivotMaximum);
        }
        return $counts;
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
        $state = [
            'input' => $input,
            'draws' => DB::table('draws')->whereIn('id', $drawIds)->orderBy('id')
                ->get(['id', 'locked', 'published', 'updated_at'])->all(),
            'venues' => DB::table('draw_venues')->whereIn('draw_id', $drawIds)->orderBy('draw_id')->orderBy('venue_id')->get()->all(),
            'fixtures' => DB::table('fixtures')->whereIn('draw_id', $drawIds)->orderBy('id')
                ->get(['id', 'draw_id', 'registration1_id', 'registration2_id', 'winner_registration',
                    'parent_fixture_id', 'loser_parent_fixture_id', 'round', 'stage', 'play_order', 'updated_at'])->all(),
            'bookings' => DB::table('order_of_plays')->whereIn('draw_id', $drawIds)->orderBy('fixture_id')->get()->all(),
        ];
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }
}
