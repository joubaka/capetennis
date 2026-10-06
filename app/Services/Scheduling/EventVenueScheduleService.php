<?php

namespace App\Services\Scheduling;

use App\Domain\Draws\Services\ScheduleAvailability;
use App\Models\{Draw, DrawAuditLog, Event, Fixture, OrderOfPlay, TeamFixture, Venue};
use App\Services\Draw\FlexibleMonradService;
use App\Services\ScheduleEngine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EventVenueScheduleService
{
    public function preview(Event $event, array $options): array
    {
        $roundProgression = $options['round_progression'] ?? 'team_ready';
        if (! in_array($roundProgression, ['team_ready', 'all_round'], true)) throw new \InvalidArgumentException('Choose a valid round progression rule.');
        $genderWaves = $options['gender_waves'] ?? 'combined';
        if (! in_array($genderWaves, ['combined', 'boys_then_girls', 'girls_then_boys'], true)) throw new \InvalidArgumentException('Choose a valid gender wave order.');
        $genderRelease = $options['gender_wave_release'] ?? 'whole_wave';
        if (! in_array($genderRelease, ['whole_wave', 'court_ready'], true)) throw new \InvalidArgumentException('Choose a valid gender wave release.');
        $tieAllocation = $options['tie_allocation'] ?? 'balanced';
        if (! in_array($tieAllocation, ['balanced', 'complete_tie'], true)) throw new \InvalidArgumentException('Choose a valid team tie allocation rule.');
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
        $venueStarts = collect($options['venue_starts'] ?? [])->filter(fn ($row) => ! empty($row['start']))
            ->mapWithKeys(fn ($row) => [(int) $row['venue_id'] => Carbon::parse($row['start'])])->sortKeys();
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
        $programme = app(ScheduleProgramme::class)->normalize($options['programme'] ?? [], $draws, $this->availableDrawRounds($draws));
        if ($programme) {
            $options['allow_partial'] = false;
            $programmeService = app(ScheduleProgramme::class);
            $ages = $draws->map(fn ($draw) => $programmeService->age($draw))->unique();
            if ($ages->count() !== 1 || $ages->first() === null) throw new \InvalidArgumentException('Select one complete age group for the programme.');
            $expected = $event->draws()->get()->filter(fn ($draw) => $draw->isTeamDraw() && $programmeService->age($draw) === $ages->first())->pluck('id');
            if ($expected->diff($draws->pluck('id'))->isNotEmpty()) throw new \InvalidArgumentException('Include every discipline of the selected age group.');
            if ($draws->contains('locked', true)) throw new \InvalidArgumentException('Unlock all age-group draws before creating a complete programme.');
            if ($options['draw_rounds'] ?? []) throw new \InvalidArgumentException('A complete programme cannot also filter rounds.');
            if ($draws->contains(fn ($draw) => ! $draw->isTeamDraw())) throw new \InvalidArgumentException('The three-day programme requires team draws.');
            $start = Carbon::parse($programme['days'][0]['start']);
            $end = Carbon::parse($programme['days'][2]['end']);
        }
        $drawRounds = $this->normalizeDrawRounds($draws, $options['draw_rounds'] ?? []);
        $roundSelection = collect($drawRounds)->mapWithKeys(fn ($row) => [$row['draw_id'] => $row['rounds']]);
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
        if ($venueStarts->keys()->diff($venueIds->map(fn ($id) => (int) $id))->isNotEmpty()) {
            throw new \InvalidArgumentException('A venue start time belongs to a venue outside this preview.');
        }
        $venues = Venue::whereIn('id', $venueIds)->orderBy('name')->get()->keyBy('id');
        $courtLabels = $this->courtLabels($event, $draws, $venues);
        $allocations = DB::table('draw_venue_court_allocations')->whereIn('draw_id', $draws->pluck('id'))
            ->get()->groupBy(fn ($row) => $row->draw_id.'|'.$row->venue_id);

        $preferenceService = app(RankVenuePreferences::class);
        $storedDraft = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        $rankRuleWarnings = [];
        $rankRules = [];
        if (array_key_exists('rank_venue_preferences', $options)) {
            $rankRules = $preferenceService->normalize($event, $options['rank_venue_preferences']);
        } else {
            foreach ($preferenceService->active($storedDraft['rank_venue_preferences'] ?? [], $draws->pluck('id')->all()) as $rule) {
                try { $rankRules = array_merge($rankRules, $preferenceService->normalize($event, [$rule])); }
                catch (\InvalidArgumentException $exception) { $rankRuleWarnings[] = 'A saved roster rank venue preference needs review. Normal venue scheduling is used: '.$exception->getMessage(); }
            }
        }
        $rankRules = $preferenceService->active($rankRules, $draws->pluck('id')->all());
        $crossBandPolicy = $options['cross_band_policy'] ?? $storedDraft['cross_band_policy'] ?? 'highest_ranked';
        if (! in_array($crossBandPolicy, ['highest_ranked', 'manual'], true)) throw new \InvalidArgumentException('Choose a valid cross-band rule.');

        $nodes = [];
        $excluded = [];
        $warnings = $rankRuleWarnings;
        foreach ($draws as $draw) {
            if ($draw->locked) {
                $warnings[] = "{$draw->drawName} was not changed because the draw is locked.";
                continue;
            }
            foreach (($draw->isTeamDraw() ? app(UnifiedTeamScheduleService::class)->nodes($draw, $roundProgression) : $this->nodesForDraw($draw, $start, $waveMinutes)) as $id => $node) {
                $node['draw_start'] = $drawStarts[$draw->id] ?? $start->copy();
                $node['selected_round'] = ! $roundSelection->has($draw->id) || in_array($node['round'], $roundSelection[$draw->id], true);
                $node['gender'] = $this->drawGender($draw);
                $node['tie_allocation_key'] = $this->tieAllocationKey($node);
                if (($node['fixture_kind'] ?? 'individual') === 'team' && count($node['participants']) < 2 * (int) ($node['fixture']->player_count_per_team ?: ($node['fixture']->isDoubles() ? 2 : 1)) && ! $node['played']) {
                    $warnings[] = $draw->drawName.' rubber '.($node['match'] ?: $node['fixture']->id).' has unassigned players; player conflicts cannot yet be checked.';
                }
                $node['venue_courts'] = [];
                foreach ($draw->venues as $venue) {
                    $venueId = (int) $venue->id;
                    if (! isset($courtLabels[$venueId])) continue;
                    $restricted = ($allocations[$draw->id.'|'.$venueId] ?? collect())->pluck('court_label')
                        ->filter(fn ($label) => in_array((string) $label, $courtLabels[$venueId], true))->values()->all();
                    $node['venue_courts'][$venueId] = $restricted ?: $courtLabels[$venueId];
                }
                $slot = $this->nodeSlot($node);
                if ($node['selected_round'] && $slot?->time && in_array((int) $slot->venue_id, $replanVenues, true)) {
                    $node['venue_courts'] = collect($node['venue_courts'])
                        ->only([(int) $slot->venue_id])->all();
                }
                $node['fixed'] = ! $node['played'] && $slot?->time
                    && (! $node['selected_round'] || ! in_array((int) $slot->venue_id, $replanVenues, true));
                $nodes[$id] = $node;
                if ($node['selected_round'] && ! $node['played'] && ! $node['fixed']) $excluded[] = $id;
            }
        }

        app(ScheduleProgramme::class)->configureNodes($nodes, $programme);
        $rankChoices = $preferenceService->choices(collect($nodes)->filter(fn ($node) => ($node['fixture_kind'] ?? '') === 'team')
            ->map(fn ($node) => $node['fixture'])->values(), $rankRules, $crossBandPolicy);
        $displayFixtures = collect($nodes)->filter(fn ($node) => ($node['fixture_kind'] ?? '') === 'team')
            ->map(fn ($node) => $node['fixture'])->filter(fn ($fixture) => $fixture->lineup_display === null)->values();
        if ($displayFixtures->isNotEmpty()) app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($displayFixtures);
        foreach ($nodes as &$node) {
            $node['rank_preference'] = $rankChoices[$node['fixture']->id] ?? null;
            if (($node['fixture_kind'] ?? '') !== 'team') $node['rank_preference'] = null;
        }
        unset($node);

        $allRegistrations = collect($nodes)->flatMap(fn ($node) => $node['participants'])->unique()->values()->all();
        $excludedIndividual = array_values(array_filter($excluded, 'is_int'));
        $excludedTeam = collect($excluded)->filter(fn ($id) => is_string($id) && str_starts_with($id, 'team:'))->map(fn ($id) => (int) substr($id, 5))->all();
        $calendar = ScheduleAvailability::load(array_keys($courtLabels), $allRegistrations, $excludedIndividual, null, $playerRest, $excludedTeam, $event, $start,
            $programme ? $start : null, $programme ? $end->copy()->addMinutes(max($playerRest, $courtGap)) : null);
        $availabilityRevision = $calendar->fingerprint();
        $pending = [];
        $finished = [];
        foreach ($nodes as $id => &$node) {
            $node['wave'] = $this->wave($id, $nodes);
            $node['not_before'] = $node['draw_start']->copy()->addMinutes($programme ? 0 : ($node['wave'] - 1) * $waveMinutes);
            if ($programme) {
                $node['wave'] = $node['programme_phase'];
                $slot = $this->nodeSlot($node);
                if (($node['fixed'] || $node['played']) && $slot?->time && (Carbon::parse($slot->time)->lt($node['draw_start']) || Carbon::parse($slot->time)->addMinutes((int) ($slot->duration_minutes ?: $duration))->gt($node['programme_end']))) {
                    throw new \InvalidArgumentException('A saved match is outside its programme day. Review or return it to planning before creating this programme.');
                }
                if (($node['fixed'] || $node['played']) && $slot?->time && $node['programme_break'] && Carbon::parse($slot->time)->lt($node['programme_break'][1]) && Carbon::parse($slot->time)->addMinutes((int) ($slot->duration_minutes ?: $duration))->gt($node['programme_break'][0])) throw new \InvalidArgumentException('A saved match overlaps a programme break. Review its time before creating this programme.');
            }
            if ($node['automatic']) {
                $finished[$id] = $node['not_before']->copy();
            } elseif ($node['played']) {
                $slot = $this->nodeSlot($node);
                $finished[$id] = $slot?->time
                    ? Carbon::parse($slot->time)->addMinutes((int) ($slot->duration_minutes ?: $duration) + $playerRest)
                    : $node['not_before']->copy();
            } elseif ($node['fixed']) {
                $slot = $this->nodeSlot($node);
                $finished[$id] = Carbon::parse($slot->time)
                    ->addMinutes((int) ($slot->duration_minutes ?: $duration) + $playerRest);
            } elseif ($node['selected_round']) {
                $pending[$id] = $node;
            }
        }
        unset($node);

        if ($programme) {
            foreach ($nodes as $node) {
                if (! $node['fixed'] && ! $node['played']) continue;
                $slot = $this->nodeSlot($node);
                if (! $slot?->time) continue;
                foreach ($node['dependencies'] as $dependency) {
                    if (isset($finished[$dependency]) && $finished[$dependency]->gt(Carbon::parse($slot->time))) {
                        throw new \InvalidArgumentException('Saved matches conflict with the programme order or required rest. Review their times or return them to planning.');
                    }
                }
            }
        }

        $sharedGenderVenues = [];
        if ($genderWaves !== 'combined' || collect($nodes)->contains(fn ($node) => ($node['programme_gender_waves'] ?? 'combined') !== 'combined')) {
            foreach (array_keys($courtLabels) as $venueId) {
                $genders = collect($nodes)->filter(fn ($node) => isset($node['venue_courts'][$venueId]))->pluck('gender');
                if ($genders->contains('boys') && $genders->contains('girls')) $sharedGenderVenues[$venueId] = true;
            }
        }

        $plan = [];
        $venueChangeWarnings = [];
        $genderSlots = [];
        foreach ($nodes as $id => $node) {
            $slot = $this->nodeSlot($node);
            if (($node['fixed'] || $node['played']) && $slot?->time) {
                $genderSlots[$id] = ['venue_id' => (int) $slot->venue_id, 'time' => Carbon::parse($slot->time),
                    'duration' => (int) ($slot->duration_minutes ?: $duration)];
            }
        }
        $scheduledPerDraw = [];
        $activeTie = null;
        $activeTieLabel = null;
        $incompleteTies = [];
        $blocked = [];
        while ($pending) {
            $best = null;
            foreach ($pending as $id => $node) {
                if ($node['rank_preference']['manual'] ?? false) {
                    $blocked[$id] = $node['rank_preference']['warning'];
                    continue;
                }
                if (! $node['venue_courts']) {
                    $blocked[$id] = 'No permitted venue with courts is selected.';
                    continue;
                }
                $release = $nodes[$id]['not_before']->copy();
                foreach ($node['dependencies'] as $dependency) {
                    if (! isset($finished[$dependency])) {
                        $blocked[$id] = isset($nodes[$dependency]) && ! $nodes[$dependency]['selected_round']
                            ? 'A qualifying match in an unselected round must be scheduled first.'
                            : 'A qualifying match must be scheduled first.';
                        continue 2;
                    }
                    $release = $release->max($finished[$dependency])->copy();
                }
                foreach ($node['venue_courts'] as $venueId => $courts) {
                    $venueRelease = isset($venueStarts[$venueId]) ? $release->max($venueStarts[$venueId])->copy() : $release;
                    if (isset($sharedGenderVenues[$venueId]) && $node['gender'] && ($node['programme_gender_waves'] ?? $genderWaves) !== 'combined') {
                        $phase = $this->genderPhase($node, $genderWaves);
                        $phaseStarts = [];
                        foreach ($nodes as $earlierId => $earlier) {
                            if (! $earlier['gender'] || $earlier['automatic']) continue;
                            $earlierPhase = $this->genderPhase($earlier, $genderWaves);
                            if ($earlierPhase >= $phase) continue;
                            if (isset($pending[$earlierId]) && isset($earlier['venue_courts'][$venueId])) {
                                $blocked[$id] = 'An earlier gender wave must be scheduled first at this shared venue.';
                                continue 2;
                            }
                            $earlierSlot = $genderSlots[$earlierId] ?? null;
                            if ($earlierSlot && $earlierSlot['venue_id'] === (int) $venueId) {
                                if ($genderRelease === 'court_ready') {
                                    $phaseStarts[$earlierPhase] = isset($phaseStarts[$earlierPhase])
                                        ? $phaseStarts[$earlierPhase]->min($earlierSlot['time'])->copy() : $earlierSlot['time']->copy();
                                } else {
                                    $venueRelease = $venueRelease->copy()->max($earlierSlot['time']->copy()
                                        ->addMinutes(max($waveMinutes, $earlierSlot['duration'] + $courtGap)));
                                }
                            }
                        }
                        foreach ($phaseStarts as $phaseStart) $venueRelease = $venueRelease->copy()->max($phaseStart->copy()->addMinutes($waveMinutes));
                    }
                    foreach ($courts as $court) {
                        $at = $calendar->nextAvailableForMatch($venueRelease, $duration + $courtGap,
                            $duration + $playerRest, $venueId, (string) $court, $node['participants'],
                            $node['participant_group']);
                        if (($node['programme_break'] ?? null) && $at->lt($node['programme_break'][1]) && $at->copy()->addMinutes($duration)->gt($node['programme_break'][0])) {
                            $at = $calendar->nextAvailableForMatch($node['programme_break'][1], $duration + $courtGap,
                                $duration + $playerRest, $venueId, (string) $court, $node['participants'], $node['participant_group']);
                        }
                        if ($end && $at->copy()->addMinutes($duration)->gt($end)) continue;
                        if (isset($node['programme_end']) && $at->copy()->addMinutes($duration)->gt($node['programme_end'])) continue;
                        if (($node['fixture_kind'] ?? 'individual') === 'team') {
                            foreach ($nodes as $later) {
                                if ((! $later['fixed'] && ! $later['played']) || ! in_array($id, $later['dependencies'], true)) continue;
                                $laterSlot = $this->nodeSlot($later);
                                if ($laterSlot?->time && $at->copy()->addMinutes($duration + $playerRest)->gt(Carbon::parse($laterSlot->time))) {
                                    $blocked[$id] = 'A saved later team tie leaves insufficient time for this rubber and required rest.';
                                    continue 2;
                                }
                            }
                        }
                        $choice = ['id' => $id, 'time' => $at, 'venue_id' => $venueId, 'court' => (string) $court,
                            'tie_priority' => $activeTie && $node['tie_allocation_key'] === $activeTie ? 0 : 1,
                            'fairness' => $scheduledPerDraw[$node['draw_id']] ?? 0,
                            'rank_penalty' => ($node['rank_preference']['venue_id'] ?? null) && (int) $node['rank_preference']['venue_id'] !== (int) $venueId ? 1 : 0,
                            'venue_changes' => $calendar->venueChanges($node['participants'], $at, (int) $venueId)];
                        if ($best === null || $this->isEarlier($choice, $best, $nodes)) $best = $choice;
                    }
                }
            }
            if ($best === null) {
                if ($activeTie && ! isset($incompleteTies[$activeTie])) {
                    $warnings[] = $activeTieLabel.' could not be completely allocated because its remaining rubbers are blocked. Those rubbers remain for review.';
                }
                break;
            }
            $id = $best['id'];
            $node = $nodes[$id];
            if ($tieAllocation === 'complete_tie') {
                if ($activeTie && $node['tie_allocation_key'] !== $activeTie && ! isset($incompleteTies[$activeTie])) {
                    $warnings[] = $activeTieLabel.' could not be completely allocated before switching ties because its remaining rubbers are blocked. Other matches use available courts; any unallocated rubbers remain for review.';
                    $incompleteTies[$activeTie] = true;
                }
                $activeTie = $node['tie_allocation_key'];
                $activeTieLabel = $node['draw_name'].' ('.implode(' vs ', $node['participant_names']).')';
            }
            if ($node['rank_preference']['warning'] ?? null) $warnings[] = $node['draw_name'].' match '.$node['match'].': '.$node['rank_preference']['warning'];
            if ($best['rank_penalty']) $warnings[] = $node['draw_name'].' match '.$node['match'].': the preferred roster rank venue is unavailable in this window; another assigned venue is used.';
            foreach ($best['venue_changes'] as $change) {
                $warnings[] = $this->venueWarning($change, $venues);
                $venueChangeWarnings[] = $this->venueChangeDetails($change, $node, $best, $venues);
            }
            $calendar->reserveWithRest($best['venue_id'], $best['court'], $best['time'], $duration + $courtGap,
                $duration + $playerRest, $node['participants'], $node['participant_group'], true, $this->nodeSource($node));
            $finished[$id] = $best['time']->copy()->addMinutes($duration + $playerRest);
            $genderSlots[$id] = ['venue_id' => (int) $best['venue_id'], 'time' => $best['time']->copy(), 'duration' => $duration];
            $scheduledPerDraw[$node['draw_id']] = ($scheduledPerDraw[$node['draw_id']] ?? 0) + 1;
            $plan[] = [
                'fixture_id' => $node['fixture']->id, 'fixture_kind' => $node['fixture_kind'] ?? 'individual',
                'fixture_key' => ($node['fixture_kind'] ?? 'individual').':'.$node['fixture']->id, 'draw_id' => $node['draw_id'], 'draw_name' => $node['draw_name'],
                'stage' => $node['stage'], 'round' => $node['round'], 'match' => $node['match'],
                'play_order' => $node['play_order'], 'wave' => $node['wave'],
                'programme_day' => $node['programme_day'] ?? null, 'programme_sequence' => $node['programme_sequence'] ?? null,
                'dependencies' => $node['dependencies'],
                'not_before' => $node['not_before']->format('Y-m-d H:i:s'),
                'scheduled_at' => $best['time']->format('Y-m-d H:i:s'), 'venue_id' => $best['venue_id'],
                'venue_name' => $venues[$best['venue_id']]->name, 'court' => $best['court'],
                'duration' => $duration, 'participants' => $node['participant_names'],
                'lineup' => ($node['fixture_kind'] ?? '') === 'team' ? $node['fixture']->lineup_display : [],
                'participant_ids' => $node['participants'], 'venue_courts' => $node['venue_courts'],
                'venue_changes' => $best['venue_changes'], 'rank_preference' => $node['rank_preference'],
            ];
            unset($pending[$id], $blocked[$id]);
            if ($activeTie && ! collect($pending)->contains(fn ($remaining) => $remaining['tie_allocation_key'] === $activeTie)) $activeTie = null;
        }

        foreach ($genderSlots as $id => $slot) {
            $node = $nodes[$id];
            if (! $node['fixed'] || ! $node['gender'] || ($node['programme_gender_waves'] ?? $genderWaves) === 'combined' || ! isset($sharedGenderVenues[$slot['venue_id']])) continue;
            $phase = $this->genderPhase($node, $genderWaves);
            $phaseStarts = [];
            if ($genderRelease === 'court_ready') {
                foreach ($genderSlots as $earlierId => $earlierSlot) {
                    $earlier = $nodes[$earlierId];
                    if (! $earlier['gender'] || $earlier['automatic'] || $earlierSlot['venue_id'] !== $slot['venue_id']) continue;
                    $earlierPhase = $this->genderPhase($earlier, $genderWaves);
                    if ($earlierPhase >= $phase) continue;
                    $phaseStarts[$earlierPhase] = isset($phaseStarts[$earlierPhase])
                        ? $phaseStarts[$earlierPhase]->min($earlierSlot['time'])->copy() : $earlierSlot['time']->copy();
                }
            }
            foreach ($genderSlots as $earlierId => $earlierSlot) {
                $earlier = $nodes[$earlierId];
                if (! $earlier['gender'] || $earlier['automatic'] || $earlierSlot['venue_id'] !== $slot['venue_id']) continue;
                $earlierPhase = $this->genderPhase($earlier, $genderWaves);
                $required = $genderRelease === 'court_ready'
                    ? ($phaseStarts[$earlierPhase] ?? $earlierSlot['time'])->copy()->addMinutes($waveMinutes)
                    : $earlierSlot['time']->copy()->addMinutes(max($waveMinutes, $earlierSlot['duration'] + $courtGap));
                if ($earlierPhase < $phase && $required->gt($slot['time'])) {
                    $order = ($node['programme_gender_waves'] ?? $genderWaves) === 'girls_then_boys' ? 'girls then boys' : 'boys then girls';
                    $warnings[] = $node['draw_name'].' match '.$node['match'].": the saved time does not follow {$order} waves. Replan this venue to change saved times.";
                    break;
                }
            }
        }

        $unscheduled = [];
        foreach ($pending as $id => $node) {
            $reason = $blocked[$id] ?? ($end ? 'No valid court time remains before the scheduling window ends.'
                : 'A qualifying match is not schedulable in this plan.');
            $unscheduled[] = [
                'fixture_id' => $node['fixture']->id, 'fixture_kind' => $node['fixture_kind'] ?? 'individual',
                'fixture_key' => ($node['fixture_kind'] ?? 'individual').':'.$node['fixture']->id, 'draw_id' => $node['draw_id'], 'draw_name' => $node['draw_name'],
                'stage' => $node['stage'], 'round' => $node['round'], 'match' => $node['match'],
                'play_order' => $node['play_order'], 'wave' => $node['wave'],
                'dependencies' => $node['dependencies'],
                'not_before' => $node['not_before']->format('Y-m-d H:i:s'),
                'scheduled_at' => null, 'venue_id' => null, 'venue_name' => null, 'court' => null,
                'programme_day' => $node['programme_day'] ?? null, 'programme_sequence' => $node['programme_sequence'] ?? null,
                'duration' => $duration, 'participants' => $node['participant_names'],
                'lineup' => ($node['fixture_kind'] ?? '') === 'team' ? $node['fixture']->lineup_display : [],
                'participant_ids' => $node['participants'], 'venue_courts' => $node['venue_courts'],
                'reason' => $reason,
            ];
        }

        usort($plan, function ($a, $b) {
            $order = [$a['scheduled_at'], $a['venue_id']] <=> [$b['scheduled_at'], $b['venue_id']];
            return $order ?: strnatcasecmp((string) $a['court'], (string) $b['court']);
        });
        $displayEnd = $end?->copy() ?? collect($plan)->map(fn ($row) => Carbon::parse($row['scheduled_at'])
            ->addMinutes((int) $row['duration']))->max();
        $existingMatches = $displayEnd ? OrderOfPlay::with([
            'fixture.draw', 'fixture.registration1.players', 'fixture.registration2.players',
        ])
            ->whereIn('venue_id', $venueIds)->whereNotNull('time')
            ->when($excludedIndividual, fn ($query) => $query->whereNotIn('fixture_id', $excludedIndividual))
            ->where('time', '>=', $start->copy()->subMinutes(600))->where('time', '<', $displayEnd)
            ->orderBy('time')->orderBy('venue_id')->orderBy('court')->limit(2000)->get()
            ->filter(fn (OrderOfPlay $slot) => Carbon::parse($slot->time)->addMinutes($slot->occupiedMinutes($duration))->gt($start))
            ->map(function (OrderOfPlay $slot) use ($duration, $nodes, $event) {
                $startsAt = Carbon::parse($slot->time);
                $fixture = $slot->fixture;
                $sameEvent = (int) $fixture?->draw?->event_id === (int) $event->id;
                $node = $nodes[$slot->fixture_id] ?? null;
                $participants = $sameEvent ? ($node['participant_names'] ?? collect([
                    $fixture?->registration1, $fixture?->registration2,
                ])->filter()->map(fn ($registration) => $registration->displayName())
                    ->filter(fn ($name) => $name && $name !== 'Unassigned')->values()->all()) : [];
                return [
                    'fixture_id' => $slot->fixture_id, 'fixture_kind' => 'individual', 'fixture_key' => 'individual:'.$slot->fixture_id, 'draw_id' => $fixture?->draw_id ?? $slot->draw_id,
                    'draw_name' => $sameEvent ? ($fixture?->draw?->drawName ?? 'Existing booking') : 'Existing booking',
                    'round' => $sameEvent ? max(1, (int) ($fixture?->round ?? $slot->round_number)) : null,
                    'match' => $sameEvent ? $fixture?->match_nr : null, 'scheduled_at' => $startsAt->format('Y-m-d H:i:s'),
                    'ends_at' => $startsAt->copy()->addMinutes($slot->occupiedMinutes($duration))->format('Y-m-d H:i:s'),
                    'venue_id' => (int) $slot->venue_id, 'court' => (string) $slot->court,
                    'duration' => (int) ($slot->duration_minutes ?: $duration),
                    'participants' => $participants,
                    'wave' => $node['wave'] ?? null, 'dependencies' => $node['dependencies'] ?? [],
                    'programme_day' => $node['programme_day'] ?? null, 'programme_sequence' => $node['programme_sequence'] ?? null,
                    'not_before' => isset($node['not_before']) ? $node['not_before']->format('Y-m-d H:i:s') : null,
                    'participant_ids' => $node['participants'] ?? [], 'venue_courts' => $node['venue_courts'] ?? [],
                    'editable' => isset($node) && $node['selected_round'] && ! $node['played'],
                ];
            })->values()->all() : [];
        if ($displayEnd) {
            $existingMatches = array_merge($existingMatches, TeamFixture::with(['draw', 'fixturePlayers.noProfile1', 'fixturePlayers.noProfile2', 'teamTie.homeTeam', 'teamTie.awayTeam'])
                ->whereIn('venue_id', $venueIds)->whereNotIn('id', $excludedTeam)->whereNotNull('scheduled_at')
                ->where('scheduled_at', '>=', $start->copy()->subMinutes(600))->where('scheduled_at', '<', $displayEnd)
                ->orderBy('scheduled_at')->limit(2000)->get()
                ->filter(fn ($fixture) => Carbon::parse($fixture->scheduled_at)->addMinutes((int) ($fixture->duration_min ?: 120) + (int) ($fixture->gap_minutes ?? 0))->gt($start))
                ->pipe(function ($fixtures) use ($event) {
                    $visible = $fixtures->filter(fn ($fixture) => (int) $fixture->draw?->event_id === (int) $event->id)->values();
                    if ($visible->isNotEmpty()) app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($visible);
                    return $fixtures;
                })->map(function ($fixture) use ($nodes, $event) {
                    $node = $nodes['team:'.$fixture->id] ?? null;
                    $sameEvent = (int) $fixture->draw?->event_id === (int) $event->id;
                    return ['fixture_id' => $fixture->id, 'fixture_kind' => 'team', 'fixture_key' => 'team:'.$fixture->id,
                        'draw_id' => $fixture->draw_id, 'draw_name' => $sameEvent ? ($fixture->draw?->drawName ?? 'Existing booking') : 'Existing booking',
                        'round' => $sameEvent ? max(1, (int) $fixture->round_nr) : null, 'match' => $sameEvent ? ($fixture->match_nr ?: $fixture->rubber_sequence) : null,
                        'scheduled_at' => Carbon::parse($fixture->scheduled_at)->format('Y-m-d H:i:s'),
                        'ends_at' => Carbon::parse($fixture->scheduled_at)->addMinutes((int) ($fixture->duration_min ?: 120) + (int) ($fixture->gap_minutes ?? 0))->format('Y-m-d H:i:s'),
                        'venue_id' => (int) $fixture->venue_id, 'court' => ScheduleAvailability::courtKey((string) $fixture->court_label),
                        'duration' => (int) ($fixture->duration_min ?: 120),
                        'participants' => $sameEvent ? ($node['participant_names'] ?? [$fixture->teamTie?->home_side_name ?: 'Home team', $fixture->teamTie?->away_side_name ?: 'Away team']) : ['Existing booking'],
                        'lineup' => $sameEvent ? $fixture->lineup_display : [],
                        'participant_ids' => $node['participants'] ?? [], 'venue_courts' => $node['venue_courts'] ?? [],
                        'wave' => $node['wave'] ?? null, 'dependencies' => $node['dependencies'] ?? [],
                        'programme_day' => $node['programme_day'] ?? null, 'programme_sequence' => $node['programme_sequence'] ?? null,
                        'not_before' => isset($node['not_before']) ? $node['not_before']->format('Y-m-d H:i:s') : null,
                        'editable' => isset($node) && $node['selected_round'] && ! $node['played']];
                })->values()->all());
        }
        if (($options['allow_partial'] ?? false) && $unscheduled && $replanVenues) {
            // Unplaced replanned matches retain their original bookings. Build the
            // fitting batch around those originals rather than occupying their slots.
            $kept = $this->preview($event, array_replace($options, ['replan_venue_ids' => []]));
            $kept['warnings'][] = 'Some matches did not fit. Existing bookings were kept fixed so saving this batch cannot overlap a retained match. Use a larger window to change those bookings.';
            return $kept;
        }
        $input = compact('duration', 'waveMinutes', 'courtGap', 'playerRest') + [
            'start' => $start->format('Y-m-d H:i:s'), 'end' => $end?->format('Y-m-d H:i:s'), 'round_progression' => $roundProgression,
            'draw_ids' => $draws->pluck('id')->sort()->values()->all(), 'venue_ids' => $venueIds->sort()->values()->all(),
            'replan_venue_ids' => collect($replanVenues)->sort()->values()->all(),
            'allow_partial' => (bool) ($options['allow_partial'] ?? false),
            'rank_venue_preferences' => $rankRules, 'cross_band_policy' => $crossBandPolicy,
            'gender_waves' => $genderWaves,
            'gender_wave_release' => $genderRelease,
            'draw_rounds' => $drawRounds,
            'programme' => $programme,
            'tie_allocation' => $tieAllocation,
            'draw_starts' => $drawStarts->map(fn ($time, $drawId) => ['draw_id' => (int) $drawId,
                'start' => $time->format('Y-m-d H:i:s')])->values()->all(),
            'venue_starts' => $venueStarts->map(fn ($time, $venueId) => ['venue_id' => (int) $venueId,
                'start' => $time->format('Y-m-d H:i:s')])->values()->all(),
        ];

        return [
            'event' => ['id' => $event->id, 'name' => $event->name],
            'venues' => $venues->map(fn ($venue) => ['id' => $venue->id, 'name' => $venue->name,
                'courts' => count($courtLabels[$venue->id]), 'court_labels' => $courtLabels[$venue->id]])->values()->all(),
            'matches' => $plan, 'existing_matches' => $existingMatches,
            'unscheduled' => $unscheduled, 'warnings' => $warnings, 'venue_change_warnings' => $venueChangeWarnings,
            'automatic_byes' => collect($nodes)->filter(fn ($node) => $node['selected_round'] && $node['automatic'] && ! $node['played'])->count(),
            'automatic_fixture_ids' => collect($nodes)->filter(fn ($node) => $node['selected_round'] && $node['automatic'] && ! $node['played'])->keys()->values()->all(),
            'revision' => $this->revision($event, $input + ['availability_revision' => $availabilityRevision, 'rank_revision' => $rankChoices]), 'input' => $input,
        ];
    }

    public function apply(Event $event, array $options, string $expectedRevision): array
    {
        return DB::transaction(function () use ($event, $options, $expectedRevision) {
            $applyVenueIds = array_values(array_unique(array_map('intval', $options['apply_venue_ids'] ?? [])));
            if (! empty($options['programme'])) {
                if ($applyVenueIds) throw new \InvalidArgumentException('Save the complete three-day programme together after every match fits.');
                $options['allow_partial'] = false;
            }
            unset($options['apply_venue_ids']);
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $drawIds = $event->draws()->orderBy('id')->lockForUpdate()->pluck('id');
            DB::table('draws')->whereIn('id', $drawIds)->orderBy('id')->lockForUpdate()->get();
            $teamIds = TeamFixture::whereIn('draw_id', $drawIds)->orderBy('id')->lockForUpdate()->pluck('id');
            DB::table('team_fixture_results')->whereIn('team_fixture_id', $teamIds)->orderBy('id')->lockForUpdate()->get();
            DB::table('team_fixture_players')->whereIn('team_fixture_id', $teamIds)->orderBy('id')->lockForUpdate()->get();
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
            if (! ($options['allow_partial'] ?? false) && ! $applyVenueIds && $preview['unscheduled']) {
                throw new \InvalidArgumentException('The preview contains unscheduled matches. Resolve them before applying.');
            }
            if (! ($options['allow_partial'] ?? false) && $applyVenueIds && collect($preview['unscheduled'])->contains(function ($row) use ($applyVenueIds) {
                $permittedVenueIds = array_map('intval', array_keys($row['venue_courts'] ?? []));
                return (bool) array_intersect($applyVenueIds, $permittedVenueIds);
            })) {
                throw new \InvalidArgumentException('This venue still has unresolved matches. Schedule them before applying the venue.');
            }

            $matches = collect($preview['matches'])
                ->when($applyVenueIds, fn (Collection $rows) => $rows->whereIn('venue_id', $applyVenueIds))->values();
            if ($applyVenueIds && $matches->isEmpty()) {
                throw new \InvalidArgumentException('This venue has no new or changed fixtures to apply.');
            }
            if ($applyVenueIds) {
                $selectedFixtureIds = $matches->map(fn ($row) => $row['fixture_kind'] === 'team' ? $row['fixture_key'] : $row['fixture_id'])->all();
                $otherPlannedFixtureIds = collect($preview['matches'])->map(fn ($row) => $row['fixture_kind'] === 'team' ? $row['fixture_key'] : $row['fixture_id'])->diff($selectedFixtureIds)->all();
                if ($matches->contains(fn ($row) => array_intersect($row['dependencies'] ?? [], $otherPlannedFixtureIds))) {
                    throw new \InvalidArgumentException('This venue contains a match that depends on an unapplied match at another venue. Apply the prerequisite venue first or apply the combined schedule.');
                }
            }
            // Validate the exact applied subset against every booking that remains.
            // Preview can replan other venues which are not part of this save.
            $calendar = ScheduleAvailability::load($matches->pluck('venue_id')->unique()->all(),
                $matches->flatMap(fn ($row) => $row['participant_ids'] ?? [])->unique()->all(),
                $matches->where('fixture_kind', 'individual')->pluck('fixture_id')->all(), null,
                (int) ($preview['input']['playerRest'] ?? 60), $matches->where('fixture_kind', 'team')->pluck('fixture_id')->all(),
                null, null, ! empty($options['programme']) ? Carbon::parse($preview['input']['start']) : null,
                ! empty($options['programme']) ? Carbon::parse($preview['input']['end'])->addMinutes(max((int) $preview['input']['playerRest'], (int) $preview['input']['courtGap'])) : null);
            $calendarDraws = Draw::whereIn('id', $matches->pluck('draw_id'))->with('flexibleMonrad')->get()->keyBy('id');
            foreach ($matches->sortBy('scheduled_at') as $row) {
                $at = Carbon::parse($row['scheduled_at']);
                $courtMinutes = (int) $row['duration'] + (int) ($preview['input']['courtGap'] ?? 0);
                $playerMinutes = (int) $row['duration'] + (int) ($preview['input']['playerRest'] ?? 60);
                $group = $calendarDraws[$row['draw_id']]->usesFlexibleMonrad() ? 'flexible-draw-'.$row['draw_id'] : null;
                $available = $calendar->nextAvailableForMatch($at, $courtMinutes, $playerMinutes,
                    (int) $row['venue_id'], (string) $row['court'], $row['participant_ids'] ?? [], $group);
                if (! $available->eq($at)) throw new \InvalidArgumentException('This batch overlaps a booking that is being kept. Save the combined schedule or generate a new preview with those bookings fixed.');
                $calendar->reserveWithRest((int) $row['venue_id'], (string) $row['court'], $at,
                    $courtMinutes, $playerMinutes, $row['participant_ids'] ?? [], $group);
            }
            $scheduledFixtureIds = $matches->pluck('fixture_id')->all();
            $appliedDrawIds = $matches->pluck('draw_id')->unique()->values();
            $automaticFixtureIds = Fixture::whereIn('id', $preview['automatic_fixture_ids'])
                ->when($applyVenueIds, fn ($query) => $query->whereIn('draw_id', $appliedDrawIds))->pluck('id');
            if ($automaticFixtureIds->isNotEmpty()) {
                OrderOfPlay::whereIn('fixture_id', $automaticFixtureIds)->delete();
                Fixture::whereIn('id', $automaticFixtureIds)->update(['scheduled' => 0]);
            }
            $auditAssignments = [];
            foreach ($matches as $row) {
                if ($row['fixture_kind'] === 'team') {
                    $fixture = TeamFixture::whereKey($row['fixture_id'])->where('draw_id', $row['draw_id'])->firstOrFail();
                    $auditAssignments[$row['draw_id']][] = ['fixture_kind' => 'team', 'fixture_id' => $fixture->id,
                        'before' => $fixture->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes']),
                        'after' => array_intersect_key($row, array_flip(['scheduled_at', 'venue_id', 'court', 'duration']))];
                    $fixture->forceFill(['scheduled_at' => $row['scheduled_at'], 'venue_id' => $row['venue_id'],
                        'court_label' => $row['court'], 'duration_min' => $row['duration'],
                        'gap_minutes' => (int) ($preview['input']['courtGap'] ?? 0), 'scheduled' => 1, 'clash_flag' => false])->save();
                    continue;
                }
                $fixture = Fixture::whereKey($row['fixture_id'])->where('draw_id', $row['draw_id'])->firstOrFail();
                $auditAssignments[$row['draw_id']][] = ['fixture_kind' => 'individual', 'fixture_id' => $fixture->id,
                    'before' => $fixture->orderOfPlay?->only(['time', 'venue_id', 'court', 'duration_minutes']),
                    'after' => array_intersect_key($row, array_flip(['scheduled_at', 'venue_id', 'court', 'duration']))];
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
                    'partial' => (bool) $applyVenueIds, 'assignments' => $auditAssignments[$drawId] ?? [],
                ]);
            }
            return ['count' => count($scheduledFixtureIds), 'revision' => $expectedRevision,
                'venue_ids' => $matches->pluck('venue_id')->unique()->values()->all()];
        });
    }

    public function removalError(Event $event, array $individualFixtureIds = [], array $teamFixtureIds = []): ?string
    {
        if ($teamFixtureIds && ($error = app(UnifiedTeamScheduleService::class)->removalError($event, $teamFixtureIds))) return $error;
        $drawIds = Fixture::whereIn('id', $individualFixtureIds)->whereHas('draw', fn ($q) => $q->where('event_id', $event->id))->pluck('draw_id')->unique();
        foreach (Draw::with(['drawFixtures.orderOfPlay', 'drawFixtures.fixtureResults', 'flexibleMonrad', 'groups'])->whereIn('id', $drawIds)->get() as $draw) {
            foreach ($this->nodesForDraw($draw, Carbon::now(), 90) as $id => $node) {
                if (in_array($id, $individualFixtureIds) || ! $node['fixture']->orderOfPlay?->time) continue;
                if (array_intersect($individualFixtureIds, $node['dependencies'])) return 'A saved later match depends on this assignment. Return the dependent match to planning too.';
            }
        }
        return null;
    }

    public function unapply(Event $event, ?int $drawId = null, ?int $venueId = null, ?int $fixtureId = null): array
    {
        if ($fixtureId !== null) return $this->unapplyIndividual($event, $drawId, $venueId, $fixtureId);
        if (collect([$drawId, $venueId])->filter(fn ($id) => $id !== null)->count() !== 1) {
            throw new \InvalidArgumentException('Choose one draw or one venue to return to planning.');
        }
        return DB::transaction(function () use ($event, $drawId, $venueId) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $hasTeam = TeamFixture::whereHas('draw', fn ($q) => $q->where('event_id', $event->id))
                ->whereNotNull('scheduled_at')->when($drawId !== null, fn ($q) => $q->where('draw_id', $drawId))
                ->when($venueId !== null, fn ($q) => $q->where('venue_id', $venueId))->exists();
            $hasIndividual = Fixture::whereHas('draw', fn ($q) => $q->where('event_id', $event->id))
                ->when($drawId !== null, fn ($q) => $q->where('draw_id', $drawId))
                ->whereHas('orderOfPlay', fn ($q) => $q->whereNotNull('time')
                    ->when($venueId !== null, fn ($q) => $q->where('venue_id', $venueId)))->exists();
            if (! $hasTeam) return $this->unapplyIndividual($event, $drawId, $venueId);
            $team = app(UnifiedTeamScheduleService::class)->unapply($event, $drawId, $venueId);
            $individual = $hasIndividual ? $this->unapplyIndividual($event, $drawId, $venueId) : ['count' => 0];
            $count = $team['count'] + $individual['count'];
            return ['count' => $count, 'message' => $count.' matches returned to planning. Fixtures and results were preserved.'];
        });
    }

    private function unapplyIndividual(Event $event, ?int $drawId = null, ?int $venueId = null, ?int $fixtureId = null): array
    {
        if (collect([$drawId, $venueId, $fixtureId])->filter(fn ($id) => $id !== null)->count() !== 1) {
            throw new \InvalidArgumentException('Choose one match, one draw, or one venue to return to planning.');
        }
        if ($fixtureId !== null) return $this->unapplyFixture($event, $fixtureId);

        return DB::transaction(function () use ($event, $drawId, $venueId) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
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
            if ($fixtures->contains(fn (Fixture $fixture) => $fixture->draw?->locked)) {
                throw new \InvalidArgumentException('A locked draw is included. Unlock it before removing scheduled times.');
            }
            if ($fixtures->contains(fn (Fixture $fixture) => ($fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0))) {
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
            if ($error = $this->removalError($event, $bookingFixtureIds->all())) throw new \InvalidArgumentException($error);
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

    private function unapplyFixture(Event $event, int $fixtureId): array
    {
        $fixture = Fixture::with(['draw', 'fixtureResults', 'orderOfPlay'])
            ->whereHas('draw', fn ($draws) => $draws->where('event_id', $event->id))
            ->find($fixtureId);
        if (! $fixture) throw new \InvalidArgumentException('This match does not belong to the event.');
        if (! $fixture->orderOfPlay?->time) throw new \InvalidArgumentException('This match has no applied assignment to remove.');
        if ($fixture->draw?->locked) throw new \InvalidArgumentException('Unlock the draw before removing this match assignment.');
        if (($fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0)) throw new \InvalidArgumentException('A played match cannot be returned to planning.');

        if ($error = $this->removalError($event, [$fixture->id])) throw new \InvalidArgumentException($error);
        if ($fixture->draw->usesFlexibleMonrad()) {
            app(\App\Services\Draw\FlexibleMonradScheduler::class)
                ->saveFixture($fixture->draw, $fixture->id, null, 0, '', null);
        } else {
            app(ScheduleEngine::class)->saveFixture($fixture->draw, $fixture->id, null, 0, '', null, true);
        }
        DrawAuditLog::record($fixture->draw_id, 'event_venue_schedule_unapplied', null, [
            'event_id' => $event->id, 'matches' => 1, 'scope' => 'fixture', 'fixture_id' => $fixture->id,
        ]);

        return ['count' => 1, 'message' => 'One match assignment was removed. Other scheduled matches were preserved.'];
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
                    $match['sources']))), $participants[$key] ?? [], (bool) $match['automatic'], (bool) $match['sets'] || $fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0,
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
                $participants[$fixture->id] ?? [], $automatic, ($fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0), $labels);
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

    private function nodeSlot(array $node): ?OrderOfPlay
    {
        return ($node['fixture_kind'] ?? 'individual') === 'team'
            ? app(UnifiedTeamScheduleService::class)->slot($node['fixture']) : $node['fixture']->orderOfPlay;
    }

    private function venueWarning(array $change, Collection $venues): string
    {
        [$kind, $id] = explode(':', $change['participant_id'], 2);
        $player = $kind === 'profile' ? \App\Models\Player::find($id) : \App\Models\NoProfileTeamPlayer::find($id);
        $name = $player ? trim($player->name.' '.$player->surname) : 'Player '.$id;
        $from = $venues[$change['from_venue_id']]->name ?? Venue::find($change['from_venue_id'])?->name ?? 'another venue';
        $to = $venues[$change['to_venue_id']]->name ?? 'another venue';
        return $name.' changes venue from '.$from.' to '.$to.'.';
    }

    private function nodeSource(array $node): array
    {
        return ['fixture_key' => ($node['fixture_kind'] ?? 'individual').':'.$node['fixture']->id,
            'draw_name' => $node['draw_name'], 'discipline' => $node['stage'],
            'round' => $node['round'], 'match' => $node['match']];
    }

    private function venueChangeDetails(array $change, array $node, array $choice, Collection $venues): array
    {
        $preferred = $node['rank_preference']['venue_id'] ?? null;
        $reason = $preferred
            ? ($choice['rank_penalty']
                ? 'Another permitted venue was selected instead of the configured roster rank venue preference. Preferences are advisory; the planner also considers tie allocation, venue continuity, court and player availability.'
                : 'This match uses its configured roster rank venue preference. That preference may differ between disciplines or partners.')
            : (($node['rank_preference']['warning'] ?? null) ? $node['rank_preference']['warning'].' ' : '').'No roster rank venue preference applies to this match. The planner selected a permitted venue using court and player availability and its scheduling priorities.';
        $from = $change['from_booking'] ?? ['fixture' => null];
        $from['venue_name'] = $venues[$change['from_venue_id']]->name ?? Venue::find($change['from_venue_id'])?->name ?? 'Another venue';
        return ['message' => $this->venueWarning($change, $venues), 'reason' => $reason,
            'reason_code' => $preferred ? ($choice['rank_penalty'] ? 'rank_fallback' : 'rank_preference') : 'unrestricted',
            'from' => $from, 'to' => ['fixture' => $this->nodeSource($node),
                'scheduled_at' => $choice['time']->format('Y-m-d H:i:s'), 'court' => $choice['court'],
                'venue_id' => $choice['venue_id'], 'venue_name' => $venues[$choice['venue_id']]->name]];
    }

    private function wave(int|string $id, array &$nodes, array $visiting = []): int
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
        return ([$candidate['tie_priority'] ?? 1, $candidate['rank_penalty'] ?? 0, count($candidate['venue_changes'] ?? []), $candidate['time']->timestamp, $candidate['fairness'], $nodes[$candidate['id']]['wave'],
            $nodes[$candidate['id']]['play_order'], $candidate['id']]
            <=> [$best['tie_priority'] ?? 1, $best['rank_penalty'] ?? 0, count($best['venue_changes'] ?? []), $best['time']->timestamp, $best['fairness'], $nodes[$best['id']]['wave'],
                $nodes[$best['id']]['play_order'], $best['id']]) < 0;
    }

    public function availableDrawRounds(Collection $draws): array
    {
        $teamIds = $draws->filter(fn ($draw) => $draw->isTeamDraw())->pluck('id');
        $individualIds = $draws->reject(fn ($draw) => $draw->isTeamDraw())->pluck('id');
        $rows = Fixture::whereIn('draw_id', $individualIds)->select('draw_id', 'round')->distinct()->get()
            ->concat(TeamFixture::whereIn('draw_id', $teamIds)->selectRaw('draw_id, round_nr as round')->distinct()->get());
        return $rows->groupBy('draw_id')->map(fn ($rounds) => $rounds->pluck('round')->map(fn ($round) => max(1, (int) $round))
            ->unique()->sort()->values()->all())->all();
    }

    public function normalizeDrawRounds(Collection $draws, array $rows): array
    {
        $available = $this->availableDrawRounds($draws);
        $seen = [];
        $normalized = [];
        foreach ($rows as $row) {
            $id = (int) ($row['draw_id'] ?? 0);
            if (! $draws->contains('id', $id)) throw new \InvalidArgumentException('Round choices belong to an unselected draw or another event.');
            if (isset($seen[$id])) throw new \InvalidArgumentException('Choose rounds once per draw.');
            $seen[$id] = true;
            $rounds = $row['rounds'] ?? [];
            if (! is_array($rounds) || ! $rounds) throw new \InvalidArgumentException('Select at least one round, or choose all rounds.');
            foreach ($rounds as $round) {
                if (filter_var($round, FILTER_VALIDATE_INT) === false || ! in_array((int) $round, $available[$id] ?? [], true)) {
                    throw new \InvalidArgumentException('A selected round does not exist in this draw.');
                }
            }
            $rounds = array_map('intval', $rounds);
            if (count($rounds) !== count(array_unique($rounds))) throw new \InvalidArgumentException('A round can be selected only once per draw.');
            sort($rounds);
            $normalized[] = ['draw_id' => $id, 'rounds' => $rounds];
        }
        usort($normalized, fn ($a, $b) => $a['draw_id'] <=> $b['draw_id']);
        return $normalized;
    }

    private function tieAllocationKey(array $node): ?string
    {
        if (($node['fixture_kind'] ?? 'individual') !== 'team') return null;
        $fixture = $node['fixture'];
        $scope = 'draw:'.$node['draw_id'].':';
        if ($fixture->team_tie_id) return $scope.'tie:'.$fixture->team_tie_id;
        $regions = array_filter([(int) $fixture->region1, (int) $fixture->region2]);
        sort($regions);
        if ($fixture->tie_nr || count($regions) === 2) {
            return $scope.'round:'.$node['round'].':legacy:'.(int) $fixture->tie_nr.':'.implode('-', $regions);
        }
        return $scope.'rubber:'.$fixture->id;
    }

    private function drawGender(Draw $draw): ?string
    {
        $gender = strtolower(trim((string) $draw->gender));
        if ($gender !== '') {
            return match ($gender) {
                'boys', 'boy', 'male', 'men', '1' => 'boys',
                'girls', 'girl', 'female', 'women', '2' => 'girls',
                default => null,
            };
        }
        $name = strtolower((string) $draw->drawName);
        if (preg_match('/\bmixed\b/', $name)) return null;
        if (preg_match('/\b(girls?|female|women)\b/', $name)) return 'girls';
        return preg_match('/\b(boys?|male|men)\b/', $name) ? 'boys' : null;
    }

    private function revision(Event $event, array $input): string
    {
        $drawIds = $event->draws()->pluck('id');
        $fixtures = DB::table('fixtures')->whereIn('draw_id', $drawIds)->orderBy('id')
            ->get(['id', 'draw_id', 'registration1_id', 'registration2_id', 'winner_registration',
                'parent_fixture_id', 'loser_parent_fixture_id', 'round', 'stage', 'play_order', 'updated_at']);
        $teamIds = DB::table('team_fixtures')->whereIn('draw_id', $drawIds)->pluck('id');
        $state = [
            'team_fixtures' => DB::table('team_fixtures')->whereIn('draw_id', $drawIds)->orderBy('id')->get()->all(),
            'team_players' => DB::table('team_fixture_players')->whereIn('team_fixture_id', $teamIds)->orderBy('id')->get()->all(),
            'team_results' => DB::table('team_fixture_results')->whereIn('team_fixture_id', $teamIds)->orderBy('id')->get()->all(),
            'team_ties' => DB::table('team_ties')->whereIn('draw_id', $drawIds)->orderBy('id')->get()->all(),
            'input' => $input,
            'draws' => DB::table('draws')->whereIn('id', $drawIds)->orderBy('id')
                ->get(['id', 'locked', 'published', 'gender', 'drawName', 'updated_at'])->all(),
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

    private function genderPhase(array $node, string $fallback): int
    {
        $order = $node['programme_gender_waves'] ?? $fallback;
        $first = $order === 'girls_then_boys' ? 'girls' : 'boys';
        return 2 * ($node['wave'] - 1) + ($order === 'combined' || $node['gender'] === $first ? 0 : 1);
    }
}
