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
    public function preservePreviewOptions(array $options, array $preview, ?array $move = null): array
    {
        $moves = collect($preview['matches'])->map(fn ($row) => ['fixture_key'=>$row['fixture_key'], 'scheduled_at'=>$row['scheduled_at'], 'venue_id'=>(int) $row['venue_id'], 'court'=>(string) $row['court']]);
        if ($move) $moves = $moves->reject(fn ($row) => $row['fixture_key'] === $move['fixture_key'])->push($move);
        $options['preview_moves'] = $moves->values()->all();
        $options['preview_frozen_keys'] = collect($preview['unscheduled'])->pluck('fixture_key')->reject(fn ($key) => $move && $key === $move['fixture_key'])->values()->all();
        return $options;
    }

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
        $duration = (int) ($options['duration'] ?? 60);
        $waveMinutes = (int) ($options['wave_minutes'] ?? 90);
        $courtGap = (int) ($options['court_gap'] ?? 5);
        $playerRest = (int) ($options['player_rest'] ?? 30);
        $selectedDraws = array_map('intval', $options['draw_ids'] ?? []);
        $selectedVenues = array_map('intval', $options['venue_ids'] ?? []);
        $dayScope = $options['day_scope'] ?? null;
        $fullDay = $options['full_day_scope'] ?? null;
        $dayBatch = ! empty($options['day_batch']);
        $drawOrderMode = $options['draw_order_mode'] ?? 'mixed';
        if ($dayBatch && (! $fullDay || ! in_array($drawOrderMode, ['mixed', 'sequential'], true))) throw new \InvalidArgumentException('Choose one playing day and a valid draw order mode.');
        if ($dayBatch && ! in_array($fullDay, app(ScheduleProgramme::class)->eventDates($event), true)) throw new \InvalidArgumentException('Choose a playing day within this event.');
        if ($dayBatch) $genderWaves = 'combined';
        if ($dayBatch && (empty($options['draw_order']) || empty($options['day_court_labels']) || ! array_key_exists('selected_fixture_keys', $options))) throw new \InvalidArgumentException('Choose draw order, specific matches and courts for this day.');
        $selectedFixtureKeys = array_key_exists('selected_fixture_keys', $options) ? array_values($options['selected_fixture_keys']) : null;
        if ($selectedFixtureKeys !== null && (! $dayBatch || ! $selectedFixtureKeys || count(array_unique($selectedFixtureKeys)) !== count($selectedFixtureKeys))) throw new \InvalidArgumentException('Choose at least one match once within the selected rounds.');
        if ($fullDay && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fullDay) || $dayScope || ! $selectedDraws || ! $selectedVenues || ! $end
            || $start->toDateString() !== $fullDay || $end->toDateString() !== $fullDay || $end->lte($start)
            || ! empty($options['programme']) || empty($options['draw_rounds']))) {
            throw new \InvalidArgumentException('Choose draws and rounds, event venues, and a time window within one day.');
        }
        if ($fullDay) {
            $options['allow_partial'] = false;
            if (! $dayBatch) $options['rank_venue_preferences'] = [];
        }
        if ($dayScope && (count($selectedDraws) !== 1 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayScope)
            || $start->toDateString() !== $dayScope || ! $end || $end->toDateString() !== $dayScope
            || $end->lte($start) || ! $selectedVenues || ! empty($options['programme']))) {
            throw new \InvalidArgumentException('Choose one draw, one day, and event venues for this day edit.');
        }
        if ($dayScope) $options['allow_partial'] = false;

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
            'drawFixtures.orderOfPlay', 'drawFixtures.fixtureResults', 'flexibleMonrad', 'groups', 'settings',
        ])->when($selectedDraws, fn ($query) => $query->whereIn('id', $selectedDraws))->get();

        $foreignDraws = array_diff($selectedDraws, $draws->pluck('id')->map(fn ($id) => (int) $id)->all());
        if ($foreignDraws) throw new \InvalidArgumentException('One or more selected draws do not belong to this event.');
        $drawOrder = array_map('intval', $options['draw_order'] ?? $draws->pluck('id')->all());
        if ($dayBatch && (count($drawOrder) !== $draws->count() || count(array_unique($drawOrder)) !== count($drawOrder)
            || array_diff($drawOrder, $draws->pluck('id')->all()) || $draws->pluck('id')->diff($drawOrder)->isNotEmpty())) throw new \InvalidArgumentException('Draw order must include each selected draw exactly once.');
        $programme = app(ScheduleProgramme::class)->normalize($options['programme'] ?? [], $draws, $this->availableDrawRounds($draws, true), $event);
        if ($programme) {
            $options['allow_partial'] = false;
            $programmeService = app(ScheduleProgramme::class);
            if ($draws->contains(fn ($draw) => $draw->isTeamDraw())) {
                $ages = $draws->map(fn ($draw) => $programmeService->key($draw))->unique();
                if ($ages->count() !== 1 || $ages->first() === null) throw new \InvalidArgumentException('Select one complete age group for the programme.');
                $expected = $event->draws()->get()->filter(fn ($draw) => $programmeService->supports($draw) && $programmeService->key($draw) === $ages->first())->pluck('id');
                if ($expected->diff($draws->pluck('id'))->isNotEmpty()) throw new \InvalidArgumentException('Include every discipline of the selected age group.');
            }
            if ($draws->contains('locked', true)) throw new \InvalidArgumentException('Unlock all age-group draws before creating a complete programme.');
            if ($options['draw_rounds'] ?? []) throw new \InvalidArgumentException('A complete programme cannot also filter rounds.');
            if ($draws->contains(fn ($draw) => ! $programmeService->supports($draw))) throw new \InvalidArgumentException('The event programme requires team or individual Monrad draws.');
            $start = Carbon::parse($programme['days'][0]['start']);
            $end = Carbon::parse($programme['days'][array_key_last($programme['days'])]['end']);
        }
        $drawRounds = $this->normalizeDrawRounds($draws, $options['draw_rounds'] ?? [], $dayBatch);
        if ($dayBatch && $draws->pluck('id')->diff(array_column($drawRounds, 'draw_id'))->isNotEmpty()) throw new \InvalidArgumentException('Choose rounds for every selected draw.');
        $roundSelection = collect($drawRounds)->mapWithKeys(fn ($row) => [$row['draw_id'] => $row['rounds']]);
        if ($fullDay && $draws->pluck('id')->diff($roundSelection->keys())->isNotEmpty()) throw new \InvalidArgumentException('Choose rounds for every selected draw.');
        if ($drawStarts->keys()->diff($draws->pluck('id')->map(fn ($id) => (int) $id))->isNotEmpty()) {
            throw new \InvalidArgumentException('An age-group start time belongs to an unselected draw.');
        }
        if ($drawStarts->contains(fn (Carbon $drawStart) => $drawStart->lt($start))) {
            throw new \InvalidArgumentException('An age-group start time cannot be earlier than the event schedule start.');
        }

        $storedRoundDraft = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        $limitDailyMatches = ! empty($options['limit_daily_matches']) || ! empty($storedRoundDraft['limit_daily_matches']);
        $roundSetups = collect($storedRoundDraft['round_venue_setups'] ?? [])->whereIn('draw_id', $draws->pluck('id'))->keyBy(fn ($row) => $row['draw_id'].'|'.$row['round']);
        if (($dayScope || $fullDay) && $draws->contains('locked', true)) throw new \InvalidArgumentException('Unlock selected draws before editing their day schedule.');
        $venueIds = $draws->flatMap(fn (Draw $draw) => $draw->venues->pluck('id'))->merge($roundSetups->flatMap(fn ($row) => $row['venue_ids']))->unique()->values();
        if ($dayScope) $venueIds = $event->venues()->pluck('venues.id')->merge($venueIds)->unique()->values();
        $fullDayCourts = $fullDay ? $this->fullDayCourtLabels($event) : [];
        if ($fullDay) $venueIds = collect(array_keys($fullDayCourts));
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
        $courtLabels = $fullDay
            ? array_intersect_key($fullDayCourts, array_flip($venues->keys()->all()))
            : $this->courtLabels($event, $draws, $venues);
        if (($dayScope || $fullDay) && isset($options['day_court_labels'])) {
            $selectedCourts = $options['day_court_labels'];
            if (array_diff(array_keys($selectedCourts), $venueIds->all()) || array_diff($venueIds->all(), array_keys($selectedCourts))) {
                throw new \InvalidArgumentException('Choose courts for every selected day venue only.');
            }
            foreach ($courtLabels as $venueId => $active) {
                $labels = array_values(array_unique(array_map('strval', $selectedCourts[$venueId] ?? [])));
                if (count($labels) !== count($selectedCourts[$venueId] ?? [])) throw new \InvalidArgumentException('Choose each physical court once within its venue.');
                if (! $labels || array_diff($labels, $active)) throw new \InvalidArgumentException('Choose active physical courts for this day.');
                $courtLabels[$venueId] = array_values(array_intersect($active, $labels));
            }
        }
        $dayVenueGroups = [];
        $courtGroups = [];
        foreach ($options['day_venue_groups'] ?? [] as $venueId => $groups) {
            if (! $fullDay || ! isset($courtLabels[$venueId]) || ! is_array($groups)
                || count($groups) !== 2 || ! isset($groups['A'], $groups['B']) || ! is_array($groups['A']) || ! is_array($groups['B'])) {
                throw new \InvalidArgumentException('Split only selected day venues into Group A and Group B.');
            }
            $all = [];
            foreach (['A', 'B'] as $group) {
                $labels = array_map('strval', $groups[$group]);
                if (! $labels || array_diff($labels, $courtLabels[$venueId])) throw new \InvalidArgumentException('Each court group needs at least one selected active court at its own venue.');
                $all = array_merge($all, $labels);
                $dayVenueGroups[$venueId][$group] = array_values(array_intersect($courtLabels[$venueId], $labels));
                foreach ($labels as $label) $courtGroups[$venueId][ScheduleAvailability::courtKey($label)] = $group;
            }
            if (count($all) !== count(array_unique(array_map([ScheduleAvailability::class, 'courtKey'], $all))) || array_diff($courtLabels[$venueId], $all)) {
                throw new \InvalidArgumentException('Assign every selected court to exactly one group. Court groups cannot overlap.');
            }
        }
        ksort($dayVenueGroups);
        $fullDayVenueWindows = [];
        foreach ($options['full_day_venue_windows'] ?? [] as $venueId => $window) {
            if (! $fullDay || ! isset($courtLabels[$venueId]) || ! is_array($window)
                || ! isset($window['start'], $window['end'])
                || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $window['start'])
                || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $window['end'])) {
                throw new \InvalidArgumentException('Choose valid opening and finishing times for selected day venues only.');
            }
            $opens = Carbon::parse($window['start']);
            $closes = Carbon::parse($window['end']);
            if ($opens->toDateString() !== $fullDay || $closes->toDateString() !== $fullDay || $closes->lte($opens)
                || $opens->lt($start) || $closes->gt($end)) throw new \InvalidArgumentException('Venue hours must finish after they start and fit within the overall playing day.');
            $fullDayVenueWindows[$venueId] = ['start'=>$opens->format('Y-m-d H:i:s'), 'end'=>$closes->format('Y-m-d H:i:s')];
        }
        ksort($fullDayVenueWindows);
        $fullDayCourtStarts = [];
        $courtStarts = [];
        $courtEnds = [];
        foreach ($options['full_day_court_starts'] ?? [] as $venueId => $rows) {
            if (! $fullDay || ! isset($courtLabels[$venueId]) || ! is_array($rows)) throw new \InvalidArgumentException('Court availability must belong to a selected day venue.');
            foreach ($rows as $row) {
                if (! is_array($row) || ! isset($row['court'], $row['start']) || ! in_array((string) $row['court'], $courtLabels[$venueId], true)
                    || ! is_string($row['start']) || ! preg_match('/^\d{4}-\d{2}-\d{2} ([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $row['start'])) throw new \InvalidArgumentException('Choose an available-from time for a selected active court at its own venue.');
                if (isset($row['end']) && (! is_string($row['end']) || ! preg_match('/^\d{4}-\d{2}-\d{2} ([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $row['end']))) throw new \InvalidArgumentException('Choose a valid court finishing time.');
                $key = ScheduleAvailability::courtKey((string) $row['court']);
                if (isset($courtStarts[$venueId][$key])) throw new \InvalidArgumentException('Set each court availability once.');
                $opens = Carbon::parse($row['start']);
                $venueOpen = Carbon::parse($fullDayVenueWindows[$venueId]['start'] ?? $start);
                $venueClose = Carbon::parse($fullDayVenueWindows[$venueId]['end'] ?? $end);
                if ($opens->toDateString() !== $fullDay || $opens->lt($venueOpen) || $opens->gte($venueClose)) throw new \InvalidArgumentException('Court availability must fit within its venue hours and the playing day.');
                $closes = isset($row['end']) ? Carbon::parse($row['end']) : $venueClose->copy();
                if ($closes->toDateString() !== $fullDay || $closes->lte($opens) || $closes->gt($venueClose)) throw new \InvalidArgumentException('Court finishing time must follow its start and fit within venue hours.');
                $courtStarts[$venueId][$key] = $opens;
                $courtEnds[$venueId][$key] = $closes;
                $fullDayCourtStarts[$venueId][] = ['court'=>(string) $row['court'], 'start'=>$opens->format('Y-m-d H:i:s'), 'end'=>$closes->format('Y-m-d H:i:s')];
            }
            if (isset($fullDayCourtStarts[$venueId])) usort($fullDayCourtStarts[$venueId], fn ($a, $b) => strcmp($a['court'], $b['court']));
        }
        ksort($fullDayCourtStarts);
        $destinationPools = [];
        $drawPoolKeys = [];
        $poolCourtLabels = [];
        $poolDefinitions = $fullDay ? $this->destinationPoolDefinitions($draws) : [];
        foreach ($poolDefinitions as $definition) foreach ($definition['draw_ids'] as $drawId) $drawPoolKeys[$drawId] = $definition['key'];
        $allowedPoolKeys = array_column($poolDefinitions, 'key');
        foreach ($options['full_day_destination_pools'] ?? [] as $key => $destinations) {
            if (! $fullDay || ! in_array($key, $allowedPoolKeys, true) || ! is_array($destinations) || ! $destinations) {
                throw new \InvalidArgumentException('Choose at least one venue or court group for each custom pool in the selected age groups or draws.');
            }
            foreach ($destinations as $destination) {
                if (! is_string($destination) || ! preg_match('/^(\d+)(?::([AB]))?$/', $destination, $parts)) throw new \InvalidArgumentException('Choose valid venue or court group destinations for each age group.');
                $venueId = (int) $parts[1];
                $group = $parts[2] ?? null;
                if (empty($courtLabels[$venueId]) || (isset($dayVenueGroups[$venueId]) ? $group === null || ! isset($dayVenueGroups[$venueId][$group]) : $group !== null)) {
                    throw new \InvalidArgumentException('Age-group pools can use selected venues and their active court groups only.');
                }
                $token = (string) $venueId.($group ? ':'.$group : '');
                if (in_array($token, $destinationPools[$key] ?? [], true)) throw new \InvalidArgumentException('Choose each destination once per age-group pool.');
                $destinationPools[$key][] = $token;
                $poolCourtLabels[$key][$venueId] = array_values(array_unique(array_merge($poolCourtLabels[$key][$venueId] ?? [], $group ? $dayVenueGroups[$venueId][$group] : $courtLabels[$venueId])));
            }
            sort($destinationPools[$key]);
            $orderedCourts = [];
            foreach ($courtLabels as $venueId => $labels) {
                if (isset($poolCourtLabels[$key][$venueId])) $orderedCourts[$venueId] = array_values(array_intersect($labels, $poolCourtLabels[$key][$venueId]));
            }
            $poolCourtLabels[$key] = $orderedCourts;
        }
        ksort($destinationPools);

        $allocations = DB::table('draw_venue_court_allocations')->whereIn('draw_id', $draws->pluck('id'))
            ->get()->groupBy(fn ($row) => $row->draw_id.'|'.$row->venue_id);

        $preferenceService = app(RankVenuePreferences::class);
        $storedDraft = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        $rankRuleWarnings = [];
        $rankRules = [];
        if (array_key_exists('rank_venue_preferences', $options)) {
            $rankRules = $dayScope
                ? $preferenceService->normalizeDay($event, $draws->first(), $options['rank_venue_preferences'], $venueIds->all())
                : $preferenceService->normalize($event, $options['rank_venue_preferences']);
        } else {
            foreach ($preferenceService->active($storedDraft['rank_venue_preferences'] ?? [], $draws->pluck('id')->all()) as $rule) {
                try { $rankRules = array_merge($rankRules, $preferenceService->normalize($event, [$rule])); }
                catch (\InvalidArgumentException $exception) { $rankRuleWarnings[] = 'A saved roster rank venue preference needs review. Normal venue scheduling is used: '.$exception->getMessage(); }
            }
        }
        $rankRules = $preferenceService->active($rankRules, $draws->pluck('id')->all());
        $bandedDrawIds = $dayBatch ? collect($rankRules)->flatMap(fn ($rule) => $rule['draw_ids'])->map(fn ($id) => (int) $id)->unique()->all() : [];
        $wholeTieForNode = fn (array $node) => $fullDay && ! in_array((int) $node['draw_id'], $bandedDrawIds, true);
        if ($dayBatch && $rankRules && ! empty($options['full_day_tie_destinations'])) throw new \InvalidArgumentException('Position-band venues and whole-tie destinations cannot be combined in one day batch.');
        $crossBandPolicy = $programme ? 'highest_ranked' : ($options['cross_band_policy'] ?? $storedDraft['cross_band_policy'] ?? 'highest_ranked');
        if (! in_array($crossBandPolicy, ['highest_ranked', 'manual'], true)) throw new \InvalidArgumentException('Choose a valid cross-band rule.');

        $nodes = [];
        $excluded = [];
        $eligibleFixtureKeys = [];
        $dayRoundCoverage = [];
        $warnings = $rankRuleWarnings;
        if ($dayBatch && $rankRules) $warnings[] = 'Saved position bands control the team venue allocations for this day; a tie may use more than one configured venue.';
        foreach ($draws as $draw) {
            if ($draw->locked) {
                $warnings[] = "{$draw->drawName} was not changed because the draw is locked.";
                continue;
            }
            foreach (($draw->isTeamDraw() ? app(UnifiedTeamScheduleService::class)->nodes($draw, $roundProgression) : $this->nodesForDraw($draw, $start, $waveMinutes, (bool) $programme || $dayBatch)) as $id => $node) {
                $timing = app(MatchTimingService::class)->forDraw($draw, $duration, $courtGap);
                $node['timed'] = ! $draw->isTeamDraw() && $draw->settings?->score_format === 'timed';
                $node['duration'] = $timing['duration_minutes'];
                $node['court_gap'] = $timing['court_gap_minutes'];
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
                $roundSetup = $roundSetups[$draw->id.'|'.($node['venue_round'] ?? $node['round'])] ?? null;
                if ($roundSetup) {
                    $node['venue_courts'] = [];
                    foreach ($roundSetup['court_allocations'] as $allocation) {
                        $venueId = (int) $allocation['venue_id'];
                        if (isset($courtLabels[$venueId])) $node['venue_courts'][$venueId] = array_values(array_intersect(array_map('strval', $allocation['court_labels']), $courtLabels[$venueId]));
                    }
                    $node['venue_courts'] = array_filter($node['venue_courts']);
                }
                $slot = $this->nodeSlot($node);
                if ($fullDay) {
                    $node['selected_round'] = $node['selected_round'] && (! $slot?->time || Carbon::parse($slot->time)->toDateString() === $fullDay);
                    if ($node['selected_round']) $node['venue_courts'] = $poolCourtLabels[$drawPoolKeys[$draw->id]] ?? array_filter($courtLabels);
                }
                if ($dayScope) {
                    $node['selected_round'] = $slot?->time && Carbon::parse($slot->time)->toDateString() === $dayScope;
                    if ($node['selected_round']) {
                        $node['venue_courts'] = array_filter($courtLabels);
                    }
                }
                if ($dayBatch && $node['selected_round']) {
                    $key = ($node['fixture_kind'] ?? 'individual').':'.$node['fixture']->id;
                    $eligibleFixtureKeys[] = $key;
                    if ($selectedFixtureKeys !== null) $node['selected_round'] = in_array($key, $selectedFixtureKeys, true);
                }
                if ($dayBatch && $node['selected_round'] && ! $node['automatic']) $dayRoundCoverage[$draw->id.'|'.$node['round']] = true;
                if ($node['selected_round'] && $slot?->time && in_array((int) $slot->venue_id, $replanVenues, true)) {
                    $node['replan_original_venue'] = (int) $slot->venue_id;
                }
                $node['fixed'] = ! $node['played'] && $slot?->time
                    && (! $node['selected_round'] || ($dayBatch ? empty($options['reschedule_existing']) : (! $dayScope && ! $fullDay && ! in_array((int) $slot->venue_id, $replanVenues, true))));
                $nodes[$id] = $node;
                if ($node['selected_round'] && ! $node['played'] && ! $node['fixed']) $excluded[] = $id;
            }
        }

        if ($selectedFixtureKeys !== null && array_diff($selectedFixtureKeys, $eligibleFixtureKeys)) throw new \InvalidArgumentException('Choose matches in the selected event draws, rounds and playing day only.');
        if ($dayBatch) foreach ($drawRounds as $row) foreach ($row['rounds'] as $round) {
            if (! isset($dayRoundCoverage[$row['draw_id'].'|'.$round])) throw new \InvalidArgumentException('Choose matches in every selected round for this day, or remove the empty round.');
        }
        if ($dayBatch && $drawOrderMode === 'sequential') $this->configureDayDrawOrder($nodes, $drawOrder);

        app(ScheduleProgramme::class)->configureNodes($nodes, $programme);
        $rankChoices = $preferenceService->choices(collect($nodes)->filter(fn ($node) => ($node['fixture_kind'] ?? '') === 'team')
            ->map(fn ($node) => $node['fixture'])->values(), $rankRules, $crossBandPolicy);
        $displayFixtures = collect($nodes)->filter(fn ($node) => ($node['fixture_kind'] ?? '') === 'team')
            ->map(fn ($node) => $node['fixture'])->filter(fn ($fixture) => $fixture->lineup_display === null)->values();
        if ($displayFixtures->isNotEmpty()) app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($displayFixtures);
        foreach ($nodes as &$node) {
            $node['rank_preference'] = $rankChoices[$node['fixture']->id] ?? null;
            $roundSetup = $roundSetups[$node['draw_id'].'|'.($node['venue_round'] ?? $node['round'])] ?? null;
            if ($roundSetup && ! (($dayScope || $fullDay) && $node['selected_round']) && ($node['fixture_kind'] ?? '') === 'team') {
                $node['rank_preference'] = $preferenceService->choices(collect([$node['fixture']]), $roundSetup['rank_venue_preferences'], $crossBandPolicy)[$node['fixture']->id] ?? null;
            }
            if (($node['fixture_kind'] ?? '') !== 'team') $node['rank_preference'] = null;
            else $rankChoices[$node['fixture']->id] = $node['rank_preference'];
            $rankVenue = $node['rank_preference']['venue_id'] ?? null;
            if ($rankVenue && ! $node['fixed'] && ! $node['played']) {
                $node['venue_courts'] = array_intersect_key($node['venue_courts'], [$rankVenue => true]);
            } elseif (! $dayScope && isset($node['replan_original_venue']) && ! $node['fixed'] && ! $node['played']) {
                $node['venue_courts'] = array_intersect_key($node['venue_courts'], [$node['replan_original_venue'] => true]);
            }
        }
        unset($node);

        $previewMoves = [];
        if (count($options['preview_moves'] ?? []) > 2000) throw new \InvalidArgumentException('Keep at most 2000 suggested matches in one preview.');
        foreach ($options['preview_moves'] ?? [] as $move) {
            $key = (string) ($move['fixture_key'] ?? '');
            if (! preg_match('/^(individual|team):([1-9][0-9]*)$/', $key, $parts) || isset($previewMoves[$key])) throw new \InvalidArgumentException('Choose each suggested match once.');
            $id = $parts[1] === 'team' ? $key : (int) $parts[2];
            $node = $nodes[$id] ?? null;
            if (! $node || ! $node['selected_round'] || $node['fixed'] || $node['played'] || $node['automatic']) throw new \InvalidArgumentException('Move only an unsaved suggested match in the selected event draws and rounds.');
            $at = Carbon::parse($move['scheduled_at']); $venue = (int) $move['venue_id']; $court = (string) $move['court'];
            if (! in_array($court, array_map('strval', $node['venue_courts'][$venue] ?? []), true)) throw new \InvalidArgumentException('Choose a permitted active court for this suggested match.');
            if ($at->lt($start) || ($end && $at->copy()->addMinutes($node['duration'])->gt($end))
                || (isset($node['programme_end']) && $at->copy()->addMinutes($node['duration'])->gt($node['programme_end']))) throw new \InvalidArgumentException('Move the match within its selected playing day and time window.');
            $normalized = ['fixture_key'=>$key, 'scheduled_at'=>$at->format('Y-m-d H:i:s'), 'venue_id'=>$venue, 'court'=>$court];
            $previewMoves[$key] = $normalized;
            $nodes[$id]['preview_move'] = $normalized;
        }
        ksort($previewMoves);
        $frozenKeys = array_values($options['preview_frozen_keys'] ?? []);
        if (count($frozenKeys) > 2000 || count($frozenKeys) !== count(array_unique($frozenKeys))) throw new \InvalidArgumentException('Keep each unallocated match once.');
        foreach ($frozenKeys as $key) {
            if (! preg_match('/^(individual|team):([1-9][0-9]*)$/', $key, $parts) || isset($previewMoves[$key])) throw new \InvalidArgumentException('Choose a distinct unscheduled match in this preview.');
            $id = $parts[1] === 'team' ? $key : (int) $parts[2]; $node = $nodes[$id] ?? null;
            if (! $node || ! $node['selected_round'] || $node['fixed'] || $node['played'] || $node['automatic']) throw new \InvalidArgumentException('Keep only selected unsaved matches in planning.');
            $nodes[$id]['preview_frozen'] = true;
        }
        sort($frozenKeys);

        $allRegistrations = collect($nodes)->flatMap(fn ($node) => $node['participants'])->unique()->values()->all();
        $excludedIndividual = array_values(array_filter($excluded, 'is_int'));
        $excludedTeam = collect($excluded)->filter(fn ($id) => is_string($id) && str_starts_with($id, 'team:'))->map(fn ($id) => (int) substr($id, 5))->all();
        $dailyLimit = $limitDailyMatches ? DailyMatchLimit::load($event, array_merge(
            array_map(fn ($id) => 'individual:'.$id, $excludedIndividual), array_map(fn ($id) => 'team:'.$id, $excludedTeam))) : null;
        $calendar = ScheduleAvailability::load(array_keys($courtLabels), $allRegistrations, $excludedIndividual, null, $playerRest, $excludedTeam, $event, $start,
            $programme ? $start : null, $programme ? $end->copy()->addMinutes(max($playerRest, $courtGap, ...array_column($nodes, 'court_gap'))) : null);
        $availabilityRevision = $calendar->fingerprint();
        $pending = [];
        $finished = [];
        foreach ($nodes as $id => &$node) {
            $node['wave'] = $this->wave($id, $nodes);
            $node['not_before'] = $node['draw_start']->copy()->addMinutes(($programme || $dayScope || $fullDay) ? 0 : ($node['wave'] - 1) * $waveMinutes);
            if (isset($node['preview_move'])) $node['not_before'] = $node['not_before']->max(Carbon::parse($node['preview_move']['scheduled_at']))->copy();
            if ($programme) {
                $node['wave'] = $node['programme_phase'];
                $slot = $this->nodeSlot($node);
                if (($node['fixed'] || $node['played']) && $slot?->time && (Carbon::parse($slot->time)->lt($node['draw_start']) || Carbon::parse($slot->time)->addMinutes((int) ($slot->duration_minutes ?: $node['duration']))->gt($node['programme_end']))) {
                    throw new \InvalidArgumentException('A saved match is outside its programme day. Review or return it to planning before creating this programme.');
                }
                if (($node['fixed'] || $node['played']) && $slot?->time && $node['programme_break'] && Carbon::parse($slot->time)->lt($node['programme_break'][1]) && Carbon::parse($slot->time)->addMinutes((int) ($slot->duration_minutes ?: $node['duration']))->gt($node['programme_break'][0])) throw new \InvalidArgumentException('A saved match overlaps a programme break. Review its time before creating this programme.');
            }
            if ($node['automatic']) {
                $finished[$id] = $node['not_before']->copy();
            } elseif ($node['played']) {
                $slot = $this->nodeSlot($node);
                $finished[$id] = $slot?->time
                    ? Carbon::parse($slot->time)->addMinutes((int) ($slot->duration_minutes ?: $node['duration']) + $playerRest)
                    : $node['not_before']->copy();
            } elseif ($node['fixed']) {
                $slot = $this->nodeSlot($node);
                $finished[$id] = Carbon::parse($slot->time)
                    ->addMinutes((int) ($slot->duration_minutes ?: $node['duration']) + $playerRest);
            } elseif ($node['selected_round']) {
                $pending[$id] = $node;
            }
        }
        unset($node);

        // A playoff-only preview still honours already scheduled group feeders.
        foreach ($nodes as $node) foreach ($node['dependencies'] as $dependency) {
            if (isset($nodes[$dependency]) || isset($finished[$dependency]) || !str_starts_with((string) $dependency, 'team:')) continue;
            $feeder = \App\Models\TeamFixture::whereKey((int) substr($dependency, 5))
                ->whereHas('draw', fn ($q) => $q->where('event_id', $event->id))->first();
            if ($feeder?->scheduled_at) $finished[$dependency] = Carbon::parse($feeder->scheduled_at)->addMinutes((int) ($feeder->duration_min ?: 120) + $playerRest);
        }

        if ($previewMoves) foreach ($nodes as $later) {
            $laterSlot = $this->nodeSlot($later);
            $laterTime = $later['preview_move']['scheduled_at'] ?? (($later['fixed'] || $later['played']) ? $laterSlot?->time : null);
            if (! $laterTime) continue;
            foreach ($later['dependencies'] as $dependency) {
                $earlier = $nodes[$dependency] ?? null;
                if (! $earlier) continue;
                $earlierSlot = $this->nodeSlot($earlier);
                $earlierTime = $earlier['preview_move']['scheduled_at'] ?? (($earlier['fixed'] || $earlier['played']) ? $earlierSlot?->time : null);
                if (! $earlierTime) continue;
                $minutes = isset($earlier['preview_move']) ? $earlier['duration'] : (int) ($earlierSlot?->duration_minutes ?: $earlier['duration']);
                $startOnly = in_array($dependency, $later['programme_start_dependencies'] ?? [], true);
                $finishOnly = in_array($dependency, $later['day_finish_dependencies'] ?? [], true);
                $rest = ($startOnly || $finishOnly) ? 0 : $playerRest;
                $release = Carbon::parse($earlierTime)->addMinutes($startOnly ? 0 : $minutes + $rest);
                if ($release->gt(Carbon::parse($laterTime))) throw new \InvalidArgumentException(
                    'Match '.$later['match'].' stays at '.Carbon::parse($laterTime)->format('H:i').', but Match '.$earlier['match'].' at '.Carbon::parse($earlierTime)->format('H:i')
                    .' needs '.($startOnly ? 'the required programme order' : $minutes.' minutes of play'.($rest ? ' and '.$rest.' minutes player rest' : ''))
                    .'. The earliest start for Match '.$later['match'].' would be '.$release->format('H:i').'. Other match slots were not changed.');
            }
        }

        if ($programme || ($dayBatch && $drawOrderMode === 'sequential')) {
            foreach ($nodes as $node) {
                if (! $node['fixed'] && ! $node['played']) continue;
                $slot = $this->nodeSlot($node);
                if (! $slot?->time) continue;
                foreach ($node['dependencies'] as $dependency) {
                    if (! isset($finished[$dependency])) continue;
                    $dependencySlot = isset($nodes[$dependency]) ? $this->nodeSlot($nodes[$dependency]) : null;
                    $dependencyRelease = in_array($dependency, $node['programme_start_dependencies'] ?? [], true) && $dependencySlot?->time
                        ? Carbon::parse($dependencySlot->time) : ($finished[$dependency] ?? null);
                    if (in_array($dependency, $node['day_finish_dependencies'] ?? [], true)) $dependencyRelease = $this->dayFinishRelease($dependency, $nodes, $finished, [], $duration);
                    if ($dependencyRelease && $dependencyRelease->gt(Carbon::parse($slot->time))) {
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
                    'duration' => (int) ($slot->duration_minutes ?: $node['duration']), 'court_gap' => (int) ($slot->gap_minutes ?? $node['court_gap'])];
            }
        }
        $scheduledPerDraw = [];
        $activeTie = null;
        $activeTieLabel = null;
        $incompleteTies = [];
        $started = [];
        foreach ($nodes as $id => $node) {
            if (isset($finished[$id])) $started[$id] = $this->nodeSlot($node)?->time
                ? Carbon::parse($this->nodeSlot($node)->time) : $node['not_before']->copy();
        }
        $blocked = [];
        $tieVenues = [];
        $tieGroups = [];
        foreach ($nodes as $node) {
            if (! isset($node['preview_move']) || ! $wholeTieForNode($node) || ! $node['tie_allocation_key']) continue;
            $move = $node['preview_move']; $key = $node['tie_allocation_key'];
            $group = $courtGroups[$move['venue_id']][ScheduleAvailability::courtKey($move['court'])] ?? null;
            if (isset($tieVenues[$key]) && ($tieVenues[$key] !== $move['venue_id'] || $tieGroups[$key] !== $group)) throw new \InvalidArgumentException('Suggested moves in one team tie must use the same venue and court group.');
            $tieVenues[$key] = $move['venue_id']; $tieGroups[$key] = $group;
        }
        $plannedTieKeys = collect($pending)->pluck('tie_allocation_key')->filter()->flip();
        $plannedTieNodes = collect($pending)->groupBy('tie_allocation_key');
        $tieDestinations = [];
        foreach ($options['full_day_tie_destinations'] ?? [] as $key => $destination) {
            if (! $fullDay || ! $plannedTieKeys->has($key) || ! is_array($destination)) throw new \InvalidArgumentException('Choose destinations only for ties in the selected draws, rounds and day.');
            $venueId = (int) ($destination['venue_id'] ?? 0);
            $group = $destination['group'] ?? null;
            if (empty($courtLabels[$venueId]) || (isset($dayVenueGroups[$venueId])
                ? ! is_string($group) || ! isset($dayVenueGroups[$venueId][$group]) : $group !== null)) {
                throw new \InvalidArgumentException('Choose a selected venue or one of its active court groups for each tie.');
            }
            $destinationCourts = $group ? $dayVenueGroups[$venueId][$group] : $courtLabels[$venueId];
            foreach ($plannedTieNodes[$key] as $node) {
                if (! array_intersect($node['venue_courts'][$venueId] ?? [], $destinationCourts)) throw new \InvalidArgumentException('The chosen tie destination is outside its age group\'s allowed venues. Change the age-group pool or choose another tie destination.');
            }
            $tieDestinations[$key] = ['venue_id'=>$venueId, 'group'=>$group];
            $tieVenues[$key] = $venueId;
            $tieGroups[$key] = $group;
        }
        ksort($tieDestinations);
        foreach ($nodes as $node) {
            if (! $wholeTieForNode($node)) continue;
            $slot = $this->nodeSlot($node);
            $key = $node['tie_allocation_key'];
            if ($key && $plannedTieKeys->has($key) && ($node['fixed'] || $node['played']) && $slot?->time) {
                if (isset($tieVenues[$key]) && $tieVenues[$key] !== (int) $slot->venue_id) throw new \InvalidArgumentException('The chosen tie venue conflicts with a fixed rubber, or the fixed tie is already split between venues. Review its saved matches before planning this day.');
                $venueId = (int) $slot->venue_id;
                $group = $courtGroups[$venueId][ScheduleAvailability::courtKey((string) $slot->court)] ?? null;
                if (isset($dayVenueGroups[$venueId]) && $group === null) throw new \InvalidArgumentException('A fixed rubber uses a court outside the selected groups. Include that court before planning the rest of its tie.');
                if (array_key_exists($key, $tieGroups) && $tieGroups[$key] !== $group) throw new \InvalidArgumentException('The chosen tie group conflicts with a fixed rubber, or the fixed tie is already split between court groups. Review its saved matches or change the court groups.');
                foreach ($plannedTieNodes[$key] as $pendingNode) {
                    $anchorCourts = $group ? $dayVenueGroups[$venueId][$group] : ($courtLabels[$venueId] ?? []);
                    if (! array_intersect($pendingNode['venue_courts'][$venueId] ?? [], $anchorCourts)) throw new \InvalidArgumentException('The age-group venue pool conflicts with a fixed rubber in this tie. Include its venue and court group before planning the remaining rubbers.');
                }
                $tieVenues[$key] = (int) $slot->venue_id;
                $tieGroups[$key] = $group;
            }
        }
        while ($pending) {
            $best = null;
            foreach ($pending as $id => $node) {
                $matchDuration = $node['duration'];
                $matchGap = $node['court_gap'];
                // Re-evaluate the current blocker after another prerequisite has been placed.
                unset($blocked[$id]);
                if (! empty($node['preview_frozen'])) {
                    $blocked[$id] = 'Kept unscheduled while moving one match. Generate a new preview to allocate it.';
                    continue;
                }
                if ($node['rank_preference']['manual'] ?? false) {
                    $blocked[$id] = $node['rank_preference']['warning'];
                    continue;
                }
                if (! $node['venue_courts']) {
                    $blocked[$id] = ($node['rank_preference']['venue_id'] ?? null)
                        ? 'The assigned roster rank venue is not selected or has no allocated courts. Select that venue and allocate courts, or explicitly change the rank band.'
                        : 'No permitted venue with courts is selected.';
                    continue;
                }
                $release = $nodes[$id]['not_before']->copy();
                foreach ($node['dependencies'] as $dependency) {
                    if (! isset($finished[$dependency])) {
                        $prerequisite = $nodes[$dependency] ?? null;
                        $label = $prerequisite ? $prerequisite['draw_name'].' round '.$prerequisite['round'] : 'An earlier match';
                        if (isset($node['programme_phase'], $prerequisite['programme_phase'])
                            && $prerequisite['programme_phase'] < $node['programme_phase']) {
                            $blocked[$id] = 'Earlier programme section '.$label.' (Day '.$prerequisite['programme_day'].', order '.$prerequisite['programme_sequence'].') must be scheduled first. Review its unallocated matches.';
                        } else {
                            $blocked[$id] = $label.($prerequisite && ! $prerequisite['selected_round'] ? ' in an unselected round' : '')
                                .' must be scheduled first for draw progression.';
                        }
                        continue 2;
                    }
                    $dependencyRelease = in_array($dependency, $node['programme_start_dependencies'] ?? [], true)
                        ? $started[$dependency] : $finished[$dependency];
                    if (in_array($dependency, $node['day_finish_dependencies'] ?? [], true)) $dependencyRelease = $this->dayFinishRelease($dependency, $nodes, $finished, $started, $matchDuration);
                    $release = $release->max($dependencyRelease)->copy();
                }
                $capacityReason = isset($node['programme_end'])
                    ? 'No valid court slot fits before programme Day '.$node['programme_day'].' ends at '.$node['programme_end']->format('H:i').'. Allow more court time or extend this day, while preserving breaks and player rest.'
                    : 'No valid court time remains before the scheduling window ends, allowing for existing bookings and player rest.';
                if ($node['rank_preference']['venue_id'] ?? null) {
                    $rankVenueName = $venues[$node['rank_preference']['venue_id']]->name ?? 'the assigned venue';
                    $capacityReason = 'Roster rank band requires '.$rankVenueName.'. '.$capacityReason.' Add court time there or explicitly change the rank band; automatic planning does not move this match to another venue.';
                }
                $blocked[$id] = $capacityReason;
                $venuePassedGenderOrder = false;
                $choiceCalendar = $calendar;
                if ($previewMoves && ! isset($node['preview_move'])) {
                    $choiceCalendar = clone $calendar;
                    foreach ($nodes as $otherId => $other) {
                        if ($otherId === $id || ! isset($other['preview_move']) || isset($started[$otherId])) continue;
                        $move = $other['preview_move'];
                        $choiceCalendar->reserveWithRest($move['venue_id'], $move['court'], Carbon::parse($move['scheduled_at']),
                            $other['duration'] + $other['court_gap'], $other['duration'] + $playerRest, $other['participants'], $other['participant_group'], false);
                    }
                }
                foreach ($node['venue_courts'] as $venueId => $courts) {
                    if ($wholeTieForNode($node) && isset($tieVenues[$node['tie_allocation_key']]) && $tieVenues[$node['tie_allocation_key']] !== (int) $venueId) {
                        $blocked[$id] = 'This tie must stay at its allocated venue. Extend the day or add courts there to fit every rubber.';
                        continue;
                    }
                    $venueRelease = isset($venueStarts[$venueId]) ? $release->max($venueStarts[$venueId])->copy() : $release;
                    if (isset($fullDayVenueWindows[$venueId])) $venueRelease = $venueRelease->max(Carbon::parse($fullDayVenueWindows[$venueId]['start']))->copy();
                    if (isset($sharedGenderVenues[$venueId]) && $node['gender'] && ($node['programme_gender_waves'] ?? $genderWaves) !== 'combined') {
                        $phase = $this->genderPhase($node, $genderWaves);
                        $phaseStarts = [];
                        foreach ($nodes as $earlierId => $earlier) {
                            if (! $earlier['gender'] || $earlier['automatic']) continue;
                            if (isset($node['programme_phase']) && ($earlier['programme_phase'] ?? null) !== $node['programme_phase']) continue;
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
                                        ->addMinutes(max($waveMinutes, $earlierSlot['duration'] + ($earlierSlot['court_gap'] ?? $matchGap))));
                                }
                            }
                        }
                        foreach ($phaseStarts as $phaseStart) $venueRelease = $venueRelease->copy()->max($phaseStart->copy()->addMinutes($waveMinutes));
                    }
                    $venuePassedGenderOrder = true;
                    foreach ($courts as $court) {
                        if (isset($node['preview_move']) && ((int) $venueId !== $node['preview_move']['venue_id'] || (string) $court !== $node['preview_move']['court'])) continue;
                        $venueGroup = $courtGroups[$venueId][ScheduleAvailability::courtKey((string) $court)] ?? null;
                        if ($wholeTieForNode($node) && array_key_exists($node['tie_allocation_key'] ?? '', $tieGroups) && $tieGroups[$node['tie_allocation_key']] !== $venueGroup) {
                            $blocked[$id] = 'This tie must stay in its allocated court group. Extend the day or add courts to that group to fit every rubber.';
                            continue;
                        }
                        $courtRelease = isset($courtStarts[$venueId][ScheduleAvailability::courtKey((string) $court)])
                            ? $venueRelease->copy()->max($courtStarts[$venueId][ScheduleAvailability::courtKey((string) $court)]) : $venueRelease;
                        $at = $choiceCalendar->nextAvailableForMatch($courtRelease, $matchDuration + $matchGap,
                            $matchDuration + $playerRest, $venueId, (string) $court, $node['participants'],
                            $node['participant_group']);
                        if (($node['programme_break'] ?? null) && $at->lt($node['programme_break'][1]) && $at->copy()->addMinutes($matchDuration)->gt($node['programme_break'][0])) {
                            $at = $choiceCalendar->nextAvailableForMatch($node['programme_break'][1], $matchDuration + $matchGap,
                                $matchDuration + $playerRest, $venueId, (string) $court, $node['participants'], $node['participant_group']);
                        }
                        if (isset($courtEnds[$venueId][ScheduleAvailability::courtKey((string) $court)]) && $at->copy()->addMinutes($matchDuration)->gt($courtEnds[$venueId][ScheduleAvailability::courtKey((string) $court)])) {
                            $blocked[$id] = 'No complete match fits within the selected court availability window. Extend the court hours or choose another court.';
                            continue;
                        }
                        if ($end && $at->copy()->addMinutes($matchDuration)->gt($end)) continue;
                        if (isset($fullDayVenueWindows[$venueId]) && $at->copy()->addMinutes($matchDuration)->gt(Carbon::parse($fullDayVenueWindows[$venueId]['end']))) {
                            $blocked[$id] = 'No court time fits within this venue\'s opening and finishing times. Extend its hours or choose another venue.';
                            continue;
                        }
                        if (isset($node['programme_end']) && $at->copy()->addMinutes($matchDuration)->gt($node['programme_end'])) continue;
                        if (isset($node['preview_move']) && ! $at->equalTo(Carbon::parse($node['preview_move']['scheduled_at']))) {
                            $blocked[$id] = 'The requested slot conflicts with court availability, qualifying matches or player rest. Choose another slot.';
                            continue;
                        }
                        if ($dailyLimit) {
                            $fixtureKey = ($node['fixture_kind'] ?? 'individual').':'.$node['fixture']->id;
                            $dailyError = $dailyLimit->error($fixtureKey, $at->toDateString());
                            // Explicit programme/day choices stay fixed. An open multi-day window may use the next day.
                            while ($dailyError && ! isset($node['preview_move']) && ! $programme && ! $dayScope && ! $fullDay && $end
                                && $at->copy()->addDay()->startOfDay()->lt($end)) {
                                $at = $calendar->nextAvailableForMatch($at->copy()->addDay()->setTimeFrom($start),
                                    $matchDuration + $matchGap, $matchDuration + $playerRest, $venueId, (string) $court, $node['participants'], $node['participant_group']);
                                $dailyError = $dailyLimit->error($fixtureKey, $at->toDateString());
                            }
                            if ($dailyError) {
                                $blocked[$id] = $dailyError;
                                continue;
                            }
                            if ($end && $at->copy()->addMinutes($matchDuration)->gt($end)) continue;
                        }
                        if ($dayScope || $fullDay || ($node['fixture_kind'] ?? 'individual') === 'team') {
                            foreach ($nodes as $later) {
                                if ((! $later['fixed'] && ! $later['played'] && ! isset($later['preview_move'])) || ! in_array($id, $later['dependencies'], true)) continue;
                                $laterSlot = $this->nodeSlot($later);
                                $requiredUntil = in_array($id, $later['programme_start_dependencies'] ?? [], true)
                                    ? $at->copy() : $at->copy()->addMinutes($matchDuration + $playerRest);
                                if (in_array($id, $later['day_finish_dependencies'] ?? [], true)) $requiredUntil = $at->copy()->addMinutes($matchDuration);
                                $laterTime = $later['preview_move']['scheduled_at'] ?? $laterSlot?->time;
                                if ($laterTime && $requiredUntil->gt(Carbon::parse($laterTime))) {
                                    $blocked[$id] = ($node['fixture_kind'] ?? 'individual') === 'team'
                                        ? 'A saved later team tie leaves insufficient time for this rubber and required rest.'
                                        : 'A saved later match leaves insufficient time for this match and required rest.';
                                    continue 2;
                                }
                            }
                        }
                        $choice = ['id' => $id, 'time' => $at, 'venue_id' => $venueId, 'court' => (string) $court,
                            'day_batch' => $dayBatch, 'draw_priority' => array_search($node['draw_id'], $drawOrder, true),
                            'venue_group' => $venueGroup,
                            'tie_priority' => $activeTie && $node['tie_allocation_key'] === $activeTie ? 0 : 1,
                            'fairness' => $scheduledPerDraw[$node['draw_id']] ?? 0,
                            'rank_penalty' => ($node['rank_preference']['venue_id'] ?? null) && (int) $node['rank_preference']['venue_id'] !== (int) $venueId ? 1 : 0,
                            'venue_changes' => $fullDay ? [] : $calendar->venueChanges($node['participants'], $at, (int) $venueId)];
                        if ($best === null || $this->isEarlier($choice, $best, $nodes)) $best = $choice;
                    }
                }
                if ($venuePassedGenderOrder && ($blocked[$id] ?? '') === 'An earlier gender wave must be scheduled first at this shared venue.') {
                    $blocked[$id] = $capacityReason;
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
            $matchDuration = $node['duration'];
            $matchGap = $node['court_gap'];
            $dailyLimit?->reserve(($node['fixture_kind'] ?? 'individual').':'.$node['fixture']->id, $best['time']->toDateString());
            if ($wholeTieForNode($node) && $node['tie_allocation_key']) {
                $tieVenues[$node['tie_allocation_key']] = (int) $best['venue_id'];
                $tieGroups[$node['tie_allocation_key']] = $best['venue_group'];
            }
            if ($tieAllocation === 'complete_tie') {
                if ($activeTie && $node['tie_allocation_key'] !== $activeTie && ! isset($incompleteTies[$activeTie])) {
                    $warnings[] = $activeTieLabel.' could not be completely allocated before switching ties because its remaining rubbers are blocked. Other matches use available courts; any unallocated rubbers remain for review.';
                    $incompleteTies[$activeTie] = true;
                }
                $activeTie = $node['tie_allocation_key'];
                $activeTieLabel = $node['draw_name'].' ('.implode(' vs ', $node['participant_names']).')';
            }
            if ($node['rank_preference']['warning'] ?? null) $warnings[] = $node['draw_name'].' match '.$node['match'].': '.$node['rank_preference']['warning'];
            foreach ($best['venue_changes'] as $change) {
                $warnings[] = $this->venueWarning($change, $venues);
                $venueChangeWarnings[] = $this->venueChangeDetails($change, $node, $best, $venues);
            }
            $calendar->reserveWithRest($best['venue_id'], $best['court'], $best['time'], $matchDuration + $matchGap,
                $matchDuration + $playerRest, $node['participants'], $node['participant_group'], true, $this->nodeSource($node));
            $started[$id] = $best['time']->copy();
            $finished[$id] = $best['time']->copy()->addMinutes($matchDuration + $playerRest);
            $genderSlots[$id] = ['venue_id' => (int) $best['venue_id'], 'time' => $best['time']->copy(), 'duration' => $matchDuration, 'court_gap' => $matchGap];
            $scheduledPerDraw[$node['draw_id']] = ($scheduledPerDraw[$node['draw_id']] ?? 0) + 1;
            $plan[] = [
                'fixture_id' => $node['fixture']->id, 'fixture_kind' => $node['fixture_kind'] ?? 'individual',
                'fixture_key' => ($node['fixture_kind'] ?? 'individual').':'.$node['fixture']->id, 'draw_id' => $node['draw_id'], 'draw_name' => $node['draw_name'],
                'stage' => $node['stage'], 'round' => $node['round'], 'match' => $node['match'], 'rank' => $node['rank'] ?? null,
                'play_order' => $node['play_order'], 'wave' => $node['wave'],
                'programme_day' => $node['programme_day'] ?? null, 'programme_sequence' => $node['programme_sequence'] ?? null,
                'dependencies' => $node['dependencies'],
                'programme_start_dependencies' => $node['programme_start_dependencies'] ?? [],
                'day_finish_dependencies' => $node['day_finish_dependencies'] ?? [],
                'not_before' => $node['not_before']->format('Y-m-d H:i:s'),
                'scheduled_at' => $best['time']->format('Y-m-d H:i:s'), 'venue_id' => $best['venue_id'],
                'venue_name' => $venues[$best['venue_id']]->name, 'court' => $best['court'],
                'venue_group' => $best['venue_group'],
                'duration' => $matchDuration, 'court_gap' => $matchGap, 'timed' => $node['timed'], 'timed_duration' => $node['duration'], 'timed_gap' => $node['court_gap'], 'participants' => $node['participant_names'],
                'tie_nr' => ($node['fixture_kind'] ?? '') === 'team' ? ($node['fixture']->teamTie?->tie_nr ?: $node['fixture']->tie_nr) : null,
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
                    if (isset($node['programme_phase']) && ($earlier['programme_phase'] ?? null) !== $node['programme_phase']) continue;
                    $earlierPhase = $this->genderPhase($earlier, $genderWaves);
                    if ($earlierPhase >= $phase) continue;
                    $phaseStarts[$earlierPhase] = isset($phaseStarts[$earlierPhase])
                        ? $phaseStarts[$earlierPhase]->min($earlierSlot['time'])->copy() : $earlierSlot['time']->copy();
                }
            }
            foreach ($genderSlots as $earlierId => $earlierSlot) {
                $earlier = $nodes[$earlierId];
                if (! $earlier['gender'] || $earlier['automatic'] || $earlierSlot['venue_id'] !== $slot['venue_id']) continue;
                    if (isset($node['programme_phase']) && ($earlier['programme_phase'] ?? null) !== $node['programme_phase']) continue;
                    $earlierPhase = $this->genderPhase($earlier, $genderWaves);
                $required = $genderRelease === 'court_ready'
                    ? ($phaseStarts[$earlierPhase] ?? $earlierSlot['time'])->copy()->addMinutes($waveMinutes)
                    : $earlierSlot['time']->copy()->addMinutes(max($waveMinutes, $earlierSlot['duration'] + ($earlierSlot['court_gap'] ?? $courtGap)));
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
                'stage' => $node['stage'], 'round' => $node['round'], 'match' => $node['match'], 'rank' => $node['rank'] ?? null,
                'play_order' => $node['play_order'], 'wave' => $node['wave'],
                'dependencies' => $node['dependencies'],
                'programme_start_dependencies' => $node['programme_start_dependencies'] ?? [],
                'day_finish_dependencies' => $node['day_finish_dependencies'] ?? [],
                'not_before' => $node['not_before']->format('Y-m-d H:i:s'),
                'scheduled_at' => null, 'venue_id' => null, 'venue_name' => null, 'court' => null,
                'programme_day' => $node['programme_day'] ?? null, 'programme_sequence' => $node['programme_sequence'] ?? null,
                'duration' => $node['duration'], 'court_gap' => $node['court_gap'], 'timed' => $node['timed'], 'timed_duration' => $node['duration'], 'timed_gap' => $node['court_gap'], 'participants' => $node['participant_names'],
                'lineup' => ($node['fixture_kind'] ?? '') === 'team' ? $node['fixture']->lineup_display : [],
                'participant_ids' => $node['participants'], 'venue_courts' => $node['venue_courts'],
                'reason' => $reason,
            ];
        }

        foreach ($pending as $id => $node) {
            if (isset($node['preview_move'])) throw new \InvalidArgumentException('Match '.$node['match'].' could not be moved: '.($blocked[$id] ?? 'Choose a valid slot after its qualifying matches.'));
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
                    'round' => $sameEvent ? max(1, (int) ($node['round'] ?? $fixture?->round ?? $slot->round_number)) : null,
                    'match' => $sameEvent ? $fixture?->match_nr : null, 'scheduled_at' => $startsAt->format('Y-m-d H:i:s'),
                    'ends_at' => $startsAt->copy()->addMinutes($slot->occupiedMinutes($duration))->format('Y-m-d H:i:s'),
                    'venue_id' => (int) $slot->venue_id, 'court' => (string) $slot->court,
                    'duration' => (int) ($slot->duration_minutes ?: ($node['duration'] ?? $duration)), 'court_gap' => (int) ($slot->gap_minutes ?? 0),
                    'timed' => $node['timed'] ?? false, 'timed_duration' => $node['duration'] ?? null, 'timed_gap' => $node['court_gap'] ?? null,
                    'participants' => $participants,
                    'wave' => $node['wave'] ?? null, 'dependencies' => $node['dependencies'] ?? [],
                    'programme_start_dependencies' => $node['programme_start_dependencies'] ?? [],
                    'day_finish_dependencies' => $node['day_finish_dependencies'] ?? [],
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
                        'rank' => $sameEvent ? app(TeamFixtureOrder::class)->rank($fixture) : null,
                        'round' => $sameEvent ? max(1, (int) $fixture->round_nr) : null, 'match' => $sameEvent ? ($fixture->match_nr ?: $fixture->rubber_sequence) : null,
                        'scheduled_at' => Carbon::parse($fixture->scheduled_at)->format('Y-m-d H:i:s'),
                        'ends_at' => Carbon::parse($fixture->scheduled_at)->addMinutes((int) ($fixture->duration_min ?: 120) + (int) ($fixture->gap_minutes ?? 0))->format('Y-m-d H:i:s'),
                        'venue_id' => (int) $fixture->venue_id, 'court' => ScheduleAvailability::courtKey((string) $fixture->court_label),
                        'duration' => (int) ($fixture->duration_min ?: 120), 'court_gap' => (int) ($fixture->gap_minutes ?? 0),
                        'participants' => $sameEvent ? ($node['participant_names'] ?? [$fixture->teamTie?->home_side_name ?: 'Home team', $fixture->teamTie?->away_side_name ?: 'Away team']) : ['Existing booking'],
                        'lineup' => $sameEvent ? $fixture->lineup_display : [],
                        'participant_ids' => $node['participants'] ?? [], 'venue_courts' => $node['venue_courts'] ?? [],
                        'wave' => $node['wave'] ?? null, 'dependencies' => $node['dependencies'] ?? [],
                        'programme_start_dependencies' => $node['programme_start_dependencies'] ?? [],
                        'day_finish_dependencies' => $node['day_finish_dependencies'] ?? [],
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
            'match_timing' => collect($nodes)->map(fn ($node) => ['duration' => $node['duration'], 'court_gap' => $node['court_gap']])->all(),
            'preview_moves' => array_values($previewMoves),
            'preview_frozen_keys' => $frozenKeys,
            'day_batch' => $dayBatch, 'draw_order_mode' => $drawOrderMode, 'draw_order' => $drawOrder,
            'selected_fixture_keys' => $selectedFixtureKeys, 'reschedule_existing' => (bool) ($options['reschedule_existing'] ?? false),
            'limit_daily_matches' => $limitDailyMatches,
            'start' => $start->format('Y-m-d H:i:s'), 'end' => $end?->format('Y-m-d H:i:s'), 'round_progression' => $roundProgression,
            'draw_ids' => $draws->pluck('id')->sort()->values()->all(), 'venue_ids' => $venueIds->sort()->values()->all(),
            'replan_venue_ids' => collect($replanVenues)->sort()->values()->all(),
            'allow_partial' => (bool) ($options['allow_partial'] ?? false),
            'round_venue_setups' => $roundSetups->values()->all(),
            'rank_venue_preferences' => $rankRules, 'cross_band_policy' => $crossBandPolicy,
            'gender_waves' => $genderWaves,
            'gender_wave_release' => $genderRelease,
            'draw_rounds' => $drawRounds,
            'programme' => $programme, 'day_scope' => $dayScope, 'full_day_scope' => $fullDay, 'day_court_labels' => ($dayScope || $fullDay) ? $courtLabels : null,
            'day_venue_groups' => $dayVenueGroups,
            'full_day_venue_windows' => $fullDayVenueWindows, 'full_day_court_starts' => $fullDayCourtStarts,
            'full_day_tie_destinations' => $tieDestinations,
            'full_day_destination_pools' => $destinationPools,
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
            'automatic_fixture_ids' => ($dayScope || $fullDay) ? [] : collect($nodes)->filter(fn ($node) => $node['selected_round'] && $node['automatic'] && ! $node['played'])->keys()->values()->all(),
            'revision' => $this->revision($event, $input + ['availability_revision' => $availabilityRevision, 'rank_revision' => $rankChoices]), 'input' => $input,
        ];
    }

    public function apply(Event $event, array $options, string $expectedRevision): array
    {
        return DB::transaction(function () use ($event, $options, $expectedRevision) {
            if (! empty($options['full_day_scope'])) $options['allow_partial'] = false;
            $applyVenueIds = array_values(array_unique(array_map('intval', $options['apply_venue_ids'] ?? [])));
            if (! empty($options['full_day_scope']) && $applyVenueIds) throw new \InvalidArgumentException('Save the full day preview together.');
            if (! empty($options['programme'])) {
                if ($applyVenueIds) throw new \InvalidArgumentException('Save the complete event programme together after every match fits.');
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
                ! empty($options['programme']) ? Carbon::parse($preview['input']['end'])->addMinutes(max((int) $preview['input']['playerRest'], (int) $preview['input']['courtGap'], (int) $matches->max('court_gap'))) : null);
            $calendarDraws = Draw::whereIn('id', $matches->pluck('draw_id'))->with('flexibleMonrad')->get()->keyBy('id');
            $dailyLimit = ! empty($preview['input']['limit_daily_matches']) ? DailyMatchLimit::load($event, $matches->pluck('fixture_key')->all()) : null;
            foreach ($matches->sortBy('scheduled_at') as $row) {
                $at = Carbon::parse($row['scheduled_at']);
                if ($dailyLimit && ($error = $dailyLimit->error($row['fixture_key'], $at->toDateString()))) throw new \InvalidArgumentException($error);
                $dailyLimit?->reserve($row['fixture_key'], $at->toDateString());
                $courtMinutes = (int) $row['duration'] + (int) ($row['court_gap'] ?? $preview['input']['courtGap'] ?? 0);
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
                    if ($error = app(UnifiedTeamScheduleService::class)->successorError($fixture->draw, Carbon::parse($row['scheduled_at']), (int) $row['duration'] + (int) ($preview['input']['playerRest'] ?? 60))) throw new \InvalidArgumentException($error);
                    $auditAssignments[$row['draw_id']][] = ['fixture_kind' => 'team', 'fixture_id' => $fixture->id,
                        'before' => $fixture->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes']),
                        'after' => array_intersect_key($row, array_flip(['scheduled_at', 'venue_id', 'venue_group', 'court', 'duration', 'court_gap']))];
                    $fixture->forceFill(['scheduled_at' => $row['scheduled_at'], 'venue_id' => $row['venue_id'],
                        'court_label' => $row['court'], 'duration_min' => $row['duration'],
                        'gap_minutes' => (int) ($row['court_gap'] ?? $preview['input']['courtGap'] ?? 0), 'scheduled' => 1, 'clash_flag' => false])->save();
                    continue;
                }
                $fixture = Fixture::whereKey($row['fixture_id'])->where('draw_id', $row['draw_id'])->firstOrFail();
                $auditAssignments[$row['draw_id']][] = ['fixture_kind' => 'individual', 'fixture_id' => $fixture->id,
                    'before' => $fixture->orderOfPlay?->only(['time', 'venue_id', 'court', 'duration_minutes', 'gap_minutes']),
                    'after' => array_intersect_key($row, array_flip(['scheduled_at', 'venue_id', 'venue_group', 'court', 'duration', 'court_gap']))];
                OrderOfPlay::updateOrCreate(['fixture_id' => $fixture->id], [
                    'draw_id' => $row['draw_id'], 'venue_id' => $row['venue_id'], 'court' => $row['court'],
                    'time' => $row['scheduled_at'], 'duration_minutes' => $row['duration'],
                    'gap_minutes' => (int) ($row['court_gap'] ?? $preview['input']['courtGap'] ?? 0), 'round_number' => $row['round'],
                ]);
                $fixture->update(['scheduled' => 1]);
            }
            foreach ($matches->groupBy('draw_id') as $drawId => $rows) {
                DrawAuditLog::record((int) $drawId, 'event_venue_schedule_applied', null, [
                    'event_id' => $event->id, 'matches' => $rows->count(), 'revision' => $expectedRevision,
                    'venue_ids' => $rows->pluck('venue_id')->unique()->values()->all(),
                    'partial' => (bool) $applyVenueIds, 'day_scope' => $options['day_scope'] ?? $options['full_day_scope'] ?? null, 'full_day_scope' => $options['full_day_scope'] ?? null, 'assignments' => $auditAssignments[$drawId] ?? [],
                    'full_day_options' => ! empty($options['full_day_scope']) ? $preview['input'] : null,
                    'day_options' => ! empty($options['day_scope']) ? array_intersect_key($preview['input'], array_flip([
                        'venue_ids', 'day_court_labels', 'rank_venue_preferences', 'cross_band_policy',
                    ])) : null,

                ]);
            }
            if (! empty($options['programme'])) {
                $draft = DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->lockForUpdate()->first();
                $stored = json_decode((string) ($draft->options ?? ''), true) ?: [];
                $legacyRound = $stored['programme']['rounds'][0] ?? null;
                $legacyDraw = $legacyRound ? Draw::where('event_id', $event->id)->find($legacyRound['draw_id'] ?? $legacyRound['drawId'] ?? 0) : null;
                $legacyAge = $legacyDraw ? app(ScheduleProgramme::class)->key($legacyDraw) : null;
                if ($legacyAge !== null && ! isset($stored['programme_settings'][(string) $legacyAge])) {
                    $stored['programme_settings'][(string) $legacyAge] = array_intersect_key($stored, array_flip([
                        'duration', 'player_rest', 'court_gap', 'gender_waves', 'gender_wave_release', 'reschedule_existing',
                    ])) + ['programme' => $stored['programme']];
                }
                $programme = $preview['input']['programme'];
                $firstDraw = Draw::where('event_id', $event->id)->find($programme['rounds'][0]['drawId']);
                $age = $firstDraw->isTeamDraw() ? app(ScheduleProgramme::class)->key($firstDraw)
                    : 'individual:'.implode(',', collect($programme['rounds'])->pluck('drawId')->unique()->sort()->values()->all());
                $stored['programme'] = $programme;
                $stored['programme_settings'][(string) $age] = array_intersect_key($options, array_flip([
                    'duration', 'player_rest', 'court_gap', 'gender_waves', 'gender_wave_release', 'reschedule_existing',
                ])) + ['programme' => $programme];
                if (! $firstDraw->isTeamDraw()) {
                    foreach (collect($programme['rounds'])->pluck('drawId')->unique() as $drawId) {
                        $stored['individual_programme_by_draw'][(string) $drawId] = $age;
                    }
                }
                DB::table('event_venue_schedule_drafts')->updateOrInsert(['event_id' => $event->id], [
                    'options' => json_encode($stored, JSON_THROW_ON_ERROR), 'updated_by' => auth()->id(),
                    'created_at' => $draft->created_at ?? now(), 'updated_at' => now(),
                ]);
            }
            $draft = DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->lockForUpdate()->first();
            $stored = json_decode((string) ($draft->options ?? ''), true) ?: [];
            if (array_key_exists('limit_daily_matches', $options) || array_key_exists('limit_daily_matches', $stored) || $preview['input']['limit_daily_matches']) {
                $stored['limit_daily_matches'] = (bool) $preview['input']['limit_daily_matches'];
            }
            if (! empty($preview['input']['day_batch'])) {
                $stored['day_batch_settings'][$preview['input']['full_day_scope']] = array_replace($options, [
                    'gender_waves' => $preview['input']['gender_waves'], 'draw_order' => $preview['input']['draw_order'],
                    'draw_order_mode' => $preview['input']['draw_order_mode'], 'day_court_labels' => $preview['input']['day_court_labels'],
                ]);
                unset($stored['day_batch_settings'][$preview['input']['full_day_scope']]['preview_moves'], $stored['day_batch_settings'][$preview['input']['full_day_scope']]['preview_frozen_keys']);
                foreach ($matches as $row) {
                    $courts = $row['venue_courts'];
                    $banded = collect($preview['input']['rank_venue_preferences'])->contains(fn ($rule) => in_array((int) $row['draw_id'], array_map('intval', $rule['draw_ids']), true));
                    if ($row['fixture_kind'] === 'team' && ! $banded) $courts = [$row['venue_id'] => $row['venue_group']
                        ? $preview['input']['day_venue_groups'][$row['venue_id']][$row['venue_group']] : ($courts[$row['venue_id']] ?? [])];
                    $stored['day_batch_allocations'][$row['fixture_key']] = [
                        'draw_id' => $row['draw_id'], 'date' => $preview['input']['full_day_scope'],
                        'venue_ids' => array_map('intval', array_keys($courts)),
                        'court_allocations' => collect($courts)->map(fn ($labels, $id) => ['venue_id' => (int) $id, 'court_labels' => $labels])->values()->all(),
                    ];
                }
            }
            DB::table('event_venue_schedule_drafts')->updateOrInsert(['event_id' => $event->id], [
                'options' => json_encode($stored, JSON_THROW_ON_ERROR), 'updated_by' => auth()->id(),
                'created_at' => $draft->created_at ?? now(), 'updated_at' => now(),
            ]);
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
        return $this->unapplyScope($event, $drawId, $venueId, $fixtureId);
    }

    public function clearDay(Event $event, array $scope, string $revision): array
    {
        return DB::transaction(function () use ($event, $scope, $revision) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $publication = app(SchedulePublicationService::class);
            if (! hash_equals($publication->revision($event), $revision)) {
                throw new \InvalidArgumentException('The schedule changed. Refresh the calendar before clearing this day.');
            }
            $drawIds = $event->draws()->when(! empty($scope['draw_id']), fn ($q) => $q->whereKey($scope['draw_id']))
                ->orderBy('id')->lockForUpdate()->get();
            $rows = $publication->workingRows($event)->filter(fn ($row) => substr($row['scheduled_at'], 0, 10) === $scope['date']
                && (empty($scope['draw_id']) || (int) $row['draw_id'] === (int) $scope['draw_id'])
                && (empty($scope['venue_id']) || (int) $row['venue_id'] === (int) $scope['venue_id']));
            if ($drawIds->whereIn('id', $rows->pluck('draw_id'))->contains(fn ($draw) => $draw->locked)) {
                throw new \InvalidArgumentException('Unlock the selected draws before clearing scheduled times.');
            }
            $result = $this->unapplyCorrectionSelection($event,
                $rows->where('fixture_kind', 'individual')->pluck('fixture_id')->all(),
                $rows->where('fixture_kind', 'team')->pluck('fixture_id')->all());
            foreach ($rows->groupBy('draw_id') as $drawId => $bookings) {
                DrawAuditLog::record((int) $drawId, 'schedule_day_cleared', null, [
                    'event_id' => $event->id, 'scope' => $scope, 'before' => $bookings->map(fn ($row) => array_intersect_key($row, array_flip(['fixture_kind', 'fixture_id', 'scheduled_at', 'venue_id', 'court', 'duration'])))->values()->all(), 'after' => [],
                ]);
            }
            return $result;
        });
    }

    public function courtCorrectionClosure(Event $event, array $individualIds, array $teamIds): array
    {
        $individualIds = array_values(array_unique($individualIds)); $teamIds = array_values(array_unique($teamIds));
        if (Fixture::whereIn('id', $individualIds)->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))->count() !== count($individualIds)
            || TeamFixture::whereIn('id', $teamIds)->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))->count() !== count($teamIds)) {
            throw new \InvalidArgumentException('Correction matches must belong to this event.');
        }
        $drawIds = Fixture::whereIn('id', $individualIds)->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))->pluck('draw_id')
            ->merge(TeamFixture::whereIn('id', $teamIds)->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))->pluck('draw_id'))->unique();
        $saved = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        $progression = ($saved['round_progression'] ?? 'team_ready') === 'all_round' ? 'all_round' : 'team_ready';
        $graph = [];
        foreach (Draw::with(['drawFixtures.orderOfPlay', 'drawFixtures.fixtureResults', 'flexibleMonrad', 'groups'])->whereIn('id', $drawIds)->orderBy('id')->get() as $draw) {
            foreach ($this->nodesForDraw($draw, Carbon::now(), 90) as $id => $node) {
                $graph['individual:'.$id] = array_map(fn ($dependency) => 'individual:'.$dependency, $node['dependencies']);
            }
            foreach (app(UnifiedTeamScheduleService::class)->nodes($draw, $progression) as $key => $node) $graph[$key] = $node['dependencies'];
        }
        $selected = array_fill_keys(array_merge(array_map(fn ($id) => 'individual:'.$id, $individualIds), array_map(fn ($id) => 'team:'.$id, $teamIds)), true);
        // Every pass adds a node; the visited set bounds cycles by the graph size.
        do {
            $changed = false;
            foreach ($graph as $key => $dependencies) {
                if (isset($selected[$key]) || ! array_intersect_key($selected, array_fill_keys($dependencies, true))) continue;
                $selected[$key] = true; $changed = true;
            }
        } while ($changed);
        ksort($selected);
        return ['keys' => array_keys($selected), 'graph' => array_intersect_key($graph, $selected)];
    }

    public function unapplyCorrectionSelection(Event $event, array $individualIds, array $teamIds): array
    {
        return DB::transaction(function () use ($event, $individualIds, $teamIds) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $fixtures = Fixture::with('fixtureResults')->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))
                ->whereIn('id', $individualIds)->orderBy('id')->lockForUpdate()->get();
            if ($fixtures->count() !== count(array_unique($individualIds))) throw new \InvalidArgumentException('A correction match does not belong to this event.');
            if ($fixtures->contains(fn ($fixture) => $fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0)) throw new \InvalidArgumentException('Played dependent matches cannot be returned to planning.');
            if ($error = $this->removalError($event, $individualIds, $teamIds)) throw new \InvalidArgumentException($error);
            $team = $teamIds ? app(UnifiedTeamScheduleService::class)->unapplyCorrectionSelection($event, $teamIds) : ['count' => 0];
            $bookings = OrderOfPlay::whereIn('fixture_id', $individualIds)->whereNotNull('time')->orderBy('id')->lockForUpdate()->get();
            OrderOfPlay::whereIn('id', $bookings->pluck('id'))->delete();
            $scheduledIds = $bookings->pluck('fixture_id')->unique();
            Fixture::whereIn('id', $scheduledIds)->update(['scheduled' => 0]);
            foreach ($fixtures->whereIn('id', $scheduledIds)->groupBy('draw_id') as $drawId => $rows) {
                DrawAuditLog::record((int) $drawId, 'event_venue_schedule_unapplied', null, [
                    'event_id' => $event->id, 'matches' => $rows->count(), 'scope' => 'court_correction',
                    'before' => $bookings->whereIn('fixture_id', $rows->pluck('id'))->map(fn ($row) => $row->only(['fixture_id', 'venue_id', 'court', 'time']))->values()->all(),
                ]);
            }
            return ['count' => $scheduledIds->count() + $team['count']];
        });
    }

    public function unapplyForCourtCorrection(Event $event, int $venueId): array
    {
        return $this->unapplyScope($event, null, $venueId, null, true);
    }

    private function unapplyScope(Event $event, ?int $drawId, ?int $venueId, ?int $fixtureId, bool $courtCorrection = false): array
    {
        if ($fixtureId !== null) return $this->unapplyIndividual($event, $drawId, $venueId, $fixtureId);
        if (collect([$drawId, $venueId])->filter(fn ($id) => $id !== null)->count() !== 1) {
            throw new \InvalidArgumentException('Choose one draw or one venue to return to planning.');
        }
        return DB::transaction(function () use ($event, $drawId, $venueId, $courtCorrection) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $hasTeam = TeamFixture::whereHas('draw', fn ($q) => $q->where('event_id', $event->id))
                ->whereNotNull('scheduled_at')->when($drawId !== null, fn ($q) => $q->where('draw_id', $drawId))
                ->when($venueId !== null, fn ($q) => $q->where('venue_id', $venueId))->exists();
            $hasIndividual = Fixture::whereHas('draw', fn ($q) => $q->where('event_id', $event->id))
                ->when($drawId !== null, fn ($q) => $q->where('draw_id', $drawId))
                ->whereHas('orderOfPlay', fn ($q) => $q->whereNotNull('time')
                    ->when($venueId !== null, fn ($q) => $q->where('venue_id', $venueId)))->exists();
            if (! $hasTeam) return $this->unapplyIndividual($event, $drawId, $venueId, null, $courtCorrection);
            $team = $courtCorrection
                ? app(UnifiedTeamScheduleService::class)->unapplyForCourtCorrection($event, $venueId)
                : app(UnifiedTeamScheduleService::class)->unapply($event, $drawId, $venueId);
            $individual = $hasIndividual ? $this->unapplyIndividual($event, $drawId, $venueId, null, $courtCorrection) : ['count' => 0];
            $count = $team['count'] + $individual['count'];
            return ['count' => $count, 'message' => $count.' matches returned to planning. Fixtures and results were preserved.'];
        });
    }

    private function unapplyIndividual(Event $event, ?int $drawId = null, ?int $venueId = null, ?int $fixtureId = null, bool $courtCorrection = false): array
    {
        if (collect([$drawId, $venueId, $fixtureId])->filter(fn ($id) => $id !== null)->count() !== 1) {
            throw new \InvalidArgumentException('Choose one match, one draw, or one venue to return to planning.');
        }
        if ($fixtureId !== null) return $this->unapplyFixture($event, $fixtureId);

        return DB::transaction(function () use ($event, $drawId, $venueId, $courtCorrection) {
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
            if (! $courtCorrection && $fixtures->contains(fn (Fixture $fixture) => $fixture->draw?->locked)) {
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

            $fixtures->whereIn('id', $bookingFixtureIds)->groupBy('draw_id')->each(function ($drawFixtures, $affectedDrawId) use ($event, $drawId, $venueId, $courtCorrection) {
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

    private function nodesForDraw(Draw $draw, Carbon $start, int $waveMinutes, bool $programme = false): array
    {
        if (! $draw->isTeamDraw() && $draw->usesFlexibleMonrad()) {
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
                $dependencies = array_map(fn ($dependency) => $keyToId[$dependency], \App\Services\Draw\FlexibleMonradCompiler::dependencies($match));
                $nodes[$match['id']] = $this->node($draw, $fixture, $dependencies, $participants[$key] ?? [], (bool) $match['automatic'], (bool) $match['sets'] || $fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0,
                    $sourceLabels);
            }
            // Placement sections restart fixture round numbers; programme rounds follow graph depth.
            foreach (array_keys($nodes) as $id) $nodes[$id]['venue_round'] = $this->wave($id, $nodes);
            if ($programme) foreach (array_keys($nodes) as $id) $nodes[$id]['round'] = $nodes[$id]['venue_round'];
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
        if ($draw->settings?->workflow === 'round_robin_playoffs') {
            foreach (array_keys($nodes) as $id) $nodes[$id]['venue_round'] = $this->wave($id, $nodes);
            if ($programme) foreach (array_keys($nodes) as $id) $nodes[$id]['round'] = $nodes[$id]['venue_round'];
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
            ? 'This match uses its configured roster rank venue assignment. That assignment may differ between disciplines or partners.'
            : (($node['rank_preference']['warning'] ?? null) ? $node['rank_preference']['warning'].' ' : '').'No roster rank venue preference applies to this match. The planner selected a permitted venue using court and player availability and its scheduling priorities.';
        $from = $change['from_booking'] ?? ['fixture' => null];
        $from['venue_name'] = $venues[$change['from_venue_id']]->name ?? Venue::find($change['from_venue_id'])?->name ?? 'Another venue';
        return ['message' => $this->venueWarning($change, $venues), 'reason' => $reason,
            'reason_code' => $preferred ? 'rank_preference' : 'unrestricted',
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

    public function dayCourtLabels(Event $event, Draw $draw): array
    {
        if ((int) $draw->event_id !== (int) $event->id) throw new \InvalidArgumentException('Choose a draw in this event.');
        $venues = $event->venues()->get()->concat($draw->venues()->get())->unique('id')->keyBy('id');
        return $this->courtLabels($event, collect([$draw->loadMissing('venues')]), $venues);
    }

    public function fullDayCourtLabels(Event $event): array
    {
        $draws = $event->draws()->with('venues')->get();
        // Match the scheduling page's event-wide inventory, including venues
        // attached through another draw in this event.
        $venues = $event->venues()->get()->concat($draws->flatMap(fn ($draw) => $draw->venues))->unique('id')->keyBy('id');
        return $this->courtLabels($event, $draws, $venues);
    }

    public function fullDayTies(Event $event): array
    {
        $draws = $event->draws()->get()->filter(fn ($draw) => $draw->isTeamDraw())->keyBy('id');
        $fixtures = TeamFixture::without(['teamResults', 'fixturePlayers', 'draw'])
            ->with(['teamTie.homeTeam', 'teamTie.awayTeam', 'fixtureResults', 'region1Name', 'region2Name'])
            ->whereIn('draw_id', $draws->keys())->orderBy('draw_id')->orderBy('round_nr')->orderBy('tie_nr')->orderBy('id')->get();
        $adapter = app(UnifiedTeamScheduleService::class);
        $ties = [];
        foreach ($fixtures as $fixture) {
            if ($adapter->protected($fixture)) continue;
            $node = ['fixture_kind'=>'team', 'fixture'=>$fixture, 'draw_id'=>(int) $fixture->draw_id, 'round'=>max(1, (int) $fixture->round_nr)];
            $key = $this->tieAllocationKey($node);
            $date = $fixture->scheduled_at?->toDateString() ?? 'unscheduled';
            if (isset($ties[$key])) {
                $ties[$key]['dates'] = array_values(array_unique([...$ties[$key]['dates'], $date]));
                continue;
            }
            $ties[$key] = ['key'=>$key, 'draw_id'=>(int) $fixture->draw_id, 'draw_name'=>$draws[$fixture->draw_id]->drawName,
                'playoff_group'=>($draws[$fixture->draw_id]->team_draw_selection['competition']['phase'] ?? null) === 'playoff'
                    && $fixture->teamTie && in_array((int) $fixture->teamTie->tie_nr, [1, 2], true)
                    ? ($fixture->teamTie->tie_nr === 1 ? '1st vs 2nd' : '3rd vs 4th') : null,
                'round'=>$node['round'], 'tie_nr'=>$fixture->teamTie?->tie_nr ?: $fixture->tie_nr, 'dates'=>[$date],
                'matchup'=>($fixture->teamTie?->home_side_name ?: $fixture->region1Name?->name ?: 'Home team').' vs '.($fixture->teamTie?->away_side_name ?: $fixture->region2Name?->name ?: 'Away team')];
        }
        return array_values($ties);
    }

    public function fullDayDestinationPools(Event $event): array
    {
        return $this->destinationPoolDefinitions($event->draws()->with('categoryEvent.category')->get());
    }

    private function destinationPoolDefinitions(Collection $draws): array
    {
        $pools = [];
        $programme = app(ScheduleProgramme::class);
        foreach ($draws as $draw) {
            $age = $programme->age($draw);
            $key = $age === null ? 'draw:'.$draw->id : 'age:'.$age;
            $pools[$key] ??= ['key'=>$key, 'label'=>$age === null ? $draw->drawName : 'U/'.$age, 'draw_ids'=>[], 'draw_names'=>[]];
            $pools[$key]['draw_ids'][] = (int) $draw->id;
            $pools[$key]['draw_names'][] = $draw->drawName;
        }
        return array_values($pools);
    }

    private function courtLabels(Event $event, Collection $draws, Collection $venues): array
    {
        $definitions = DB::table('event_venue_courts')->where('event_id', $event->id)->whereIn('venue_id', $venues->keys())->orderBy('id')->get()->groupBy('venue_id');
        $configured = $definitions->map(fn ($rows) => $rows->where('active', true));
        $eventCounts = DB::table('event_venues')->where('event_id', $event->id)->whereIn('venue_id', $venues->keys())->pluck('num_courts', 'venue_id');
        $labels = [];
        foreach ($venues as $venue) {
            $venueLabels = ($configured[$venue->id] ?? collect())->pluck('label')->map(fn ($label) => (string) $label)->all();
            $pivotMaximum = $draws->flatMap(fn ($draw) => $draw->venues->where('id', $venue->id))
                ->max(fn ($assigned) => (int) ($assigned->pivot->num_courts ?? 0));
            $count = max((int) $pivotMaximum, (int) ($eventCounts[$venue->id] ?? 0));
            $labels[$venue->id] = $definitions->has($venue->id) ? $venueLabels : ($count > 0 ? array_map('strval', range(1, $count)) : []);
        }
        return $labels;
    }

    private function isEarlier(array $candidate, array $best, array $nodes): bool
    {
        if (! empty($candidate['day_batch'])) {
            return ([$candidate['time']->timestamp, $candidate['rank_penalty'] ?? 0, $candidate['fairness'], $candidate['draw_priority'], $candidate['tie_priority'] ?? 1, count($candidate['venue_changes'] ?? []), $nodes[$candidate['id']]['play_order'], $candidate['id']]
                <=> [$best['time']->timestamp, $best['rank_penalty'] ?? 0, $best['fairness'], $best['draw_priority'], $best['tie_priority'] ?? 1, count($best['venue_changes'] ?? []), $nodes[$best['id']]['play_order'], $best['id']]) < 0;
        }
        return ([$candidate['tie_priority'] ?? 1, $candidate['rank_penalty'] ?? 0, count($candidate['venue_changes'] ?? []), $candidate['time']->timestamp, $candidate['fairness'], $nodes[$candidate['id']]['wave'],
            $nodes[$candidate['id']]['play_order'], $candidate['id']]
            <=> [$best['tie_priority'] ?? 1, $best['rank_penalty'] ?? 0, count($best['venue_changes'] ?? []), $best['time']->timestamp, $best['fairness'], $nodes[$best['id']]['wave'],
                $nodes[$best['id']]['play_order'], $best['id']]) < 0;
    }

    public function programmeFixtureRounds(Draw $draw): array
    {
        $draw->loadMissing('drawFixtures.fixtureResults');
        return collect($this->nodesForDraw($draw, Carbon::now(), 90, true))->mapWithKeys(fn ($node, $id) => [$id => $node['round']])->all();
    }

    public function programmeRoundDetails(Draw $draw): array
    {
        if (! $draw->usesFlexibleMonrad() || ! $draw->flexibleMonrad?->graph) return [];
        $rounds = $this->programmeFixtureRounds($draw);
        $matches = (array) app(FlexibleMonradService::class)->state($draw)['matches'];
        $graph = $draw->flexibleMonrad->graph;
        $finishes = [];
        foreach ($graph['base_positions'] ?? $graph['positions'] ?? [] as $position => $source) {
            if (isset($source['match'])) $finishes[$source['match']][] = (int) $position;
        }
        $details = [];
        foreach ($matches as $key => $match) {
            if ($match['automatic'] || ! isset($rounds[$match['id']])) continue;
            $section = (string) $match['section'];
            if (isset($match['challenge_positions'])) {
                $label = 'Conditional play-off · Positions '.implode('–', $match['challenge_positions']);
            } elseif ($section === 'Main draw') {
                $label = 'Main draw · '.$match['label'];
            } elseif (count($finishes[$key] ?? []) === 2) {
                $positions = $finishes[$key]; sort($positions);
                $label = 'Positions '.implode('–', $positions).' final';
            } elseif (str_contains($section, 'Feed-in')) {
                $label = $match['label'].' · '.str_replace(' · Feed-in', '', $section);
            } else {
                $remaining = null;
                if (preg_match('/^Positions (\d+)–(\d+)$/u', $section, $band)) {
                    $size = (int) $band[2] - (int) $band[1] + 1;
                    if ($size > 0 && ($size & ($size - 1)) === 0) $remaining = $size / (2 ** max(0, (int) $match['round'] - 1));
                }
                $label = $section.' '.match ($remaining) {
                    4, 4.0 => 'semifinals', 8, 8.0 => 'quarterfinals', default => 'qualifying matches',
                };
            }
            $round = $rounds[$match['id']];
            $details[$round][$label] ??= ['label' => $label, 'count' => 0, 'matches' => []];
            $details[$round][$label]['count']++;
            $details[$round][$label]['matches'][] = ['fixture_id' => (int) $match['id'], 'number' => (int) $match['number']];
        }
        ksort($details);
        return array_map(fn ($groups) => array_values($groups), $details);
    }

    public function dayRoundDetails(Draw $draw): array
    {
        $draw->loadMissing('drawFixtures.fixtureResults');
        $nodes = $draw->isTeamDraw() ? app(UnifiedTeamScheduleService::class)->nodes($draw, 'team_ready') : $this->nodesForDraw($draw, Carbon::now(), 90, true);
        $byKey = [];
        foreach ($nodes as $node) {
            if ($node['automatic']) continue;
            $fixture = $node['fixture']; $slot = $this->nodeSlot($node);
            $key = ($node['fixture_kind'] ?? 'individual').':'.$fixture->id;
            $byKey[$key] = ['fixture_id' => (int) $fixture->id, 'number' => (int) $node['match'], 'key' => $key,
                'scheduled_at' => $slot?->time ? Carbon::parse($slot->time)->format('Y-m-d H:i:s') : null, 'played' => (bool) $node['played']];
        }
        if (! $draw->isTeamDraw() && $draw->usesFlexibleMonrad()) {
            $groups = $this->programmeRoundDetails($draw);
            foreach ($groups as &$round) foreach ($round as &$group) $group['matches'] = array_values(array_filter(array_map(fn ($match) => $byKey['individual:'.$match['fixture_id']] ?? null, $group['matches'])));
            unset($round, $group);
            return $groups;
        }
        $groups = [];
        foreach ($nodes as $node) {
            $key = ($node['fixture_kind'] ?? 'individual').':'.$node['fixture']->id;
            if (! isset($byKey[$key])) continue;
            $label = $draw->isTeamDraw() ? ($node['fixture']->rubber_name ?: $node['fixture']->rubber_code ?: 'Team matches')
                : ($draw->settings?->workflow === 'round_robin_playoffs' ? ($node['stage'] === 'RR' ? 'Round robin' : $node['stage'].' playoffs') : 'Draw matches');
            $round = $node['round'];
            $groups[$round][$label] ??= ['label' => $label, 'count' => 0, 'matches' => []];
            $groups[$round][$label]['count']++; $groups[$round][$label]['matches'][] = $byKey[$key];
        }
        ksort($groups);
        return array_map(fn ($round) => array_values($round), $groups);
    }

    public function availableDrawRounds(Collection $draws, bool $programme = false): array
    {
        $teamIds = $draws->filter(fn ($draw) => $draw->isTeamDraw())->pluck('id');
        $individualIds = $draws->reject(fn ($draw) => $draw->isTeamDraw())->pluck('id');
        $rows = Fixture::whereIn('draw_id', $individualIds)->select('draw_id', 'round')->distinct()->get()
            ->concat(TeamFixture::whereIn('draw_id', $teamIds)->selectRaw('draw_id, round_nr as round')->distinct()->get());
        $available = $rows->groupBy('draw_id')->map(fn ($rounds) => $rounds->pluck('round')->map(fn ($round) => max(1, (int) $round))
            ->unique()->sort()->values()->all())->all();
        foreach ($draws->filter(fn ($draw) => $programme && ($draw->usesFlexibleMonrad() || $draw->settings?->workflow === 'round_robin_playoffs')) as $draw) {
            $draw->loadMissing('drawFixtures.fixtureResults');
            $available[$draw->id] = collect($this->nodesForDraw($draw, Carbon::now(), 90, true))
                ->pluck('round')->unique()->sort()->values()->all();
        }
        return $available;
    }

    public function normalizeDrawRounds(Collection $draws, array $rows, bool $programme = false): array
    {
        $available = $this->availableDrawRounds($draws, $programme);
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

    private function configureDayDrawOrder(array &$nodes, array $drawOrder): void
    {
        $groups = [];
        foreach ($nodes as $id => $node) {
            if (! $node['selected_round'] || $node['automatic']) continue;
            $priority = array_search($node['draw_id'], $drawOrder, true);
            $groups[sprintf('%08d:%04d', $node['round'], $priority)][] = $id;
        }
        ksort($groups);
        $previous = [];
        foreach ($groups as $ids) {
            foreach ($ids as $id) {
                $nodes[$id]['day_finish_dependencies'] = array_values(array_diff($previous, $nodes[$id]['dependencies']));
                $nodes[$id]['dependencies'] = array_values(array_unique(array_merge($nodes[$id]['dependencies'], $previous)));
            }
            $previous = $ids;
        }
    }

    private function dayFinishRelease(int|string $dependency, array $nodes, array $finished, array $started, int $duration): Carbon
    {
        $node = $nodes[$dependency] ?? null;
        $duration = $node['duration'] ?? $duration;
        if (isset($started[$dependency]) && $node && ! $node['fixed'] && ! $node['played']) return $started[$dependency]->copy()->addMinutes($duration);
        if ($node) {
            $slot = $this->nodeSlot($node);
            if ($slot?->time) return Carbon::parse($slot->time)->addMinutes((int) ($slot->duration_minutes ?: $duration));
        }
        if (isset($started[$dependency])) return $started[$dependency]->copy()->addMinutes($duration);
        return $finished[$dependency]->copy();
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

    public function drawGender(Draw $draw): ?string
    {
        $gender = strtolower(trim((string) $draw->gender));
        if ($gender !== '') {
            return match ($gender) {
                'boys', 'boy', 'male', 'men', '1' => 'boys',
                'girls', 'girl', 'female', 'women', '2' => 'girls',
                default => null,
            };
        }
        $name = strtolower((string) ($draw->categoryEvent?->category?->name ?: $draw->drawName));
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
