<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Domain\Draws\Services\ScheduleConflictService;
use App\Models\{Draw, DrawAuditLog, Event, Fixture, OrderOfPlay, TeamFixture, TeamTie, Venue};
use App\Services\EventAnnouncementService;
use App\Services\ScheduleEngine;
use App\Services\Scheduling\EventVenueScheduleService;
use App\Services\Scheduling\MatchTimingService;
use App\Services\Scheduling\RoundRobinPlayoffScheduleService;
use App\Services\Scheduling\UnifiedTeamScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EventVenueScheduleController extends Controller
{
    public function preparePlayoffs(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate(['draw_ids' => ['required', 'array', 'min:1', 'max:200'], 'draw_ids.*' => ['required', 'integer', 'distinct']]);
        $draws = $event->draws()->whereIn('id', $data['draw_ids'])->get();
        abort_unless($draws->count() === count($data['draw_ids']), 422, 'Choose draws belonging to this event.');
        try {
            $count = DB::transaction(fn () => $draws->sum(fn ($draw) => app(RoundRobinController::class)->prepareSchedulePlayoffs($draw)));
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['playoffs' => $exception->getMessage()]);
        }
        return redirect()->route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => $data['draw_ids']])
            ->with('success', $count ? "Prepared {$count} playoff matches for scheduling. Qualifiers remain undecided until group results are complete." : 'Existing playoff matches are ready to select.');
    }

    public function index(Request $request, Event $event, EventAnnouncementService $announcements)
    {
        $this->authorize('event.manage', $event);
        $event->load('draws.venues', 'draws.draw_types', 'draws.settings');
        $eventDraws = $event->draws->sortBy(function ($draw) {
            $typeName = mb_strtolower($draw->draw_types?->drawTypeName ?? $draw->drawName);
            $typeOrder = match (true) {
                str_contains($typeName, 'single') && ! str_contains($typeName, 'reverse') => 0,
                str_contains($typeName, 'single') && str_contains($typeName, 'reverse') => 1,
                str_contains($typeName, 'double') && ! str_contains($typeName, 'mixed') && ! str_contains($typeName, 'reverse') => 2,
                default => 3,
            };

            return [app(\App\Services\Scheduling\ScheduleProgramme::class)->age($draw) ?? PHP_INT_MAX,
                $typeOrder, mb_strtolower($draw->drawName), $draw->id];
        })->values();
        $availableRounds = app(EventVenueScheduleService::class)->availableDrawRounds($eventDraws);
        $programmeRounds = app(EventVenueScheduleService::class)->availableDrawRounds($eventDraws, true);
        $selectionSupplied = $request->has('draw_ids');
        $requestedDrawIds = collect($request->validate([
            'draw_ids' => ['sometimes', 'array', 'max:200'],
            'draw_ids.*' => ['required', 'integer', 'distinct'],
        ])['draw_ids'] ?? [])->map(fn ($id) => (int) $id);
        if ($selectionSupplied && $requestedDrawIds->diff($eventDraws->pluck('id')->map(fn ($id) => (int) $id))->isNotEmpty()) {
            abort(422, 'One or more selected draws do not belong to this event.');
        }
        $eventDraws->loadCount(['drawFixtures as playoff_fixture_count' => fn ($query) => $query->where('stage', '!=', 'RR')]);
        $pendingPlayoffDraws = $eventDraws->filter(fn ($draw) => $draw->settings?->workflow === 'round_robin_playoffs'
            && (int) $draw->playoff_fixture_count === 0 && (! $selectionSupplied || $requestedDrawIds->contains((int) $draw->id)));
        $pendingSecondRoundRobinDraws = $eventDraws->filter(fn ($draw) => $draw->isTwoStageRoundRobin()
            && ! $draw->groups()->where('round_robin_phase', 2)->exists()
            && (! $selectionSupplied || $requestedDrawIds->contains((int) $draw->id)));
        $eventVenues = $event->venues()->get();
        $drawVenues = $eventDraws->flatMap(fn ($draw) => $draw->venues);

        $availableVenues = $eventVenues->concat($drawVenues)
            ->unique('id')->sortBy('name')->values();
        $courtRows = DB::table('event_venue_courts')->where('event_id', $event->id)
            ->whereIn('venue_id', $availableVenues->pluck('id'))->where('active', true)->orderBy('id')->get()->groupBy('venue_id');
        $courtAllocations = DB::table('draw_venue_court_allocations')->whereIn('draw_id', $eventDraws->pluck('id'))
            ->get()->groupBy(fn ($row) => $row->draw_id.'|'.$row->venue_id);
        $scheduledCounts = DB::table('order_of_plays')->join('fixtures', 'fixtures.id', '=', 'order_of_plays.fixture_id')
            ->whereIn('fixtures.draw_id', $eventDraws->pluck('id'))->whereNotNull('order_of_plays.time')
            ->groupBy('fixtures.draw_id')->selectRaw('fixtures.draw_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'fixtures.draw_id');
        $teamScheduledCounts = TeamFixture::whereIn('draw_id', $eventDraws->pluck('id'))
            ->whereNotNull('scheduled_at')->selectRaw('draw_id, COUNT(*) as aggregate')
            ->groupBy('draw_id')->pluck('aggregate', 'draw_id');
        $scheduledCounts = $scheduledCounts->map(fn ($count, $id) => (int) $count + (int) ($teamScheduledCounts[$id] ?? 0));
        foreach ($teamScheduledCounts as $id => $count) {
            if (! $scheduledCounts->has($id)) $scheduledCounts[$id] = (int) $count;
        }

        $draws = $eventDraws->map(function ($draw) use ($courtAllocations, $scheduledCounts, $selectionSupplied, $requestedDrawIds, $availableRounds, $programmeRounds) {
            $allocations = [];
            foreach ($draw->venues as $venue) {
                $allocations[$venue->id] = ($courtAllocations[$draw->id.'|'.$venue->id] ?? collect())
                    ->pluck('court_label')->map(fn ($label) => (string) $label)->all();
            }
            return ['id' => $draw->id, 'name' => $draw->drawName,
                'programme_age' => app(\App\Services\Scheduling\ScheduleProgramme::class)->key($draw),
                'rubber_code' => $draw->team_draw_selection['rubber_code'] ?? null,
                'competition_phase' => $draw->team_draw_selection['competition']['phase'] ?? null,
                'doubles_pair_count' => $this->standardPairCount($draw, 'doubles'),
                'mixed_pair_count' => $this->standardPairCount($draw, 'mixed_doubles'),
                'venues' => $draw->venues->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'court_allocations' => $allocations,
                'applied_match_count' => (int) ($scheduledCounts[$draw->id] ?? 0),
                'locked' => (bool) $draw->locked, 'published' => (bool) $draw->published,
                'is_team' => $draw->isTeamDraw(),
                'schedule_gender' => app(EventVenueScheduleService::class)->drawGender($draw),
                'programme_supported' => app(\App\Services\Scheduling\ScheduleProgramme::class)->supports($draw),
                'rounds' => $availableRounds[$draw->id] ?? [],
                'programme_rounds' => $programmeRounds[$draw->id] ?? [],
                'programme_round_details' => app(EventVenueScheduleService::class)->programmeRoundDetails($draw),
                'day_round_details' => app(EventVenueScheduleService::class)->dayRoundDetails($draw),
                'selected' => ! $selectionSupplied || $requestedDrawIds->contains((int) $draw->id)];
        });
        $venues = $availableVenues->map(function ($venue) use ($drawVenues, $courtRows) {
            $assignedCounts = $drawVenues->where('id', $venue->id)
                ->map(fn ($assigned) => (int) ($assigned->pivot->num_courts ?? 0));
            $count = max(1, (int) ($venue->pivot->num_courts ?? 0), (int) ($assignedCounts->max() ?? 0));
            $courts = ($courtRows[$venue->id] ?? collect())->map(fn ($court) => [
                'label' => (string) $court->label, 'ball_type' => $court->ball_type,
            ])->values();
            if ($courts->isEmpty()) $courts = collect(range(1, $count))->map(fn ($label) => ['label' => (string) $label, 'ball_type' => null]);
            $ballTypes = $courts->map(fn ($court) => $court['ball_type'] ?: 'standard')->unique()->values();
            $numberedLabels = array_map('strval', range(1, $courts->count()));
            return [
                'id' => $venue->id, 'name' => $venue->name,
                'courts' => $courts->count(), 'court_list' => $courts->all(),
                'common_ball_type' => $ballTypes->count() === 1 ? $ballTypes->first() : 'mixed',
                'has_custom_courts' => $courts->pluck('label')->diff($numberedLabels)->isNotEmpty(),
            ];
        });
        $allVenues = Venue::whereNotIn('id', $availableVenues->pluck('id'))->orderBy('name')->get(['id', 'name']);

        $announcementDraft = $announcements->venueAssignmentDraft($event);

        $storedScheduleDraft = json_decode((string) DB::table('event_venue_schedule_drafts')
            ->where('event_id', $event->id)->value('options'), true) ?: [];
        $scheduleDraft = array_replace([
            'start' => optional($event->start_date)->format('Y-m-d').'T08:00',
            'end' => optional($event->start_date)->format('Y-m-d').'T18:00',
            'duration' => 60,
            'wave_minutes' => 90,
            'court_gap' => 5,
            'player_rest' => 30,
            'limit_daily_matches' => false,
            'draw_starts' => [],
            'draw_rounds' => [],
            'venue_starts' => [],
            'reschedule_existing' => false,
            'round_progression' => 'team_ready',
            'gender_waves' => 'combined',
            'gender_wave_release' => 'whole_wave',
            'tie_allocation' => 'balanced',
            'rank_venue_preferences' => [], 'cross_band_policy' => 'highest_ranked',
        ], $storedScheduleDraft);
        foreach (['start', 'end'] as $key) {
            if (! empty($scheduleDraft[$key])) {
                $scheduleDraft[$key] = \Carbon\Carbon::parse($scheduleDraft[$key])->format('Y-m-d\TH:i');
            }
        }
        $planningScope = $this->calendarScope($request, $event);
        $planningDate = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? null;
        $replanSaved = $request->validate(['replan_saved' => ['sometimes', 'boolean']])['replan_saved'] ?? false;
        abort_if($replanSaved && ! $planningDate, 422, 'Choose one saved playing day to replan.');
        $savedDayReplan = null;
        if ($replanSaved) {
            $savedRows = app(\App\Services\Scheduling\SchedulePublicationService::class)->workingRows($event)
                ->filter(fn ($row) => substr($row['scheduled_at'], 0, 10) === $planningDate
                    && (empty($planningScope['draw_id']) || (int) $row['draw_id'] === (int) $planningScope['draw_id'])
                    && (! $selectionSupplied || $requestedDrawIds->contains((int) $row['draw_id']))
                    && (empty($planningScope['venue_id']) || (int) $row['venue_id'] === (int) $planningScope['venue_id']));
            abort_if($savedRows->isEmpty(), 422, 'There are no saved matches in this day and selection.');
            $keys = $savedRows->pluck('fixture_key')->all();
            $selectedRounds = $draws->map(function ($draw) use ($keys) {
                $rounds = collect($draw['day_round_details'])->filter(fn ($groups) => collect($groups)
                    ->flatMap(fn ($group) => $group['matches'] ?? [])->contains(fn ($match) => in_array($match['key'], $keys, true)))->keys()->map(fn ($round) => (int) $round)->all();
                return ['draw_id' => $draw['id'], 'rounds' => $rounds];
            })->filter(fn ($row) => $row['rounds'])->values();
            $savedDayReplan = array_replace($storedScheduleDraft['day_batch_settings'][$planningDate] ?? $storedScheduleDraft, [
                'full_day_scope' => $planningDate, 'draw_ids' => $selectedRounds->pluck('draw_id')->all(),
                'draw_order' => $selectedRounds->pluck('draw_id')->all(), 'draw_rounds' => $selectedRounds->all(),
                'selected_fixture_keys' => $keys, 'reschedule_existing' => true,
            ]);
            $priorOrder = $storedScheduleDraft['day_batch_settings'][$planningDate]['draw_order'] ?? $storedScheduleDraft['draw_order'] ?? [];
            $savedDayReplan['draw_order'] = collect($priorOrder)->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => in_array($id, $savedDayReplan['draw_ids'], true))
                ->concat($savedDayReplan['draw_ids'])->unique()->values()->all();
            // Daily court availability is independent of the venue's physical court inventory.
            $savedCourts = $savedDayReplan['day_court_labels'] ?? [];
            $savedDayReplan['day_court_labels'] = $savedRows->groupBy('venue_id')->mapWithKeys(fn ($rows, $id) => [
                $id => isset($savedCourts[$id])
                    ? $savedCourts[$id] : $rows->pluck('court')->map(fn ($court) => (string) $court)->unique()->values()->all(),
            ])->all();
            $savedDayReplan['start'] = $planningDate.' '.substr($savedDayReplan['start'] ?? '2000-01-01 08:00', 11, 5);
            $savedDayReplan['end'] = $planningDate.' '.substr($savedDayReplan['end'] ?? '2000-01-01 18:00', 11, 5);
            $scheduleDraft['day_batch_settings'][$planningDate] = $savedDayReplan;
        }
        if ($planningDate) {
            $scheduleDraft['start'] = $planningDate.'T08:00';
            $scheduleDraft['end'] = $planningDate.'T18:00';
            $scheduleDraft['draw_starts'] = [];
            $scheduleDraft['venue_starts'] = [];
            $scheduleDraft['reschedule_existing'] = false;
        }
        $scheduleDraft['draw_starts'] = collect($scheduleDraft['draw_starts'] ?? [])
            ->filter(fn ($row) => isset($row['draw_id'], $row['start']) && $eventDraws->contains('id', (int) $row['draw_id']))
            ->mapWithKeys(fn ($row) => [(int) $row['draw_id'] => \Carbon\Carbon::parse($row['start'])->format('Y-m-d\TH:i')]);
        $scheduleDraft['draw_rounds'] = collect($scheduleDraft['draw_rounds'] ?? [])
            ->filter(fn ($row) => isset($row['draw_id'], $row['rounds']) && $eventDraws->contains('id', (int) $row['draw_id']))
            ->mapWithKeys(fn ($row) => [(int) $row['draw_id'] => array_map('intval', $row['rounds'])]);
        $scheduleDraft['venue_starts'] = collect($scheduleDraft['venue_starts'] ?? [])
            ->filter(fn ($row) => isset($row['venue_id'], $row['start']) && $availableVenues->contains('id', (int) $row['venue_id']))
            ->mapWithKeys(fn ($row) => [(int) $row['venue_id'] => \Carbon\Carbon::parse($row['start'])->format('Y-m-d\TH:i')]);

        $latestAdaptationIds = DrawAuditLog::whereIn('draw_id', $eventDraws->pluck('id'))
            ->where('action', 'team_draw_adapted')->selectRaw('MAX(id)')->groupBy('draw_id');
        $adaptationLogs = DrawAuditLog::whereIn('id', $latestAdaptationIds)->latest('id')->limit(20)->get();
        $pendingAdaptedCounts = TeamFixture::withoutEagerLoads()->whereIn('draw_id', $adaptationLogs->pluck('draw_id'))
            ->whereNull('scheduled_at')->where('match_status', 0)->whereDoesntHave('fixtureResults')
            ->selectRaw('draw_id, COUNT(*) AS pending_count')->groupBy('draw_id')->pluck('pending_count', 'draw_id');
        $reviewTieIds = $adaptationLogs->flatMap(fn ($log) => $log->payload['report']['review_tie_ids'] ?? [])->unique();
        $draftReviewTieIds = TeamTie::whereIn('id', $reviewTieIds)->whereIn('draw_id', $eventDraws->pluck('id'))
            ->where('status', TeamTie::STATUS_DRAFT)->pluck('id')->all();
        $adaptationNotices = $adaptationLogs->map(function ($log) use ($eventDraws, $pendingAdaptedCounts, $draftReviewTieIds) {
            $report = $log->payload['report'] ?? [];
            $pending = (int) ($pendingAdaptedCounts[$log->draw_id] ?? 0);
            $needsReview = (bool) ($report['review_required'] ?? false)
                || (bool) array_intersect($report['review_tie_ids'] ?? [], $draftReviewTieIds);
            return ['draw_name' => $eventDraws->firstWhere('id', $log->draw_id)?->drawName,
                'pending_count' => $pending, 'review_required' => $needsReview,
                'warnings' => array_slice($report['warnings'] ?? [], 0, 5)];
        })->filter(fn ($notice) => $notice['pending_count'] > 0 || $notice['review_required'])->values();

        return view('backend.schedule.event-venue-schedule', compact(
            'event', 'draws', 'venues', 'allVenues', 'announcementDraft', 'scheduleDraft', 'adaptationNotices', 'planningDate', 'planningScope', 'pendingPlayoffDraws', 'pendingSecondRoundRobinDraws', 'savedDayReplan'
        ));
    }

    private function standardPairCount(Draw $draw, string $code): ?int
    {
        $rubbers = $draw->team_format_snapshot['rubbers'] ?? [];
        if (! $rubbers || count($rubbers) > ($code === 'doubles' ? 50 : 100)) return null;
        $previousSequence = 0;
        foreach (array_values($rubbers) as $index => $rubber) {
            $positions = [2 * $index + 1, 2 * $index + 2];
            if (($rubber['rubber_code'] ?? null) !== $code
                || (int) ($rubber['sequence'] ?? 0) <= $previousSequence
                || ($rubber['home_positions'] ?? []) !== $positions
                || ($rubber['away_positions'] ?? []) !== $positions) return null;
            $previousSequence = (int) $rubber['sequence'];
        }

        return count($rubbers);
    }

    public function calendar(Request $request, Event $event, \App\Services\Scheduling\SchedulePublicationService $publication)
    {
        $this->authorize('event.manage', $event);
        $scope = $this->calendarScope($request, $event);
        $reviewOptions = $request->validate(['group' => ['nullable', 'in:draw'], 'page' => ['nullable', 'integer', 'min:1'], 'retained_page' => ['nullable', 'integer', 'min:1'], 'focus' => ['nullable', 'regex:/^(team|individual):[1-9][0-9]*$/']]);
        if (($reviewOptions['group'] ?? null) === 'draw') {
            $scope['date'] ??= 'all';
            $review = app(\App\Services\Scheduling\ScheduleDrawReviewService::class)->build($event, $scope, $request->integer('page', 1));
            return response()->view('backend.schedule.scheduled-matches-by-draw', compact('event', 'scope') + $review)
                ->header('Cache-Control', 'no-store, private');
        }
        $working = $publication->workingRows($event);
        $dailySavedCounts = $working->groupBy(fn ($row) => substr($row['scheduled_at'], 0, 10))->map->count();
        $published = $publication->publishedRows($event)->keyBy('fixture_key');
        $publishedAssignments = $publication->publishedAssignments($event)->keyBy('fixture_key');
        $days = $working->concat($published->values())->groupBy(fn ($row) => substr($row['scheduled_at'], 0, 10))
            ->map(fn ($rows) => $rows->pluck('fixture_key')->unique()->count())->sortKeys();
        if ($event->start_date) {
            $start = \Carbon\Carbon::parse($event->start_date)->startOfDay();
            $end = $event->end_date ? \Carbon\Carbon::parse($event->end_date)->startOfDay() : $start->copy();
            for ($day = $start->copy(), $i = 0; $day->lte($end) && $i < 31; $day->addDay(), $i++) {
                if (! $days->has($day->toDateString())) $days->put($day->toDateString(), 0);
            }
            $days = $days->sortKeys();
        }
        $date = $scope['date'] ?? 'all';
        $scope['date'] = $date;
        $filter = fn ($row) => ($date === 'all' || substr($row['scheduled_at'], 0, 10) === $date)
            && (empty($scope['venue_id']) || (int) $row['venue_id'] === (int) $scope['venue_id'])
            && (empty($scope['draw_id']) || (int) $row['draw_id'] === (int) $scope['draw_id']);
        $rows = $working->filter($filter)->map(function ($row) use ($publishedAssignments, $published) {
            $public = $publishedAssignments->get($row['fixture_key']);
            $same = $public && collect(['scheduled_at', 'venue_id', 'court', 'duration'])->every(fn ($key) => (string) $row[$key] === (string) $public[$key]);
            return $row + ['publication_state' => $same ? ($published->has($row['fixture_key']) ? 'Published' : 'Not publicly visible') : ($public ? 'Updates private' : 'Private')];
        });
        $workingKeys = $rows->pluck('fixture_key');
        $retained = $published->values()->filter($filter)->reject(fn ($row) => $workingKeys->contains($row['fixture_key']));
        $venues = $event->venues()->get()->concat($event->draws()->with('venues')->get()->flatMap(fn ($draw) => $draw->venues))->unique('id')->sortBy('name');
        $draws = $event->draws()->orderBy('drawName')->get();
        $publicationCounts = $rows->countBy('publication_state');
        $savedCount = $rows->count();
        $retainedCount = $retained->count();
        $page = $request->integer('page', 1);
        if (! empty($reviewOptions['focus'])) {
            $focusIndex = $rows->values()->search(fn ($row) => $row['fixture_key'] === $reviewOptions['focus']);
            abort_if($focusIndex === false, 404);
            $page = intdiv($focusIndex, 100) + 1;
        }
        $retainedPage = max(1, $request->integer('retained_page', 1));
        $savedPages = new \Illuminate\Pagination\LengthAwarePaginator($rows->forPage($page, 100)->values(), $savedCount, 100, $page,
            ['path' => $request->url(), 'query' => $request->except('page', 'focus')]);
        $retainedPages = new \Illuminate\Pagination\LengthAwarePaginator($retained->forPage($retainedPage, 100)->values(), $retainedCount, 100, $retainedPage,
            ['path' => $request->url(), 'query' => $request->except('retained_page', 'focus'), 'pageName' => 'retained_page']);
        $rows = collect($savedPages->items());
        $retained = collect($retainedPages->items());
        $rows = app(\App\Services\Scheduling\ScheduleDrawReviewService::class)->decorateRows($event, $rows, $draws);
        $drawIds = $draws->pluck('id');
        $unscheduledCount = Fixture::whereIn('draw_id', $drawIds)->when(! empty($scope['draw_id']), fn ($q) => $q->where('draw_id', $scope['draw_id']))
            ->where('match_status', 0)->whereDoesntHave('fixtureResults')->whereDoesntHave('orderOfPlay', fn ($q) => $q->whereNotNull('time'))->count()
            + TeamFixture::whereIn('draw_id', $drawIds)->when(! empty($scope['draw_id']), fn ($q) => $q->where('draw_id', $scope['draw_id']))->where('match_status', 0)->whereDoesntHave('fixtureResults')->whereNull('scheduled_at')->count();
        $pendingPlayoffs = app(\App\Services\Scheduling\PlayoffScheduleReadinessService::class)->pending(
            $draws->when(!empty($scope['draw_id']), fn ($items) => $items->where('id', (int) $scope['draw_id']))
        );
        $revision = $publication->revision($event);
        return view('backend.schedule.saved-calendar', compact('event', 'scope', 'days', 'date', 'rows', 'retained', 'venues', 'draws', 'revision', 'unscheduledCount', 'pendingPlayoffs', 'dailySavedCounts', 'publicationCounts', 'savedCount', 'retainedCount', 'savedPages', 'retainedPages'));
    }

    public function editDay(Request $request, Event $event, EventVenueScheduleService $scheduler,
        \App\Services\Scheduling\SchedulePublicationService $publication)
    {
        $this->authorize('event.manage', $event);
        if ($request->isMethod('post')) {
            $request->merge(['bands' => $request->input('bands', [])]);
            if ($request->boolean('court_labels_present')) $request->merge(['court_labels' => $request->input('court_labels', [])]);
        }
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'], 'draw_id' => ['required', 'integer'],
            'origin_draw_id' => ['nullable', 'integer', 'min:1'],
            'venue_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:100'],
            'venue_ids.*' => ['integer', 'distinct'], 'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'duration' => ['sometimes', 'required', 'integer', 'min:1', 'max:480'],
            'player_rest' => ['sometimes', 'required', 'integer', 'min:0', 'max:480'],
            'court_gap' => ['sometimes', 'required', 'integer', 'min:0', 'max:120'],
            'action' => ['sometimes', 'in:preview,save'], 'revision' => ['nullable', 'string', 'size:64'],
            'court_labels' => ['sometimes', 'array', 'max:100'], 'court_labels.*' => ['array', 'max:100'],
            'court_labels.*.*' => ['required', 'string', 'max:50'],
            'court_labels_present' => ['sometimes', 'boolean'],
            'bands' => ['sometimes', 'array', 'max:50'], 'bands.*.min_rank' => ['required', 'integer', 'min:1', 'max:100'],
            'bands.*.max_rank' => ['required', 'integer', 'min:1', 'max:100'], 'bands.*.venue_id' => ['required', 'integer'],
            'cross_band_policy' => ['sometimes', 'in:highest_ranked,manual'],

        ]);
        $draw = $event->draws()->findOrFail($data['draw_id']);
        $originDraw = ! empty($data['origin_draw_id']) ? $event->draws()->findOrFail($data['origin_draw_id']) : $draw;
        $originContext = ! empty($data['origin_draw_id']) ? ['origin_draw_id'=>$originDraw->id] : [];
        $rows = $publication->workingRows($event)->filter(fn ($row) => (int) $row['draw_id'] === $draw->id
            && substr($row['scheduled_at'], 0, 10) === $data['date'])->values();
        $venues = $event->venues()->get()->concat($draw->venues()->get())->unique('id')->sortBy('name');
        $allVenues = Venue::whereNotIn('id', $venues->pluck('id'))->orderBy('name')->get(['id', 'name']);
        $stored = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        $age = app(\App\Services\Scheduling\ScheduleProgramme::class)->key($draw);
        $settings = $stored['programme_settings'][(string) $age] ?? $stored;
        if (! $draw->isTeamDraw()) {
            $selectionKey = $stored['individual_programme_by_draw'][(string) $draw->id] ?? null;
            if ($selectionKey !== null) {
                $settings = $stored['programme_settings'][$selectionKey] ?? $settings;
            }
        }
        $courtOptions = $scheduler->dayCourtLabels($event, $draw);
        $defaultCourts = [];
        $fixtureRounds = $draw->usesFlexibleMonrad() ? $scheduler->programmeFixtureRounds($draw) : [];
        $dayRounds = $rows->map(fn ($row) => $fixtureRounds[$row['fixture_id']] ?? $row['round_nr'])->filter()->unique();
        $roundSetups = collect($stored['round_venue_setups'] ?? [])->filter(fn ($row) => (int) $row['draw_id'] === $draw->id && $dayRounds->contains((int) $row['round']));
        $selectedVenues = $rows->pluck('venue_id')->unique()->all();
        foreach ($courtOptions as $venueId => $labels) {
            $allocated = $roundSetups->flatMap(fn ($setup) => collect($setup['court_allocations'] ?? [])->where('venue_id', $venueId)->flatMap(fn ($allocation) => $allocation['court_labels']))
                ->merge($rows->where('venue_id', $venueId)->pluck('court'))->map(fn ($label) => (string) $label)->unique()->all();
            if (! $roundSetups->count()) {
                $allocated = DB::table('draw_venue_court_allocations')->where('draw_id', $draw->id)->where('venue_id', $venueId)->pluck('court_label')->map(fn ($label) => (string) $label)->merge($allocated)->unique()->all();
            }
            $defaultCourts[$venueId] = array_values(array_intersect($labels, $allocated)) ?: $labels;
        }
        $bandDefaults = [];
        $bandNotice = null;
        if ($draw->isTeamDraw()) {
            $candidateRules = $dayRounds->map(function ($round) use ($roundSetups, $stored, $draw) {
                $setup = $roundSetups->firstWhere('round', $round);
                return $setup['rank_venue_preferences'] ?? app(\App\Services\Scheduling\RankVenuePreferences::class)->active($stored['rank_venue_preferences'] ?? [], [$draw->id]);
            });
            if ($candidateRules->map(fn ($rules) => json_encode($rules))->unique()->count() > 1) {
                $bandNotice = 'Saved rounds on this day use different position bands. Choose the bands you want for this day.';
            } else {
                $bandDefaults = $candidateRules->first() ?? [];
                if (collect($bandDefaults)->contains(fn ($rule) => ! in_array((int) $rule['venue_id'], $selectedVenues, true))) {
                    $bandDefaults = [];
                    $bandNotice = 'Saved band venues differ from the current venues for this day. Choose bands for the destinations below.';
                }
            }
        }

        $dayAudit = DrawAuditLog::where('draw_id', $draw->id)->where('action', 'event_venue_schedule_applied')
            ->where('payload->event_id', $event->id)->where('payload->day_scope', $data['date'])->latest('id')->first();
        $savedDay = $dayAudit?->payload['day_options'] ?? null;
        if ($savedDay) {
            $savedVenues = array_map('intval', $savedDay['venue_ids'] ?? []);
            $valid = $savedVenues && ! array_diff($savedVenues, $venues->pluck('id')->all());
            foreach ($savedDay['day_court_labels'] ?? [] as $venueId => $labels) {
                if (! $labels || array_diff($labels, $courtOptions[$venueId] ?? [])) $valid = false;
            }
            if ($valid) {
                $selectedVenues = $savedVenues;
                $defaultCourts = array_replace($defaultCourts, $savedDay['day_court_labels'] ?? []);
                $bandDefaults = $savedDay['rank_venue_preferences'] ?? [];
                $bandNotice = 'Position bands and courts are loaded from the last saved edit for this draw and day.';
            } else {
                $bandNotice = 'The last saved day options include venues or courts that are no longer available. Review the current choices.';
            }
        }
        $values = array_replace(['venue_ids' => $selectedVenues, 'start_time' => $rows->isNotEmpty() ? substr($rows->min('scheduled_at'), 11, 5) : '08:00',
            'end_time' => '18:00', 'duration' => $settings['duration'] ?? 75,
            'player_rest' => $settings['player_rest'] ?? 60, 'court_gap' => $settings['court_gap'] ?? 5, 'court_labels' => $defaultCourts,
            'bands' => $bandDefaults, 'cross_band_policy' => $savedDay['cross_band_policy'] ?? $stored['cross_band_policy'] ?? 'highest_ranked'], $data);
        $preview = null;
        if ($request->isMethod('post')) {
            $values['bands'] = $data['bands'] ?? [];
            $request->validate(['venue_ids' => ['required'], 'start_time' => ['required'], 'end_time' => ['required'],
                'duration' => ['required'], 'player_rest' => ['required'], 'court_gap' => ['required'], 'action' => ['required']]);
            if (array_diff($values['venue_ids'], $venues->pluck('id')->all())) abort(422, 'Choose venues in this event.');
            $options = ['day_scope' => $data['date'], 'draw_ids' => [$draw->id], 'venue_ids' => $values['venue_ids'],
                'start' => $data['date'].' '.$values['start_time'], 'end' => $data['date'].' '.$values['end_time'],
                'duration' => $values['duration'], 'wave_minutes' => 0, 'player_rest' => $values['player_rest'],
                'court_gap' => $values['court_gap'], 'allow_partial' => false,
                'rank_venue_preferences' => $values['bands'], 'cross_band_policy' => $values['cross_band_policy']];
            if ($request->has('court_labels') || $request->boolean('court_labels_present')) $options['day_court_labels'] = $data['court_labels'] ?? [];
            try {
                if ($data['action'] === 'save') {
                    $request->validate(['revision' => ['required', 'string', 'size:64']]);
                    $result = $scheduler->apply($event, $options, $data['revision']);
                    return redirect()->route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'date' => $data['date'], 'draw_id' => $draw->id] + $originContext)
                        ->with('success', $result['count'].' match times updated for this draw and day. Publish updates separately when ready.');
                }
                $preview = $scheduler->preview($event, $options);
            } catch (\InvalidArgumentException $exception) {
                return redirect()->route('backend.event-venue-schedule.calendar.edit-day', ['event' => $event->id, 'date' => $data['date'], 'draw_id' => $draw->id] + $originContext)
                    ->withInput()->withErrors(['schedule' => $exception->getMessage()]);
            }
        }
        return response()->view('backend.schedule.edit-draw-day', compact('event', 'draw', 'data', 'rows', 'venues', 'allVenues', 'values', 'preview', 'courtOptions', 'bandNotice', 'originDraw', 'originContext'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function fullDay(Request $request, Event $event, EventVenueScheduleService $scheduler)
    {
        $this->authorize('event.manage', $event);
        if ($request->isMethod('post')) $request->merge(['court_starts' => $request->input('court_starts', []), 'venue_groups' => $request->input('venue_groups', []), 'venue_windows' => $request->input('venue_windows', []), 'tie_destinations' => $request->input('tie_destinations', []), 'destination_pools' => $request->input('destination_pools', [])]);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'draw_id' => ['nullable', 'integer', 'min:1'],
            'origin_draw_id' => ['nullable', 'integer', 'min:1'],
            'draw_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:200'], 'draw_ids.*' => ['integer', 'distinct'],
            'rounds' => ['sometimes', 'array', 'max:200'], 'rounds.*' => ['array', 'min:1', 'max:100'], 'rounds.*.*' => ['integer'],
            'venue_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:100'], 'venue_ids.*' => ['integer', 'distinct'],
            'court_labels' => ['sometimes', 'array', 'max:100'], 'court_labels.*' => ['array', 'min:1', 'max:100'], 'court_labels.*.*' => ['string', 'max:50'],
            'court_starts' => ['sometimes', 'array', 'max:100'], 'court_starts.*' => ['array', 'max:100'],
            'court_starts.*.*' => ['array:court,start_time,end_time'], 'court_starts.*.*.court' => ['required', 'string', 'max:50'],
            'court_starts.*.*.start_time' => ['nullable', 'date_format:H:i'], 'court_starts.*.*.end_time' => ['nullable', 'date_format:H:i'],
            'venue_groups' => ['sometimes', 'array', 'max:100'], 'venue_groups.*' => ['array', 'size:2'],
            'venue_groups.*.*' => ['array', 'min:1', 'max:100'], 'venue_groups.*.*.*' => ['required', 'string', 'max:50'],
            'venue_windows' => ['sometimes', 'array', 'max:100'], 'venue_windows.*' => ['array:start_time,end_time'],
            'venue_windows.*.start_time' => ['nullable', 'date_format:H:i'], 'venue_windows.*.end_time' => ['nullable', 'date_format:H:i'],
            'tie_destinations' => ['sometimes', 'array', 'max:2000'], 'tie_destinations.*' => ['nullable', 'string', 'regex:/^\d+(?::[AB])?$/'],
            'destination_pools' => ['sometimes', 'array', 'max:200'], 'destination_pools.*' => ['array', 'max:200'],
            'destination_pools.*.*' => ['nullable', 'string', 'regex:/^\d+(?::[AB])?$/'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'], 'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'duration' => ['sometimes', 'required', 'integer', 'min:1', 'max:480'],
            'player_rest' => ['sometimes', 'required', 'integer', 'min:0', 'max:480'], 'court_gap' => ['sometimes', 'required', 'integer', 'min:0', 'max:120'],
            'action' => ['sometimes', 'in:preview,save'], 'revision' => ['nullable', 'string', 'size:64'],
        ]);
        $draws = $event->draws()->with('venues')->orderBy('drawName')->get();
        foreach (['draw_id', 'origin_draw_id'] as $contextKey) {
            if (! empty($data[$contextKey]) && ! $draws->contains('id', (int) $data[$contextKey])) abort(404);
        }
        $contextDrawId = $data['origin_draw_id'] ?? $data['draw_id'] ?? null;
        $contextDraw = $contextDrawId ? $draws->firstWhere('id', (int) $contextDrawId) : null;
        $currentRows = app(\App\Services\Scheduling\SchedulePublicationService::class)->workingRows($event)->keyBy('fixture_key');
        $venues = $event->venues()->orderBy('name')->get();
        $roundOptions = $scheduler->availableDrawRounds($draws);
        $courtOptions = $scheduler->fullDayCourtLabels($event);
        $tieOptions = $scheduler->fullDayTies($event);
        $poolOptions = $scheduler->fullDayDestinationPools($event);
        $savedNotice = null;
        $saved = DrawAuditLog::whereIn('draw_id', $draws->pluck('id'))->where('action', 'event_venue_schedule_applied')
            ->where('payload->event_id', $event->id)->where('payload->full_day_scope', $data['date'])->latest('id')->first()?->payload['full_day_options'] ?? null;
        $defaults = [];
        if ($saved) {
            $valid = ! array_diff($saved['venue_ids'] ?? [], $venues->pluck('id')->all())
                && ! array_diff($saved['draw_ids'] ?? [], $draws->pluck('id')->all());
            foreach ($saved['day_court_labels'] ?? [] as $venueId => $labels) {
                if (! $labels || array_diff($labels, $courtOptions[$venueId] ?? [])) $valid = false;
            }
            foreach ($saved['draw_rounds'] ?? [] as $row) {
                if (array_diff($row['rounds'], $roundOptions[$row['draw_id']] ?? [])) $valid = false;
            }
            $currentTies = collect($tieOptions)->filter(fn ($tie) => in_array('unscheduled', $tie['dates'], true) || in_array($data['date'], $tie['dates'], true))->pluck('key')->all();
            if (array_diff(array_keys($saved['full_day_tie_destinations'] ?? []), $currentTies)) $valid = false;
            $currentPools = collect($poolOptions)->filter(fn ($pool) => array_intersect($pool['draw_ids'], $saved['draw_ids'] ?? []))->pluck('key')->all();
            if (array_diff(array_keys($saved['full_day_destination_pools'] ?? []), $currentPools)) $valid = false;
            if ($valid) {
                $defaults = ['draw_ids'=>$saved['draw_ids'], 'rounds'=>collect($saved['draw_rounds'])->mapWithKeys(fn ($row) => [$row['draw_id']=>$row['rounds']])->all(),
                    'venue_ids'=>$saved['venue_ids'], 'court_labels'=>$saved['day_court_labels'], 'venue_groups'=>$saved['day_venue_groups'] ?? [],
                    'venue_windows'=>collect($saved['full_day_venue_windows'] ?? [])->map(fn ($window) => ['start_time'=>substr($window['start'], 11, 5), 'end_time'=>substr($window['end'], 11, 5)])->all(),
                    'tie_destinations'=>collect($saved['full_day_tie_destinations'] ?? [])->map(fn ($destination) => $destination['venue_id'].($destination['group'] ? ':'.$destination['group'] : ''))->all(),
                    'destination_pools'=>$saved['full_day_destination_pools'] ?? [],
                    'court_starts'=>collect($saved['full_day_court_starts'] ?? [])->map(fn ($rows) => collect($rows)->map(fn ($row) => ['court'=>$row['court'], 'start_time'=>substr($row['start'],11,5), 'end_time'=>substr($row['end'],11,5)])->all())->all(),
                    'start_time'=>substr($saved['start'], 11, 5), 'end_time'=>substr($saved['end'], 11, 5),
                    'duration'=>$saved['duration'], 'player_rest'=>$saved['playerRest'], 'court_gap'=>$saved['courtGap']];
                $savedNotice = 'Loaded the last saved full-day choices, including court groups and venue hours. Review tie destinations before previewing.';
            } else {
                $savedNotice = 'The last saved full-day choices include draws, rounds, ties or courts that are no longer available. Court groups have not been restored; select venues, courts, groups and tie destinations again before previewing.';
            }
        }
        $values = array_replace(['draw_ids' => [], 'rounds' => [], 'venue_ids' => $venues->pluck('id')->all(),
            'court_labels' => $courtOptions, 'court_starts' => [], 'venue_groups' => [], 'venue_windows' => [], 'tie_destinations'=>[], 'destination_pools'=>[], 'start_time' => '08:00', 'end_time' => '18:00', 'duration' => 75, 'player_rest' => 60, 'court_gap' => 5], $defaults, $data);
        $preview = null;
        if ($request->isMethod('post')) {
            $request->validate(['draw_ids' => ['required'], 'rounds' => ['required'], 'venue_ids' => ['required'], 'court_labels' => ['required'],
                'start_time' => ['required'], 'end_time' => ['required'], 'duration' => ['required'], 'player_rest' => ['required'], 'court_gap' => ['required'], 'action' => ['required']]);
            $options = ['full_day_scope' => $data['date'], 'draw_ids' => $data['draw_ids'], 'venue_ids' => $data['venue_ids'],
                'draw_rounds' => collect($data['rounds'])->map(fn ($rounds, $drawId) => ['draw_id' => (int) $drawId, 'rounds' => $rounds])->values()->all(),
                'day_court_labels' => $data['court_labels'], 'start' => $data['date'].' '.$data['start_time'], 'end' => $data['date'].' '.$data['end_time'],
                'day_venue_groups' => $data['venue_groups'] ?? [],
                'full_day_tie_destinations' => collect($data['tie_destinations'] ?? [])->filter(fn ($value) => $value !== null && $value !== '')
                    ->map(function ($value) { $parts = explode(':', $value); return ['venue_id'=>(int) $parts[0], 'group'=>$parts[1] ?? null]; })->all(),
                'full_day_destination_pools' => collect($data['destination_pools'] ?? [])->map(fn ($choices) => array_values(array_filter($choices, fn ($value) => $value !== null && $value !== '')))->all(),
                'duration' => $data['duration'], 'player_rest' => $data['player_rest'], 'court_gap' => $data['court_gap'], 'wave_minutes' => 0, 'allow_partial' => false];
            $options['full_day_court_starts'] = collect($data['court_starts'] ?? [])->map(fn ($rows, $venueId) => collect($rows)->filter(fn ($row) => ! empty($row['start_time']) || ! empty($row['end_time']))->map(fn ($row) => ['court'=>$row['court'], 'start'=>$data['date'].' '.(($row['start_time'] ?? null) ?: (($data['venue_windows'][$venueId]['start_time'] ?? null) ?: $data['start_time'])), 'end'=>$data['date'].' '.(($row['end_time'] ?? null) ?: (($data['venue_windows'][$venueId]['end_time'] ?? null) ?: $data['end_time']))])->values()->all())->filter()->all();
            $options['full_day_venue_windows'] = collect($data['venue_windows'] ?? [])
                ->filter(fn ($window) => ! empty($window['start_time']) || ! empty($window['end_time']))
                ->map(fn ($window) => ['start'=>$data['date'].' '.(($window['start_time'] ?? null) ?: $data['start_time']),
                    'end'=>$data['date'].' '.(($window['end_time'] ?? null) ?: $data['end_time'])])->all();
            try {
                if ($data['action'] === 'save') {
                    $request->validate(['revision' => ['required', 'string', 'size:64']]);
                    $result = $scheduler->apply($event, $options, $data['revision']);
                    return redirect()->route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'date' => $data['date']]
                        + (count($data['draw_ids']) === 1 ? ['draw_id' => $data['draw_ids'][0]] : [])
                        + ($contextDraw ? ['origin_draw_id' => $contextDraw->id] : []))
                        ->with('success', $result['count'].' match times saved for this day. Publish updates separately when ready.');
                }
                $preview = $scheduler->preview($event, $options);
            } catch (\InvalidArgumentException $exception) {
                return back()->withInput()->withErrors(['schedule' => $exception->getMessage()]);
            }
        }
        return response()->view('backend.schedule.full-day', compact('event', 'data', 'draws', 'venues', 'roundOptions', 'courtOptions', 'tieOptions', 'poolOptions', 'values', 'preview', 'savedNotice', 'contextDraw', 'currentRows'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function clearDay(Request $request, Event $event, EventVenueScheduleService $scheduler)
    {
        $this->authorize('event.manage', $event);
        $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'revision' => ['required', 'string', 'size:64']]);
        $scope = $this->calendarScope($request, $event);
        try {
            $result = $scheduler->clearDay($event, $scope, $request->string('revision')->toString());
        } catch (\InvalidArgumentException $exception) {
            return redirect()->route('backend.event-venue-schedule.calendar', ['event' => $event->id] + $scope)
                ->withErrors(['schedule' => $exception->getMessage()]);
        }
        return redirect()->route('backend.event-venue-schedule.calendar', ['event' => $event->id] + $scope)
            ->with('success', $result['count'].' match times cleared from this selection. Fixtures and results were preserved. Published times remain until you publish updates or hide them.');
    }

    public function auditSchedule(Request $request, Event $event, \App\Services\Scheduling\ScheduleAuditService $auditor)
    {
        $this->authorize('event.manage', $event);
        $scope = $this->calendarScope($request, $event);
        $scope['date'] ??= 'all';
        $report = $auditor->audit($event, $scope);

        return view('backend.schedule.schedule-audit', compact('event', 'scope', 'report'));
    }

    public function publishScope(Request $request, Event $event, \App\Services\Scheduling\SchedulePublicationService $publication)
    {
        return $this->changePublication($request, $event, $publication, false);
    }

    public function publicationStatus(Request $request, Event $event, \App\Services\Scheduling\SchedulePublicationStatusService $status)
    {
        $this->authorize('event.manage', $event);
        $scope = $this->calendarScope($request, $event);
        try {
            return response()->json(['success' => true] + $status->snapshot($event, isset($scope['draw_id']) ? (int) $scope['draw_id'] : null));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 503);
        }
    }

    public function hideScope(Request $request, Event $event, \App\Services\Scheduling\SchedulePublicationService $publication)
    {
        return $this->changePublication($request, $event, $publication, true);
    }

    public function publicPreview(Request $request, Event $event, \App\Services\Scheduling\SchedulePublicationService $publication)
    {
        $this->authorize('event.manage', $event);
        $scope = $this->calendarScope($request, $event);
        $request->validate(['after_publish' => ['nullable', 'boolean'], 'hide_times' => ['nullable', 'boolean']]);
        $afterPublish = $request->boolean('after_publish');
        $hideTimes = $request->boolean('hide_times');
        $request->attributes->set('public_schedule_preview', true);
        $allRows = ($afterPublish || $hideTimes) ? $publication->previewAfterPublishingRows($event, $scope) : $publication->publishedRows($event);
        $rows = $allRows->filter(fn ($row) => (empty($scope['date']) || $scope['date'] === 'all' || substr($row['scheduled_at'], 0, 10) === $scope['date'])
            && (empty($scope['venue_id']) || (int) $row['venue_id'] === (int) $scope['venue_id'])
            && (empty($scope['draw_id']) || (int) $row['draw_id'] === (int) $scope['draw_id']))->take(1000)->values();
        // Use the same fixture presentation and public lineup rules as the player pages.
        $drawIds = app(\App\Services\PublicTournamentVisibility::class)->eventIsVisible($event)
            ? $event->draws()->where('published', true)
                ->when(! empty($scope['draw_id']), fn ($query) => $query->whereKey($scope['draw_id']))
                ->when(empty($scope['draw_id']) && $rows->isNotEmpty(), fn ($query) => $query->whereIn('id', $rows->pluck('draw_id')))->pluck('id')
            : collect();
        $individualFixtures = \App\Models\Fixture::with(['draw.settings', 'registration1.players', 'registration2.players', 'fixtureResults'])
            ->whereIn('draw_id', $drawIds)->orderByRaw('CAST(match_nr AS UNSIGNED)')->limit(1000)->get();
        $teamFixtures = \App\Models\TeamFixture::with(['draw.settings', 'fixtureResults'])
            ->whereIn('draw_id', $drawIds)->publicDrawFixtures()->inPlayOrder()->limit(1000)->get();
        $fixtures = $individualFixtures->concat($teamFixtures);
        $publication->projectFixtures($fixtures, $hideTimes ? collect() : $allRows);
        app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($teamFixtures, publicDraw: true);
        $previewDraws = $fixtures->groupBy('draw_id')->map(function ($drawFixtures) use ($afterPublish, $hideTimes, $request, $allRows) {
            $draw = $drawFixtures->first()->draw;
            $hasPublicTimes = ! $hideTimes && $allRows->contains('draw_id', $draw->id);
            if ($afterPublish || $hideTimes) $draw->setAttribute('oop_published', $hasPublicTimes);
            if ($afterPublish) $request->attributes->set('draw_schedule_exists_'.$draw->event_id.'_'.$draw->id, $hasPublicTimes);
            if ($drawFixtures->first() instanceof \App\Models\TeamFixture) {
                $drawFixtures = app(\App\Services\Scheduling\TeamFixtureOrder::class)->sort($drawFixtures);
            }
            return ['draw' => $draw, 'fixtures' => $drawFixtures];
        });

        return view('backend.schedule.public-schedule-preview', compact('event', 'scope', 'afterPublish', 'hideTimes', 'previewDraws'));
    }

    private function changePublication(Request $request, Event $event, \App\Services\Scheduling\SchedulePublicationService $publication, bool $hide)
    {
        $this->authorize('event.manage', $event);
        $request->validate(['revision' => ['required', 'string', 'size:64'], 'date' => ['required', 'date_format:Y-m-d'], 'venue_id' => ['nullable', 'integer']]);
        $scope = $this->calendarScope($request, $event);
        try {
            $scope['revision'] = (string) $request->string('revision');
            $count = $hide ? $publication->hide($event, $scope) : $publication->publish($event, $scope);
            unset($scope['revision']);
            if ($request->expectsJson()) return response()->json(['success' => true, 'action' => $hide ? 'hide' : 'publish', 'changed' => $count]
                + app(\App\Services\Scheduling\SchedulePublicationStatusService::class)->snapshot($event, isset($scope['draw_id']) ? (int) $scope['draw_id'] : null));
            return redirect()->route('backend.event-venue-schedule.calendar', ['event' => $event->id] + $scope)
                ->with('success', $hide ? "Hidden {$count} public match times." : "Published {$count} saved match times. Other saved changes remain private.");
        } catch (\InvalidArgumentException $exception) {
            if ($request->expectsJson()) return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
            return back()->withErrors(['schedule' => $exception->getMessage()]);
        }
    }

    private function calendarScope(Request $request, Event $event): array
    {
        $scope = $request->validate(['date' => ['nullable', \Illuminate\Validation\Rule::when($request->input('date') !== 'all', ['date_format:Y-m-d'])], 'venue_id' => ['nullable', 'integer'], 'draw_id' => ['nullable', 'integer', 'min:1'], 'origin_draw_id' => ['nullable', 'integer', 'min:1']]);
        if (! empty($scope['origin_draw_id'])) abort_unless($event->draws()->whereKey($scope['origin_draw_id'])->exists(), 422, 'Choose an origin draw in this event.');
        if (! empty($scope['draw_id'])) abort_unless($event->draws()->whereKey($scope['draw_id'])->exists(), 422, 'Choose a draw in this event.');
        if (! empty($scope['venue_id'])) abort_unless($event->venues()->whereKey($scope['venue_id'])->exists()
            || DB::table('draw_venues')->whereIn('draw_id', $event->draws()->pluck('id'))->where('venue_id', $scope['venue_id'])->exists(), 422, 'Choose a venue in this event.');
        return array_filter($scope, fn ($value) => $value !== null);
    }

    public function addVenue(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'venue_id' => ['nullable', 'integer', 'exists:venues,id', 'required_without:name'],
            'name' => ['nullable', 'string', 'max:191', 'required_without:venue_id', 'regex:/\S/'],
            'courts' => ['required', 'integer', 'min:1', 'max:100'],
            'ball_type' => ['nullable', 'in:orange,green,yellow,red,standard'],
        ]);
        $venue = ! empty($data['venue_id']) ? Venue::findOrFail($data['venue_id']) : tap(new Venue(), function ($venue) use ($data) {
            $venue->forceFill(['name' => trim($data['name'])])->save();
        });
        $event->venues()->syncWithoutDetaching([$venue->id => ['num_courts' => $data['courts']]]);
        foreach (range(1, $data['courts']) as $label) {
            DB::table('event_venue_courts')->insertOrIgnore([
                'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => (string) $label,
                'ball_type' => $data['ball_type'] ?: null, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $courtLabels = DB::table('event_venue_courts')->where('event_id', $event->id)
            ->where('venue_id', $venue->id)->where('active', true)->orderBy('id')->pluck('label')->map(fn ($label) => (string) $label)->all();
        $courtCount = count($courtLabels);
        $event->venues()->syncWithoutDetaching([$venue->id => ['num_courts' => $courtCount]]);
        return response()->json([
            'message' => "{$venue->name} added with {$courtCount} courts.",
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'num_courts' => $courtCount,
                'court_labels' => $courtLabels,
                'ball_type' => $data['ball_type'] ?: 'standard',
            ],
        ]);
    }

    public function removeVenue(Event $event, Venue $venue)
    {
        $this->authorize('event.manage', $event);

        return DB::transaction(function () use ($event, $venue) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $drawIds = $event->draws()->pluck('id');
            $belongs = $event->venues()->whereKey($venue->id)->exists()
                || DB::table('draw_venues')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)->exists();
            abort_unless($belongs, 404);

            $protected = DB::table('draw_venues')->join('draws', 'draws.id', '=', 'draw_venues.draw_id')
                ->where('draws.event_id', $event->id)->where('draw_venues.venue_id', $venue->id)
                ->where('draws.locked', true)->exists();
            $protectedAllocation = DB::table('draw_venue_court_allocations')->join('draws', 'draws.id', '=', 'draw_venue_court_allocations.draw_id')
                ->where('draws.event_id', $event->id)->where('draw_venue_court_allocations.venue_id', $venue->id)
                ->where('draws.locked', true)->exists();
            if ($protected || $protectedAllocation) {
                return response()->json(['message' => 'This venue is assigned to a locked draw and cannot be removed.'], 422);
            }

            $fixtureIds = Fixture::whereIn('draw_id', $drawIds)->pluck('id');
            $scheduled = OrderOfPlay::where('venue_id', $venue->id)
                ->where(fn ($query) => $query->whereIn('draw_id', $drawIds)->orWhereIn('fixture_id', $fixtureIds))->exists();
            $teamScheduled = TeamFixture::whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)
                ->whereNotNull('scheduled_at')->exists();
            $publishedScheduled = DB::table('published_schedule_assignments')
                ->where('event_id', $event->id)->whereIn('draw_id', $drawIds)
                ->where('venue_id', $venue->id)->exists();
            if ($publishedScheduled) {
                return response()->json(['message' => 'This venue has published match times. Update or hide the published schedule before removing it. Moving saved matches alone does not change the published times.'], 422);
            }
            if ($scheduled || $teamScheduled) {
                return response()->json(['message' => 'This venue has saved matches. Clear or move those bookings before removing it.'], 422);
            }

            DB::table('draw_venue_court_allocations')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)->delete();
            DB::table('draw_venues')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)->delete();
            DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $venue->id)->delete();
            $event->venues()->detach($venue->id);
            $this->removeVenuePreferences($event, $venue);

            return response()->json(['message' => "{$venue->name} removed from this event.", 'venue_id' => $venue->id]);
        });
    }

    public function removeDrawVenue(Event $event, \App\Models\Draw $draw, Venue $venue)
    {
        $this->authorize('event.manage', $event);
        abort_unless((int) $draw->event_id === (int) $event->id, 404);

        return DB::transaction(function () use ($event, $draw, $venue) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $draw->refresh();
            abort_unless($draw->venues()->whereKey($venue->id)->exists(), 404);
            if ($draw->locked) {
                return response()->json(['message' => 'A venue cannot be removed from a locked age group.'], 422);
            }
            $fixtureIds = Fixture::where('draw_id', $draw->id)->pluck('id');
            $scheduled = OrderOfPlay::where('venue_id', $venue->id)
                ->where(fn ($query) => $query->where('draw_id', $draw->id)->orWhereIn('fixture_id', $fixtureIds))->exists();
            $teamScheduled = TeamFixture::where('draw_id', $draw->id)->where('venue_id', $venue->id)
                ->whereNotNull('scheduled_at')->exists();
            $publishedScheduled = DB::table('published_schedule_assignments')
                ->where('event_id', $event->id)->where('draw_id', $draw->id)
                ->where('venue_id', $venue->id)->exists();
            if ($publishedScheduled) {
                return response()->json(['message' => 'This venue has published match times. Update or hide the published schedule before removing it. Moving saved matches alone does not change the published times.'], 422);
            }
            if ($scheduled || $teamScheduled) {
                return response()->json(['message' => 'This age group has saved matches at the venue. Clear or move those bookings before removing it.'], 422);
            }
            DB::table('draw_venue_court_allocations')->where('draw_id', $draw->id)->where('venue_id', $venue->id)->delete();
            $draw->venues()->detach($venue->id);
            $this->removeVenuePreferences($event, $venue, (int) $draw->id);

            return response()->json(['message' => "{$venue->name} removed from {$draw->drawName}.",
                'draw_id' => $draw->id, 'venue_id' => $venue->id]);
        });
    }

    private function removeVenuePreferences(Event $event, Venue $venue, ?int $drawId = null): void
    {
        $draft = DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->lockForUpdate()->first();
        if (! $draft) return;
        $options = json_decode((string) $draft->options, true) ?: [];
        $options['rank_venue_preferences'] = collect($options['rank_venue_preferences'] ?? [])->map(function ($rule) use ($venue, $drawId) {
            if ((int) ($rule['venue_id'] ?? 0) === (int) $venue->id) {
                $rule['draw_ids'] = $drawId === null ? [] : array_values(array_filter($rule['draw_ids'] ?? [], fn ($id) => (int) $id !== $drawId));
            }
            return $rule;
        })->filter(fn ($rule) => ! empty($rule['draw_ids']))->values()->all();
        $options['round_venue_setups'] = collect($options['round_venue_setups'] ?? [])->map(function ($row) use ($venue, $drawId) {
            if ($drawId !== null && (int) $row['draw_id'] !== $drawId) return $row;
            $row['venue_ids'] = array_values(array_filter($row['venue_ids'], fn ($id) => (int) $id !== (int) $venue->id));
            $row['court_allocations'] = array_values(array_filter($row['court_allocations'], fn ($allocation) => (int) $allocation['venue_id'] !== (int) $venue->id));
            $row['rank_venue_preferences'] = array_values(array_filter($row['rank_venue_preferences'], fn ($rule) => (int) $rule['venue_id'] !== (int) $venue->id));
            return $row;
        })->filter(fn ($row) => ! empty($row['venue_ids']))->values()->all();
        DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)
            ->update(['options' => json_encode($options), 'updated_at' => now()]);
    }

    public function addCourt(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'label' => ['required', 'string', 'max:50', 'regex:/\S/'],
            'ball_type' => ['nullable', 'in:orange,green,yellow,red,standard'],
            'append_only' => ['sometimes', 'boolean'],
        ]);
        return DB::transaction(function () use ($event, $data) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $venue = Venue::findOrFail($data['venue_id']);
            $courts = $this->editableCourts($event, $venue);
            $definitions = DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $venue->id)->get();
            if ($definitions->isNotEmpty()) $courts = $definitions->where('active', true)->values();
            if ($data['append_only'] ?? false) {
                $inactive = $definitions->where('active', false)->first(fn ($court) => \App\Domain\Draws\Services\ScheduleAvailability::courtKey((string) $court->label)
                    === \App\Domain\Draws\Services\ScheduleAvailability::courtKey(trim($data['label'])));
                if ($inactive) return response()->json(['message'=>'This court is inactive. Review it in venue setup before adding it to the day.'], 422);
                $existing = $courts->first(fn ($court) => \App\Domain\Draws\Services\ScheduleAvailability::courtKey((string) $court->label)
                    === \App\Domain\Draws\Services\ScheduleAvailability::courtKey(trim($data['label'])));
                if ($existing) return response()->json(['message'=>'This court is already available at this venue.', 'court'=>['label'=>(string) $existing->label], 'created'=>false]);
                if ($courts->count() >= 100) return response()->json(['message'=>'A venue can have at most 100 active courts in the day editor.'], 422);
            }
            $this->materializeCourts($event, $venue, $courts);
            $identity = ['event_id' => $event->id, 'venue_id' => $venue->id, 'label' => trim($data['label'])];
            DB::table('event_venue_courts')->insertOrIgnore($identity + [
                'ball_type' => $data['ball_type'] ?? null, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('event_venue_courts')->where($identity)->update([
                'ball_type' => $data['ball_type'] ?? null, 'active' => true, 'updated_at' => now(),
            ]);
            $this->syncCourtCount($event, $venue);
            return response()->json(['message' => 'Court saved.', 'court'=>['label'=>$identity['label']], 'created'=>true]);
        });
    }

    public function removeCourt(Request $request, Event $event, Venue $venue)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'], 'confirm_reset' => ['sometimes', 'boolean'],
            'correction_revision' => ['sometimes', 'string', 'size:64'],
            'review_only' => ['sometimes', 'boolean'], 'reset_age_keys' => ['sometimes', 'array', 'max:20'], 'reset_age_keys.*' => ['required', 'string', 'max:30'],
        ]);
        return $this->courtTransaction(function () use ($event, $venue, $data) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $courts = $this->editableCourts($event, $venue);
            if (! $courts->contains(fn ($court) => (string) $court->label === $data['label'])) {
                $alreadyRemoved = DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $venue->id)
                    ->where('label', $data['label'])->where('active', false)->exists();
                abort_unless($alreadyRemoved, 404);
                return response()->json(['message' => 'Court already removed from this event.']);
            }
            if ($courts->count() <= 1) return response()->json([
                'message' => 'Keep at least one court, or remove the venue from this event.',
            ], 422);
            $drawIds = $event->draws()->pluck('id');
            $before = $courts->pluck('label')->map(fn ($label) => (string) $label)->all();
            $after = array_values(array_diff($before, [$data['label']]));
            if ($conflict = app(\App\Services\Scheduling\VenueCourtCorrectionService::class)->prepare($event, $venue, $before, $after, $data)) {
                return response()->json(array_diff_key($conflict, ['status' => true]), $conflict['status']);
            }
            $this->materializeCourts($event, $venue, $courts);
            DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $venue->id)
                ->where('label', $data['label'])->update(['active' => false, 'updated_at' => now()]);
            DB::table('draw_venue_court_allocations')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)
                ->where('court_label', $data['label'])->delete();
            $this->syncCourtCount($event, $venue);
            return response()->json(['message' => 'Court removed from this event.']);
        });
    }

    private function courtTransaction(\Closure $action)
    {
        try {
            return DB::transaction($action);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function editableCourts(Event $event, Venue $venue): \Illuminate\Support\Collection
    {
        $eventCount = $event->venues()->whereKey($venue->id)->value('event_venues.num_courts');
        $drawCount = DB::table('draw_venues')->whereIn('draw_id', $event->draws()->pluck('id'))
            ->where('venue_id', $venue->id)->max('num_courts');
        abort_unless($eventCount !== null || $drawCount !== null, 404);
        $courts = DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $venue->id)
            ->where('active', true)->get();
        return $courts->isNotEmpty() ? $courts : collect(range(1, max(1, (int) $eventCount, (int) $drawCount)))
            ->map(fn ($label) => (object) ['label' => (string) $label, 'ball_type' => null]);
    }

    private function materializeCourts(Event $event, Venue $venue, \Illuminate\Support\Collection $courts): void
    {
        foreach ($courts as $court) DB::table('event_venue_courts')->insertOrIgnore([
            'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => $court->label,
            'ball_type' => $court->ball_type, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function syncCourtCount(Event $event, Venue $venue): void
    {
        $count = DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $venue->id)->where('active', true)->count();
        $event->venues()->syncWithoutDetaching([$venue->id => ['num_courts' => $count]]);
        DB::table('draw_venues')->whereIn('draw_id', $event->draws()->pluck('id'))->where('venue_id', $venue->id)
            ->update(['num_courts' => $count, 'updated_at' => now()]);
    }

    public function configureCourts(Request $request, Event $event, Venue $venue)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'courts' => ['required', 'integer', 'min:1', 'max:100'],
            'ball_type' => ['required', 'in:orange,green,yellow,red,standard'],
            'confirm_reset' => ['sometimes', 'boolean'], 'correction_revision' => ['sometimes', 'string', 'size:64'],
            'review_only' => ['sometimes', 'boolean'], 'reset_age_keys' => ['sometimes', 'array', 'max:20'], 'reset_age_keys.*' => ['required', 'string', 'max:30'],
        ]);
        return $this->courtTransaction(function () use ($event, $venue, $data) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $drawIds = $event->draws()->pluck('id');
            $belongs = $event->venues()->whereKey($venue->id)->exists()
                || DB::table('draw_venues')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)->exists();
            abort_unless($belongs, 404);

            $labels = array_map('strval', range(1, $data['courts']));
            $existing = $this->editableCourts($event, $venue)->pluck('label')->map(fn ($label) => (string) $label);
            $removed = $existing->diff($labels)->values();
            if ($conflict = app(\App\Services\Scheduling\VenueCourtCorrectionService::class)->prepare($event, $venue, $existing->all(), $labels, $data)) {
                return response()->json(array_diff_key($conflict, ['status' => true]), $conflict['status']);
            }

            if ($removed->isNotEmpty()) {
                DB::table('draw_venue_court_allocations')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)
                    ->whereIn('court_label', $removed)->delete();
                DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $venue->id)
                    ->whereIn('label', $removed)->delete();
            }
            foreach ($labels as $label) {
                DB::table('event_venue_courts')->updateOrInsert([
                    'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => $label,
                ], ['ball_type' => $data['ball_type'], 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            $event->venues()->syncWithoutDetaching([$venue->id => ['num_courts' => $data['courts']]]);
            DB::table('draw_venues')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)
                ->update(['num_courts' => $data['courts'], 'updated_at' => now()]);

            return response()->json(['message' => "{$venue->name} now has {$data['courts']} {$data['ball_type']} courts."]);
        });
    }

    public function updateAssignments(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        if ($request->boolean('setup_only') && $request->has('round_venue_setups')) {
            $data = $request->validate([
                'round_venue_setups' => ['required', 'array', 'min:1', 'max:2000'],
                'round_venue_setups.*.draw_id' => ['required', 'integer'], 'round_venue_setups.*.round' => ['required', 'integer', 'min:1'],
                'round_venue_setups.*.venue_ids' => ['required', 'array', 'min:1'], 'round_venue_setups.*.venue_ids.*' => ['required', 'integer'],
                'round_venue_setups.*.court_allocations' => ['required', 'array', 'min:1'],
                'round_venue_setups.*.court_allocations.*.venue_id' => ['required', 'integer'],
                'round_venue_setups.*.court_allocations.*.court_labels' => ['required', 'array', 'min:1'],
                'round_venue_setups.*.court_allocations.*.court_labels.*' => ['required', 'string', 'max:50'],
                'round_venue_setups.*.rank_venue_preferences' => ['present', 'array', 'max:50'],
                'round_venue_setups.*.rank_venue_preferences.*.min_rank' => ['required', 'integer', 'min:1', 'max:100'],
                'round_venue_setups.*.rank_venue_preferences.*.max_rank' => ['required', 'integer', 'min:1', 'max:100'],
                'round_venue_setups.*.rank_venue_preferences.*.venue_id' => ['required', 'integer'],
            ]);
            try {
                $rows = app(\App\Services\Scheduling\RoundVenueSetup::class)->save($event, $data['round_venue_setups'], $request->user()?->id);
                return response()->json(['message' => 'Selected round venue setup saved. Saved match times are unchanged.', 'round_venue_setups' => $rows]);
            } catch (\InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        }

        if ($request->boolean('setup_only')) {
            $storedSetup = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
            $request->merge(['schedule' => array_replace([
                'start' => \Carbon\Carbon::parse($event->start_date)->format('Y-m-d').'T08:00',
                'end' => \Carbon\Carbon::parse($event->start_date)->format('Y-m-d').'T18:00',
                'duration' => 60, 'wave_minutes' => 90, 'court_gap' => 5, 'player_rest' => 30, 'limit_daily_matches' => false,
                'draw_starts' => [], 'venue_starts' => [], 'reschedule_existing' => false,
            ], $storedSetup, array_intersect_key((array) $request->input('schedule', []), array_flip(['rank_venue_preferences', 'rank_preference_draw_ids'])))]);
        }
        $data = $request->validate([
            'setup_only' => ['sometimes', 'boolean'],
            'venues' => ['required', 'array', 'min:1'], 'venues.*.id' => ['required', 'integer', 'exists:venues,id'],
            'venues.*.courts' => ['required', 'integer', 'min:1', 'max:100'],
            'assignments' => ['required', 'array'], 'assignments.*.draw_id' => ['required', 'integer'],
            'assignments.*.venue_ids' => ['present', 'array'], 'assignments.*.venue_ids.*' => ['integer'],
            'assignments.*.court_allocations' => ['present', 'array'],
            'assignments.*.court_allocations.*.venue_id' => ['required', 'integer'],
            'assignments.*.court_allocations.*.court_labels' => ['required', 'array', 'min:1'],
            'assignments.*.court_allocations.*.court_labels.*' => ['string', 'max:50'],
            'schedule' => ['required', 'array'],
            'schedule.start' => ['required', 'date'],
            'schedule.end' => ['nullable', 'date', 'after:schedule.start'],
            'schedule.duration' => ['required', 'integer', 'min:15', 'max:480'],
            'schedule.wave_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'schedule.court_gap' => ['required', 'integer', 'min:0', 'max:120'],
            'schedule.player_rest' => ['required', 'integer', 'min:0', 'max:480'],
            'schedule.limit_daily_matches' => ['sometimes', 'boolean'],
            'schedule.draw_starts' => ['present', 'array'],
            'schedule.draw_starts.*.draw_id' => ['required', 'integer', 'distinct'],
            'schedule.draw_starts.*.start' => ['required', 'date'],
            'schedule.venue_starts' => ['present', 'array'],
            'schedule.venue_starts.*.venue_id' => ['required', 'integer', 'distinct'],
            'schedule.venue_starts.*.start' => ['required', 'date'],
            'schedule.reschedule_existing' => ['required', 'boolean'],
            'schedule.round_progression' => ['sometimes', 'in:team_ready,all_round'],
            'schedule.gender_waves' => ['sometimes', 'in:combined,boys_then_girls,girls_then_boys'],
            'schedule.gender_wave_release' => ['sometimes', 'in:whole_wave,court_ready'],
            'schedule.tie_allocation' => ['sometimes', 'in:balanced,complete_tie'],
            'schedule.draw_rounds' => ['sometimes', 'array', 'max:200'],
            'schedule.draw_rounds.*.draw_id' => ['required', 'integer', 'distinct'],
            'schedule.draw_rounds.*.rounds' => ['required', 'array', 'min:1', 'max:100'],
            'schedule.draw_rounds.*.rounds.*' => ['required', 'integer', 'min:1'],
            'schedule.rank_preference_draw_ids' => ['required_if:setup_only,true', 'array', 'max:200'],
            'schedule.rank_preference_draw_ids.*' => ['integer', 'distinct'],
            'schedule.rank_venue_preferences' => ['sometimes', 'array', 'max:50'],
            'schedule.rank_venue_preferences.*.draw_ids' => ['required', 'array', 'min:1', 'max:200'],
            'schedule.rank_venue_preferences.*.draw_ids.*' => ['integer'],
            'schedule.rank_venue_preferences.*.min_rank' => ['required', 'integer', 'min:1', 'max:100'],
            'schedule.rank_venue_preferences.*.max_rank' => ['required', 'integer', 'min:1', 'max:100'],
            'schedule.rank_venue_preferences.*.venue_id' => ['required', 'integer'],
            'schedule.cross_band_policy' => ['sometimes', 'in:highest_ranked,manual'],
        ]);
        $draws = $event->draws()->whereIn('id', collect($data['assignments'])->pluck('draw_id'))->get()->keyBy('id');
        if ($draws->count() !== count($data['assignments'])) abort(422, 'One or more draws do not belong to this event.');
        $eventDrawIds = $event->draws()->pluck('id')->map(fn ($id) => (int) $id);
        if (collect($data['schedule']['draw_starts'])->pluck('draw_id')->map(fn ($id) => (int) $id)->diff($eventDrawIds)->isNotEmpty()) {
            abort(422, 'An age-group start time does not belong to this event.');
        }
        $courtCounts = collect($data['venues'])->mapWithKeys(fn ($venue) => [(int) $venue['id'] => (int) $venue['courts']]);
        $allowedVenueIds = $event->venues()->pluck('venues.id')
            ->merge(DB::table('draw_venues')->whereIn('draw_id', $event->draws()->pluck('id'))->pluck('venue_id'))
            ->unique()->map(fn ($id) => (int) $id)->all();
        if (array_diff($courtCounts->keys()->all(), $allowedVenueIds)) abort(422, 'A venue does not belong to this event.');
        if (collect($data['schedule']['venue_starts'])->pluck('venue_id')->map(fn ($id) => (int) $id)->diff($allowedVenueIds)->isNotEmpty()) {
            abort(422, 'A venue start time does not belong to this event.');
        }

        $unscheduled = 0;
        $rankWarnings = [];
        try {
            DB::transaction(function () use ($data, $draws, $courtCounts, $event, $request, &$unscheduled, &$rankWarnings) {
                Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
                DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
                $draws = $event->draws()->whereIn('id', $draws->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($data['setup_only'] ?? false) {
                    $storedSetup = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
                    $rankScope = $data['schedule']['rank_preference_draw_ids'] ?? [];
                    if (! $rankScope || array_diff($rankScope, $draws->keys()->all())) {
                        throw new \InvalidArgumentException('Position bands must belong to the draws being assigned.');
                    }
                    $data['schedule'] = array_replace([
                        'start' => \Carbon\Carbon::parse($event->start_date)->format('Y-m-d').'T08:00',
                        'end' => \Carbon\Carbon::parse($event->start_date)->format('Y-m-d').'T18:00',
                        'duration' => 60, 'wave_minutes' => 90, 'court_gap' => 5, 'player_rest' => 30, 'limit_daily_matches' => false,
                        'draw_starts' => [], 'venue_starts' => [], 'reschedule_existing' => false,
                    ], $storedSetup, array_intersect_key($data['schedule'], array_flip(['rank_venue_preferences', 'rank_preference_draw_ids'])));
                    $data['schedule']['draw_rounds'] = collect($storedSetup['draw_rounds'] ?? [])
                        ->filter(fn ($row) => $draws->has((int) ($row['draw_id'] ?? 0)))->values()->all();
                } else {
                    $data['schedule']['draw_rounds'] = app(EventVenueScheduleService::class)->normalizeDrawRounds($draws->values(), $data['schedule']['draw_rounds'] ?? []);
                }
                foreach ($data['assignments'] as $assignment) {
                    $draw = $draws[(int) $assignment['draw_id']];
                    if ($draw->locked) {
                        throw new \InvalidArgumentException("{$draw->drawName} is locked and its venue allocation cannot change.");
                    }
                    $venueIds = array_map('intval', $assignment['venue_ids']);
                    $allocationVenueIds = collect($assignment['court_allocations'])->pluck('venue_id')->map(fn ($venueId) => (int) $venueId);
                    if ($allocationVenueIds->count() !== $allocationVenueIds->unique()->count()) {
                        throw new \InvalidArgumentException('A venue can have only one court allocation per age group.');
                    }
                    $allocationVenueIds = $allocationVenueIds->unique()->sort()->values()->all();
                    if ($allocationVenueIds !== collect($venueIds)->unique()->sort()->values()->all()) {
                        throw new \InvalidArgumentException('Choose at least one physical court for every selected venue.');
                    }
                    if (array_diff($venueIds, $courtCounts->keys()->all())) {
                        throw new \InvalidArgumentException('A selected venue is not available for this event.');
                    }
                    $before = $draw->venues()->pluck('venues.id')->map(fn ($id) => (int) $id)->all();
                    $removed = array_diff($before, $venueIds);
                    Fixture::where('draw_id', $draw->id)->orderBy('id')->lockForUpdate()->get();
                    TeamFixture::where('draw_id', $draw->id)->orderBy('id')->lockForUpdate()->get();
                    $affected = OrderOfPlay::whereHas('fixture', fn ($query) => $query->where('draw_id', $draw->id))
                        ->whereIn('venue_id', $removed);
                    if ((clone $affected)->whereHas('fixture', fn ($query) => $query->whereHas('fixtureResults')->orWhere('match_status', '!=', 0))->exists()) {
                        throw new \InvalidArgumentException("{$draw->drawName} has played matches at a venue being removed.");
                    }
                    $affectedFixtureIds = (clone $affected)->pluck('fixture_id');
                    $teamAffected = TeamFixture::where('draw_id', $draw->id)->whereIn('venue_id', $removed);
                    if ((clone $teamAffected)->where(fn ($query) => $query->whereHas('fixtureResults')
                        ->orWhere('match_status', '!=', 0)->orWhereHas('teamTie', fn ($ties) => $ties->where('status', 'completed')))->exists()) {
                        throw new \InvalidArgumentException("{$draw->drawName} has play at a venue being removed.");
                    }
                    $teamAffectedIds = (clone $teamAffected)->pluck('id');
                    if (($data['setup_only'] ?? false) && ($affectedFixtureIds->isNotEmpty() || $teamAffectedIds->isNotEmpty())) {
                        throw new \InvalidArgumentException('This venue has saved matches. Move those matches before removing its assignment.');
                    }
                    $removalError = app(EventVenueScheduleService::class)->removalError($event, $affectedFixtureIds->all(), $teamAffectedIds->all());
                    if ($removalError) throw new \InvalidArgumentException($removalError);
                    $unscheduled += $affectedFixtureIds->count();
                    $unscheduled += $teamAffectedIds->count();
                    $affected->delete();
                    Fixture::whereIn('id', $affectedFixtureIds)->update(['scheduled' => 0]);
                    TeamFixture::whereIn('id', $teamAffectedIds)->update(['scheduled_at' => null, 'scheduled' => 0,
                        'venue_id' => null, 'court_label' => null, 'duration_min' => null, 'clash_flag' => false]);
                    $draw->venues()->sync(collect($venueIds)->mapWithKeys(fn ($venueId) => [
                        $venueId => ['num_courts' => $courtCounts[$venueId]],
                    ])->all());
                    DB::table('draw_venue_court_allocations')->where('draw_id', $draw->id)->delete();
                    foreach ($assignment['court_allocations'] as $allocation) {
                        $venueId = (int) $allocation['venue_id'];
                        if (! in_array($venueId, $venueIds, true)) continue;
                        if (count($allocation['court_labels']) !== count(array_unique($allocation['court_labels']))) {
                            throw new \InvalidArgumentException('The same physical court cannot be selected twice for an age group.');
                        }
                        $validLabels = DB::table('event_venue_courts')->where('event_id', $draw->event_id)
                            ->where('venue_id', $venueId)->where('active', true)->pluck('label')->map(fn ($label) => (string) $label)->all();
                        if (array_diff($allocation['court_labels'], $validLabels)) {
                            throw new \InvalidArgumentException('A selected court is not active at this venue.');
                        }
                        $occupied = OrderOfPlay::whereHas('fixture', fn ($query) => $query->where('draw_id', $draw->id))
                            ->where('venue_id', $venueId)->whereNotNull('time')->pluck('court');
                        $occupied = $occupied->merge(TeamFixture::where('draw_id', $draw->id)->where('venue_id', $venueId)
                            ->whereNotNull('scheduled_at')->pluck('court_label'))
                            ->map(fn ($label) => \App\Domain\Draws\Services\ScheduleAvailability::courtKey((string) $label));
                        $permitted = collect($allocation['court_labels'])->map(fn ($label) => \App\Domain\Draws\Services\ScheduleAvailability::courtKey((string) $label));
                        if ($occupied->diff($permitted)->isNotEmpty()) {
                            throw new \InvalidArgumentException('Move the scheduled matches before removing their court allocation.');
                        }
                        foreach (array_unique($allocation['court_labels']) as $label) {
                            DB::table('draw_venue_court_allocations')->insert([
                                'draw_id' => $draw->id, 'venue_id' => $venueId, 'court_label' => $label,
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                    }
                    \App\Models\DrawAuditLog::record($draw->id, 'venue_allocation_updated', null, [
                        'before' => $before, 'after' => $venueIds, 'unscheduled_matches' => $affectedFixtureIds->count(),
                    ]);
                }
                $stored = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
                $data['schedule']['draw_rounds'] = array_merge(collect($stored['draw_rounds'] ?? [])
                    ->filter(fn ($row) => ! $draws->has((int) ($row['draw_id'] ?? 0)))->values()->all(), $data['schedule']['draw_rounds']);
                $retainedRules = app(\App\Services\Scheduling\RankVenuePreferences::class)->assigned($event, $stored['rank_venue_preferences'] ?? []);
                if (collect($retainedRules)->sum(fn ($rule) => count($rule['draw_ids'])) < collect($stored['rank_venue_preferences'] ?? [])->sum(fn ($rule) => count($rule['draw_ids']))) {
                    $rankWarnings[] = 'Roster rank venue preferences for removed venue assignments were cleared. Other draws keep their preferences.';
                }
                if (array_key_exists('rank_venue_preferences', $data['schedule'])) {
                    $scope = array_map('intval', $data['schedule']['rank_preference_draw_ids'] ?? collect($data['schedule']['rank_venue_preferences'])->flatMap(fn ($rule) => $rule['draw_ids'])->unique()->all());
                    if (array_diff($scope, $event->draws()->pluck('id')->all())) throw new \InvalidArgumentException('Rank preference draws must belong to this event.');
                    if ($event->draws()->whereIn('id', $scope)->get()->contains(fn ($draw) => ! $draw->isTeamDraw())) throw new \InvalidArgumentException('Roster rank preference scope must contain team draws only.');
                    $newRules = app(\App\Services\Scheduling\RankVenuePreferences::class)->normalize($event, $data['schedule']['rank_venue_preferences']);
                    if (array_diff(collect($newRules)->flatMap(fn ($rule) => $rule['draw_ids'])->all(), $scope)) throw new \InvalidArgumentException('Rank preferences must use the selected preference draws.');
                    $data['schedule']['rank_venue_preferences'] = array_merge(
                        app(\App\Services\Scheduling\RankVenuePreferences::class)->retained($retainedRules, $scope), $newRules);
                } else {
                    $data['schedule']['rank_venue_preferences'] = $retainedRules;
                }
                $data['schedule']['cross_band_policy'] ??= $stored['cross_band_policy'] ?? 'highest_ranked';
                $data['schedule']['limit_daily_matches'] = ! empty($stored['limit_daily_matches']) || ! empty($data['schedule']['limit_daily_matches']);
                $data['schedule']['round_venue_setups'] = $stored['round_venue_setups'] ?? [];
                foreach (['programme', 'programme_settings'] as $key) {
                    if (isset($stored[$key])) $data['schedule'][$key] = $stored[$key];
                }
                unset($data['schedule']['rank_preference_draw_ids']);
                DB::table('event_venue_schedule_drafts')->updateOrInsert(
                    ['event_id' => $event->id],
                    [
                        'options' => json_encode($data['schedule'], JSON_THROW_ON_ERROR),
                        'updated_by' => $request->user()?->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => (($data['setup_only'] ?? false) ? 'Venues, courts and position bands saved.' : 'Court allocations and timing saved.').($rankWarnings ? ' '.implode(' ', $rankWarnings) : ''), 'warnings' => $rankWarnings, 'unscheduled' => $unscheduled]);
    }

    public function preview(Request $request, Event $event, EventVenueScheduleService $scheduler,
        RoundRobinPlayoffScheduleService $playoffs)
    {
        $this->authorize('event.manage', $event);
        try {
            $options = $this->validatedOptions($request);
            if ($request->has('preview_move')) {
                $data = $request->validate([
                    'revision'=>['required','string','size:64'], 'preview_move'=>['required','array:fixture_key,scheduled_at,venue_id,court'],
                    'preview_move.fixture_key'=>['required','regex:/^(individual|team):[1-9][0-9]*$/'],
                    'preview_move.scheduled_at'=>['required','date'], 'preview_move.venue_id'=>['required','integer'], 'preview_move.court'=>['required','string','max:50'],
                ]);
                $current = $scheduler->preview($event->fresh(), $options);
                if (! hash_equals($current['revision'], $data['revision'])) throw new \InvalidArgumentException('The preview changed. Generate a new preview before moving this match.');
                $moving = collect(array_merge($current['matches'], $current['unscheduled']))->firstWhere('fixture_key', $data['preview_move']['fixture_key']);
                if (! $moving) throw new \InvalidArgumentException('Choose an unsaved match in this preview.');
                $preserved = $scheduler->preservePreviewOptions($options, $current, $data['preview_move']);
                try { return response()->json($scheduler->preview($event->fresh(), $preserved)); }
                catch (\InvalidArgumentException $exception) {
                    return response()->json(['message' => 'Match '.$moving['match'].' was not moved. '.$exception->getMessage(),
                        'move_conflict' => app(\App\Services\Scheduling\ScheduleMoveConflictService::class)
                            ->report($event, $options, $current, $data['preview_move'], $exception->getMessage())], 422);
                }
            }
            if (empty($options['programme']) && empty($options['day_batch'])) $playoffs->prepareEvent($event, $options['draw_ids'] ?? []);

            return response()->json($scheduler->preview($event->fresh(), $options));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function apply(Request $request, Event $event, EventVenueScheduleService $scheduler,
        RoundRobinPlayoffScheduleService $playoffs)
    {
        $this->authorize('event.manage', $event);
        $request->validate(['revision' => ['required', 'string', 'size:64']]);
        try {
            $options = $this->validatedOptions($request);
            if (empty($options['programme']) && empty($options['day_batch'])) $playoffs->prepareEvent($event, $options['draw_ids'] ?? []);

            return response()->json($scheduler->apply($event->fresh(), $options, (string) $request->string('revision')));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function unapply(Request $request, Event $event, EventVenueScheduleService $scheduler)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'draw_id' => ['nullable', 'integer'],
            'venue_id' => ['nullable', 'integer'],
            'fixture_id' => ['nullable', 'integer'],
            'fixture_kind' => ['sometimes', 'in:individual,team'],
        ]);
        $drawId = isset($data['draw_id']) ? (int) $data['draw_id'] : null;
        $venueId = isset($data['venue_id']) ? (int) $data['venue_id'] : null;
        $fixtureId = isset($data['fixture_id']) ? (int) $data['fixture_id'] : null;
        if (collect([$drawId, $venueId, $fixtureId])->filter(fn ($id) => $id !== null)->count() !== 1) {
            return response()->json(['message' => 'Choose one match, one draw, or one venue to return to planning.'], 422);
        }
        try {
            if ($fixtureId && ($data['fixture_kind'] ?? 'individual') === 'team') {
                return response()->json(app(UnifiedTeamScheduleService::class)->unapply($event, null, null, $fixtureId));
            }
            return response()->json($scheduler->unapply($event, $drawId, $venueId, $fixtureId));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function saveTimingSettings(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'duration' => ['required', 'integer', 'between:15,480'],
            'player_rest' => ['required', 'integer', 'between:0,480'],
            'court_gap' => ['required', 'integer', 'between:0,120'],
            'limit_daily_matches' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($event, $data) {
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $draft = DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->lockForUpdate()->first();
            $stored = json_decode((string) ($draft->options ?? ''), true) ?: [];
            DB::table('event_venue_schedule_drafts')->updateOrInsert(['event_id' => $event->id], [
                'options' => json_encode(array_replace($stored, $data), JSON_THROW_ON_ERROR),
                'updated_by' => auth()->id(), 'created_at' => $draft->created_at ?? now(), 'updated_at' => now(),
            ]);
        });
        return response()->json(['message' => 'Timing settings saved. Existing match times are unchanged.']);
    }

    public function assignFixture(Request $request, Event $event, ScheduleConflictService $conflicts,
        ScheduleEngine $scheduleEngine)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'fixture_id' => ['required', 'integer'],
            'fixture_kind' => ['sometimes', 'in:individual,team'],
            'scheduled_at' => ['required', 'date'],
            'venue_id' => ['required', 'integer'],
            'court' => ['required', 'string', 'max:50', 'regex:/\S/'],
            'duration' => ['required', 'integer', 'min:1', 'max:480'],
            'court_gap' => ['required', 'integer', 'min:0', 'max:120'],
            'player_rest' => ['required', 'integer', 'min:0', 'max:480'],
            'limit_daily_matches' => ['sometimes', 'boolean'],
            'round_progression' => ['sometimes', 'in:team_ready,all_round'],
        ]);

        if (($data['fixture_kind'] ?? 'individual') === 'team' && $data['duration'] < 15) {
            return response()->json(['message' => 'Team match duration must be at least 15 minutes.'], 422);
        }

        if ($request->has('preview_context')) {
            $context = $request->validate(['preview_context'=>['required','array'], 'preview_revision'=>['required','string','size:64']]);
            $options = $this->validatedOptions(Request::create('/', 'POST', $context['preview_context']));
            $moveConflictContext = null;
            try {
                return DB::transaction(function () use ($request, $event, $data, $options, $context, $conflicts, $scheduleEngine, &$moveConflictContext) {
                    Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
                    DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
                    $scheduler = app(EventVenueScheduleService::class);
                    $current = $scheduler->preview($event->fresh(), $options);
                    if (! hash_equals($current['revision'], $context['preview_revision'])) throw new \InvalidArgumentException('The preview changed. Generate a new preview before moving this match.');
                    $key = ($data['fixture_kind'] ?? 'individual').':'.$data['fixture_id'];
                    $saved = collect($current['existing_matches'])->firstWhere('fixture_key', $key);
                    if (! $saved || empty($saved['editable'])) throw new \InvalidArgumentException('Choose an editable saved match in this preview.');
                    $moveConflictContext = ['preview' => $current, 'move' => ['fixture_key' => $key, 'scheduled_at' => $data['scheduled_at'], 'venue_id' => (int) $data['venue_id'], 'court' => $data['court']]];
                    $preserved = $scheduler->preservePreviewOptions($options, $current);
                    if (! empty($options['day_batch'])) {
                        $movedDay = \Carbon\Carbon::parse($data['scheduled_at'])->toDateString();
                        if ($movedDay !== $options['full_day_scope']) {
                            $selected = $options['selected_fixture_keys'];
                            $retained = collect(array_merge($current['matches'], $current['unscheduled'], $current['existing_matches']))
                                ->filter(fn ($row) => $row['fixture_key'] !== $key && in_array($row['fixture_key'], $selected, true))->unique('fixture_key');
                            if ($retained->isEmpty()) {
                                $retained = collect([array_replace($saved, ['scheduled_at'=>$data['scheduled_at']])]);
                                $preserved['full_day_scope'] = $movedDay;
                                $preserved['start'] = $movedDay.' 00:00:00'; $preserved['end'] = $movedDay.' 23:59:00';
                                unset($preserved['full_day_venue_windows'], $preserved['full_day_court_starts'], $preserved['full_day_destination_pools'], $preserved['full_day_tie_destinations']);
                            }
                            $preserved['selected_fixture_keys'] = $retained->pluck('fixture_key')->values()->all();
                            $preserved['draw_ids'] = $retained->pluck('draw_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
                            $preserved['draw_order'] = array_values(array_intersect($options['draw_order'], $preserved['draw_ids']));
                            $preserved['draw_rounds'] = $retained->groupBy('draw_id')->map(fn ($rows, $id) => ['draw_id'=>(int) $id,'rounds'=>$rows->pluck('round')->map(fn ($round)=>(int) $round)->unique()->values()->all()])->values()->all();
                        } else {
                            $at = \Carbon\Carbon::parse($data['scheduled_at']);
                            if ($at->lt(\Carbon\Carbon::parse($preserved['start']))) $preserved['start'] = $movedDay.' 00:00:00';
                            if ($at->copy()->addMinutes($data['duration'])->gt(\Carbon\Carbon::parse($preserved['end']))) $preserved['end'] = $movedDay.' 23:59:00';
                        }
                    }
                    $inner = Request::create($request->url(), 'POST', $data);
                    $inner->setUserResolver(fn () => $request->user());
                    $response = $this->assignFixture($inner, $event, $conflicts, $scheduleEngine);
                    $result = $response->getData(true);
                    if ($response->getStatusCode() >= 400) throw new \InvalidArgumentException($result['message'] ?? 'This match could not be moved.');
                    // Validate every unchanged suggestion before the outer transaction can commit.
                    $result['preview'] = $scheduler->preview($event->fresh(), $preserved);
                    $result['preview_options'] = $preserved;
                    return response()->json($result);
                });
            } catch (\InvalidArgumentException $exception) {
                $result = ['message' => $exception->getMessage()];
                if ($moveConflictContext) $result['move_conflict'] = app(\App\Services\Scheduling\ScheduleMoveConflictService::class)
                    ->report($event, array_replace($options, ['duration' => $data['duration'], 'player_rest' => $data['player_rest'], 'court_gap' => $data['court_gap']]),
                        $moveConflictContext['preview'], $moveConflictContext['move'], $exception->getMessage(), false);
                return response()->json($result, 422);
            }
        }

        try {
            if (($data['fixture_kind'] ?? 'individual') === 'team') {
                $target = TeamFixture::whereHas('draw', fn ($query) => $query->where('event_id', $event->id))->findOrFail($data['fixture_id']);
                $warnings = app(UnifiedTeamScheduleService::class)->warnings($target, $data);
                $fixture = app(UnifiedTeamScheduleService::class)->assign($event, $data);
                return response()->json([
                    'warnings' => $warnings,
                    'message' => 'Rubber assigned to '.$fixture->venue->name.' Â· Court '.$fixture->court_label.' Â· '.$fixture->scheduled_at->format('d M Y H:i').'.',
                    'assignment' => ['fixture_id' => $fixture->id, 'fixture_kind' => 'team', 'fixture_key' => 'team:'.$fixture->id,
                        'venue_id' => (int) $fixture->venue_id, 'court' => $fixture->court_label,
                        'scheduled_at' => $fixture->scheduled_at->format('Y-m-d H:i:s')],
                ]);
            }
            $slot = DB::transaction(function () use ($data, $event, $conflicts, $scheduleEngine) {
                Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
                DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
                $fixture = Fixture::with(['draw.venues', 'draw.flexibleMonrad', 'draw.settings', 'fixtureResults', 'orderOfPlay'])
                    ->whereHas('draw', fn ($draws) => $draws->where('event_id', $event->id))
                    ->lockForUpdate()->find($data['fixture_id']);
                if (! $fixture) throw new \InvalidArgumentException('This match does not belong to the event.');
                $timing = app(MatchTimingService::class)->forFixture($fixture, (int) $data['duration'], (int) $data['court_gap']);
                $data['duration'] = $timing['duration_minutes'];
                $data['court_gap'] = $timing['court_gap_minutes'];
                $venueId = (int) $data['venue_id'];
                $court = trim((string) $data['court']);
                DB::table('venues')->where('id', $venueId)->lockForUpdate()->get();
                $error = $this->manualAssignmentError($event, $fixture, $venueId, $court, $data, $conflicts);
                if ($error) throw new \InvalidArgumentException($error);

                $slot = $scheduleEngine->saveFixture($fixture->draw, $fixture->id, $data['scheduled_at'],
                    $venueId, $court, (int) $data['duration'], true, true, (int) $data['player_rest'], (int) $data['court_gap']);
                $slot->update(['gap_minutes' => (int) $data['court_gap']]);
                DrawAuditLog::record($fixture->draw_id, 'event_venue_match_manually_scheduled', null, [
                    'event_id' => $event->id, 'fixture_id' => $fixture->id, 'venue_id' => $venueId,
                    'court' => $court, 'scheduled_at' => $slot->time,
                ]);

                return $slot->fresh()->load('venue');
            });
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Match assigned to '.$slot->venue->name.' Â· Court '.$slot->court.' Â· '.
                \Carbon\Carbon::parse($slot->time)->format('d M Y H:i').'.',
            'assignment' => ['fixture_id' => (int) $slot->fixture_id, 'venue_id' => (int) $slot->venue_id,
                'court' => (string) $slot->court, 'scheduled_at' => \Carbon\Carbon::parse($slot->time)->format('Y-m-d H:i:s')],
        ]);
    }

    public function manualOptions(Request $request, Event $event, ScheduleConflictService $conflicts)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'fixture_ids' => ['required', 'array', 'max:200'],
            'fixture_ids.*' => ['required', 'integer', 'distinct'],
            'fixture_kind' => ['sometimes', 'in:individual,team'],
            'scheduled_at' => ['required', 'date'],
            'venue_id' => ['required', 'integer'],
            'court' => ['required', 'string', 'max:50', 'regex:/\S/'],
            'duration' => ['required', 'integer', 'min:1', 'max:480'],
            'court_gap' => ['required', 'integer', 'min:0', 'max:120'],
            'player_rest' => ['required', 'integer', 'min:0', 'max:480'],
            'limit_daily_matches' => ['sometimes', 'boolean'],
            'round_progression' => ['sometimes', 'in:team_ready,all_round'],
        ]);

        if (($data['fixture_kind'] ?? 'individual') === 'team') {
            if ($data['duration'] < 15) return response()->json(['message' => 'Team match duration must be at least 15 minutes.'], 422);
            $fixtures = TeamFixture::whereIn('id', $data['fixture_ids'])
                ->whereHas('draw', fn ($draws) => $draws->where('event_id', $event->id))->get()->keyBy('id');
            $eligible = [];
            $blocked = [];
            foreach ($data['fixture_ids'] as $id) {
                $fixture = $fixtures->get((int) $id);
                $error = $fixture ? app(UnifiedTeamScheduleService::class)->manualError($event, $fixture, $data)
                    : 'This rubber is not available in the selected event.';
                if ($error) $blocked[(string) $id] = $error;
                else $eligible[] = (int) $id;
            }
            return response()->json(['eligible_fixture_ids' => $eligible, 'blocked' => $blocked]);
        }

        $fixtures = Fixture::with(['draw.venues', 'draw.flexibleMonrad', 'draw.settings', 'fixtureResults', 'orderOfPlay'])
            ->whereIn('id', $data['fixture_ids'])
            ->whereHas('draw', fn ($draws) => $draws->where('event_id', $event->id))
            ->get()->keyBy('id');
        $eligible = [];
        $blocked = [];
        foreach ($data['fixture_ids'] as $fixtureId) {
            $fixture = $fixtures->get((int) $fixtureId);
            $error = $fixture
                ? $this->manualAssignmentError($event, $fixture, (int) $data['venue_id'], trim((string) $data['court']), $data, $conflicts)
                : 'This match is not available in the selected event.';
            if ($error) $blocked[(string) $fixtureId] = $error;
            else $eligible[] = (int) $fixtureId;
        }

        return response()->json(['eligible_fixture_ids' => $eligible, 'blocked' => $blocked]);
    }

    private function manualAssignmentError(Event $event, Fixture $fixture, int $venueId, string $court,
        array $data, ScheduleConflictService $conflicts): ?string
    {
        if ($fixture->draw->settings?->score_format !== 'timed' && (int) $data['duration'] < 15) {
            return 'Match duration must be at least 15 minutes.';
        }
        if ($fixture->draw->locked) {
            return 'Unlock the draw before manually scheduling this match.';
        }
        if ($fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0) return 'A match with play cannot be moved to another slot.';

        if ($error = app(\App\Services\Scheduling\RoundVenueSetup::class)->individualCourtError($fixture, $venueId, $court)) return $error;

        $stored = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        if (($stored['limit_daily_matches'] ?? false) || ($data['limit_daily_matches'] ?? false)) {
            $error = \App\Services\Scheduling\DailyMatchLimit::load($event)->error('individual:'.$fixture->id, $data['scheduled_at']);
            if ($error) return $error;
        }

        $timing = app(MatchTimingService::class)->forFixture($fixture, (int) $data['duration'], (int) $data['court_gap']);
        return $conflicts->conflict($fixture->draw, $fixture, $venueId, $court,
            $data['scheduled_at'], $timing['duration_minutes'], (int) $data['player_rest'],
            $timing['court_gap_minutes'], true);
    }

    private function validatedOptions(Request $request): array
    {
        return $request->validate([
            'preview_moves'=>['sometimes','array','max:2000'],
            'preview_moves.*'=>['required','array:fixture_key,scheduled_at,venue_id,court'],
            'preview_moves.*.fixture_key'=>['required','distinct','regex:/^(individual|team):[1-9][0-9]*$/'],
            'preview_moves.*.scheduled_at'=>['required','date'], 'preview_moves.*.venue_id'=>['required','integer'], 'preview_moves.*.court'=>['required','string','max:50'],
            'preview_frozen_keys'=>['sometimes','array','max:2000'],
            'preview_frozen_keys.*'=>['required','string','distinct','regex:/^(individual|team):[1-9][0-9]*$/'],
            'day_batch' => ['sometimes', 'boolean'],
            'draw_order_mode' => ['sometimes', 'in:mixed,sequential'],
            'draw_order' => ['sometimes', 'array', 'min:1', 'max:200'],
            'draw_order.*' => ['required', 'integer', 'distinct'],
            'selected_fixture_keys' => ['sometimes', 'array', 'min:1', 'max:2000'],
            'selected_fixture_keys.*' => ['required', 'string', 'distinct', 'regex:/^(individual|team):[1-9][0-9]*$/'],
            'full_day_scope' => ['sometimes', 'date_format:Y-m-d'],
            'day_court_labels' => ['sometimes', 'array', 'max:200'],
            'day_court_labels.*' => ['required', 'array', 'min:1', 'max:100'],
            'day_court_labels.*.*' => ['required', 'string', 'max:50'],
            'day_venue_groups' => ['sometimes', 'array', 'max:200'],
            'day_venue_groups.*' => ['required', 'array:A,B'],
            'day_venue_groups.*.*' => ['required', 'array', 'min:1', 'max:100'],
            'day_venue_groups.*.*.*' => ['required', 'string', 'max:50'],
            'full_day_venue_windows' => ['sometimes', 'array', 'max:200'],
            'full_day_venue_windows.*' => ['required', 'array:start,end'],
            'full_day_venue_windows.*.start' => ['required', 'date'],
            'full_day_venue_windows.*.end' => ['required', 'date'],
            'full_day_court_starts' => ['sometimes', 'array', 'max:200'],
            'full_day_court_starts.*' => ['required', 'array', 'max:100'],
            'full_day_court_starts.*.*' => ['required', 'array:court,start,end'],
            'full_day_court_starts.*.*.court' => ['required', 'string', 'max:50'],
            'full_day_court_starts.*.*.start' => ['required', 'date'],
            'full_day_court_starts.*.*.end' => ['required', 'date'],
            'reschedule_existing' => ['sometimes', 'boolean'],
            'start' => ['required', 'date'], 'end' => ['nullable', 'date', 'after:start'],
            'programme' => ['sometimes', 'array:days,rounds,matches'],
            'programme.matches' => ['sometimes', 'array', 'max:2000'],
            'programme.matches.*' => ['array:draw_id,fixture_id,day,sequence'],
            'programme.matches.*.draw_id' => ['required', 'integer'],
            'programme.matches.*.fixture_id' => ['required', 'integer', 'distinct'],
            'programme.matches.*.day' => ['required', 'integer', 'min:1'],
            'programme.matches.*.sequence' => ['required', 'integer', 'between:1,100'],
            'programme.days' => ['required_with:programme', 'array', 'min:1'],
            'programme.days.*.start' => ['required', 'date'],
            'programme.days.*.end' => ['required', 'date'],
            'programme.days.*.break_start' => ['nullable', 'date'],
            'programme.days.*.break_end' => ['nullable', 'date'],
            'programme.rounds' => ['required_with:programme', 'array', 'min:1', 'max:2000'],
            'programme.rounds.*.draw_id' => ['required', 'integer'],
            'programme.rounds.*.round' => ['required', 'integer', 'min:1'],
            'programme.rounds.*.day' => ['required', 'integer', 'min:1'],
            'programme.rounds.*.sequence' => ['required', 'integer', 'between:1,100'],
            'duration' => ['required', 'integer', 'min:15', 'max:480'],
            'wave_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'court_gap' => ['required', 'integer', 'min:0', 'max:120'],
            'player_rest' => ['required', 'integer', 'min:0', 'max:480'],
            'limit_daily_matches' => ['sometimes', 'boolean'],
            'draw_ids' => ['nullable', 'array'], 'draw_ids.*' => ['integer'],
            'venue_ids' => ['nullable', 'array'], 'venue_ids.*' => ['integer'],
            'replan_venue_ids' => ['nullable', 'array'], 'replan_venue_ids.*' => ['integer'],
            'allow_partial' => ['sometimes', 'boolean'],
            'apply_venue_ids' => ['nullable', 'array'], 'apply_venue_ids.*' => ['integer'],
            'draw_starts' => ['nullable', 'array'],
            'draw_starts.*.draw_id' => ['required', 'integer'],
            'draw_starts.*.start' => ['nullable', 'date'],
            'venue_starts' => ['nullable', 'array'],
            'venue_starts.*.venue_id' => ['required', 'integer'],
            'venue_starts.*.start' => ['nullable', 'date'],
            'round_progression' => ['sometimes', 'in:team_ready,all_round'],
            'gender_waves' => ['sometimes', 'in:combined,boys_then_girls,girls_then_boys'],
            'programme.days.*.gender_waves' => ['sometimes', 'in:combined,boys_then_girls,girls_then_boys'],
            'gender_wave_release' => ['sometimes', 'in:whole_wave,court_ready'],
            'tie_allocation' => ['sometimes', 'in:balanced,complete_tie'],
            'draw_rounds' => ['sometimes', 'array', 'max:200'],
            'draw_rounds.*.draw_id' => ['required', 'integer', 'distinct'],
            'draw_rounds.*.rounds' => ['required', 'array', 'min:1', 'max:100'],
            'draw_rounds.*.rounds.*' => ['required', 'integer', 'min:1'],
            'rank_venue_preferences' => ['sometimes', 'array', 'max:50'],
            'rank_venue_preferences.*.draw_ids' => ['required', 'array', 'min:1', 'max:200'],
            'rank_venue_preferences.*.draw_ids.*' => ['integer'],
            'rank_venue_preferences.*.min_rank' => ['required', 'integer', 'min:1', 'max:100'],
            'rank_venue_preferences.*.max_rank' => ['required', 'integer', 'min:1', 'max:100'],
            'rank_venue_preferences.*.venue_id' => ['required', 'integer'],
            'cross_band_policy' => ['sometimes', 'in:highest_ranked,manual'],
        ]);
    }
}
