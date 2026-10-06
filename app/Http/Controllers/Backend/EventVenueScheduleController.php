<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Domain\Draws\Services\ScheduleConflictService;
use App\Models\{Draw, DrawAuditLog, Event, Fixture, OrderOfPlay, TeamFixture, TeamTie, Venue};
use App\Services\EventAnnouncementService;
use App\Services\ScheduleEngine;
use App\Services\Scheduling\EventVenueScheduleService;
use App\Services\Scheduling\RoundRobinPlayoffScheduleService;
use App\Services\Scheduling\UnifiedTeamScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EventVenueScheduleController extends Controller
{
    public function index(Request $request, Event $event, EventAnnouncementService $announcements)
    {
        $this->authorize('event.manage', $event);
        $event->load('draws.venues', 'draws.draw_types');
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
        $selectionSupplied = $request->has('draw_ids');
        $requestedDrawIds = collect($request->validate([
            'draw_ids' => ['sometimes', 'array', 'max:200'],
            'draw_ids.*' => ['required', 'integer', 'distinct'],
        ])['draw_ids'] ?? [])->map(fn ($id) => (int) $id);
        if ($selectionSupplied && $requestedDrawIds->diff($eventDraws->pluck('id')->map(fn ($id) => (int) $id))->isNotEmpty()) {
            abort(422, 'One or more selected draws do not belong to this event.');
        }
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

        $draws = $eventDraws->map(function ($draw) use ($courtAllocations, $scheduledCounts, $selectionSupplied, $requestedDrawIds, $availableRounds) {
            $allocations = [];
            foreach ($draw->venues as $venue) {
                $allocations[$venue->id] = ($courtAllocations[$draw->id.'|'.$venue->id] ?? collect())
                    ->pluck('court_label')->map(fn ($label) => (string) $label)->all();
            }
            return ['id' => $draw->id, 'name' => $draw->drawName,
                'programme_age' => app(\App\Services\Scheduling\ScheduleProgramme::class)->age($draw),
                'rubber_code' => $draw->team_draw_selection['rubber_code'] ?? null,
                'doubles_pair_count' => $this->standardPairCount($draw, 'doubles'),
                'mixed_pair_count' => $this->standardPairCount($draw, 'mixed_doubles'),
                'venues' => $draw->venues->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'court_allocations' => $allocations,
                'applied_match_count' => (int) ($scheduledCounts[$draw->id] ?? 0),
                'locked' => (bool) $draw->locked, 'published' => (bool) $draw->published,
                'is_team' => $draw->isTeamDraw(),
                'rounds' => $availableRounds[$draw->id] ?? [],
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
            'duration' => 75,
            'wave_minutes' => 90,
            'court_gap' => 5,
            'player_rest' => 60,
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
            'event', 'draws', 'venues', 'allVenues', 'announcementDraft', 'scheduleDraft', 'adaptationNotices'
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
        $rows = $rows->take(1000)->values(); $retained = $retained->take(1000)->values();
        $drawIds = $draws->pluck('id');
        $unscheduledCount = Fixture::whereIn('draw_id', $drawIds)->when(! empty($scope['draw_id']), fn ($q) => $q->where('draw_id', $scope['draw_id']))
            ->where('match_status', 0)->whereDoesntHave('fixtureResults')->whereDoesntHave('orderOfPlay', fn ($q) => $q->whereNotNull('time'))->count()
            + TeamFixture::whereIn('draw_id', $drawIds)->when(! empty($scope['draw_id']), fn ($q) => $q->where('draw_id', $scope['draw_id']))->where('match_status', 0)->whereDoesntHave('fixtureResults')->whereNull('scheduled_at')->count();
        $revision = $publication->revision($event);
        return view('backend.schedule.saved-calendar', compact('event', 'scope', 'days', 'date', 'rows', 'retained', 'venues', 'draws', 'revision', 'unscheduledCount', 'dailySavedCounts'));
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

    public function hideScope(Request $request, Event $event, \App\Services\Scheduling\SchedulePublicationService $publication)
    {
        return $this->changePublication($request, $event, $publication, true);
    }

    public function publicPreview(Request $request, Event $event, \App\Services\Scheduling\SchedulePublicationService $publication)
    {
        $this->authorize('event.manage', $event);
        $scope = $this->calendarScope($request, $event);
        $rows = $publication->publishedRows($event)->filter(fn ($row) => (empty($scope['date']) || $scope['date'] === 'all' || substr($row['scheduled_at'], 0, 10) === $scope['date'])
            && (empty($scope['venue_id']) || (int) $row['venue_id'] === (int) $scope['venue_id'])
            && (empty($scope['draw_id']) || (int) $row['draw_id'] === (int) $scope['draw_id']))->take(1000)->values();
        return view('backend.schedule.public-schedule-preview', compact('event', 'rows', 'scope'));
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
            return redirect()->route('backend.event-venue-schedule.calendar', ['event' => $event->id] + $scope)
                ->with('success', $hide ? "Hidden {$count} public match times." : "Published {$count} saved match times. Other saved changes remain private.");
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()]);
        }
    }

    private function calendarScope(Request $request, Event $event): array
    {
        $scope = $request->validate(['date' => ['nullable', \Illuminate\Validation\Rule::when($request->input('date') !== 'all', ['date_format:Y-m-d'])], 'venue_id' => ['nullable', 'integer'], 'draw_id' => ['nullable', 'integer']]);
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
        $courtCount = DB::table('event_venue_courts')->where('event_id', $event->id)
            ->where('venue_id', $venue->id)->where('active', true)->count();
        $event->venues()->syncWithoutDetaching([$venue->id => ['num_courts' => $courtCount]]);
        return response()->json([
            'message' => "{$venue->name} added with {$courtCount} courts.",
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->name,
                'num_courts' => $courtCount,
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
        ]);
        $allowed = $event->venues()->whereKey($data['venue_id'])->exists()
            || DB::table('draw_venues')->whereIn('draw_id', $event->draws()->pluck('id'))->where('venue_id', $data['venue_id'])->exists();
        abort_unless($allowed, 422, 'This venue does not belong to the event.');
        DB::table('event_venue_courts')->updateOrInsert([
            'event_id' => $event->id, 'venue_id' => $data['venue_id'], 'label' => trim($data['label']),
        ], ['ball_type' => $data['ball_type'] ?: null, 'active' => true, 'updated_at' => now(), 'created_at' => now()]);
        $count = DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $data['venue_id'])->where('active', true)->count();
        $event->venues()->syncWithoutDetaching([$data['venue_id'] => ['num_courts' => $count]]);
        return response()->json(['message' => 'Court saved.']);
    }

    public function configureCourts(Request $request, Event $event, Venue $venue)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'courts' => ['required', 'integer', 'min:1', 'max:100'],
            'ball_type' => ['required', 'in:orange,green,yellow,red,standard'],
        ]);
        return DB::transaction(function () use ($event, $venue, $data) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $drawIds = $event->draws()->pluck('id');
            $belongs = $event->venues()->whereKey($venue->id)->exists()
                || DB::table('draw_venues')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)->exists();
            abort_unless($belongs, 404);

            $labels = array_map('strval', range(1, $data['courts']));
            $existing = DB::table('event_venue_courts')->where('event_id', $event->id)
                ->where('venue_id', $venue->id)->pluck('label')->map(fn ($label) => (string) $label);
            $removed = $existing->diff($labels)->values();
            if ($removed->isNotEmpty()) {
                $lockedAllocation = DB::table('draw_venue_court_allocations')->join('draws', 'draws.id', '=', 'draw_venue_court_allocations.draw_id')
                    ->where('draws.event_id', $event->id)->where('draw_venue_court_allocations.venue_id', $venue->id)
                    ->whereIn('draw_venue_court_allocations.court_label', $removed)
                    ->where(fn ($query) => $query->where('draws.locked', true)->orWhere('draws.published', true))->exists();
                if ($lockedAllocation) return response()->json([
                    'message' => 'A court being removed is allocated to a locked or published draw and cannot be changed.',
                ], 422);
                $fixtureIds = Fixture::whereIn('draw_id', $drawIds)->pluck('id');
                $scheduled = OrderOfPlay::where('venue_id', $venue->id)->whereIn('court', $removed)
                    ->where(fn ($query) => $query->whereIn('draw_id', $drawIds)->orWhereIn('fixture_id', $fixtureIds))->exists();
                $teamScheduled = TeamFixture::whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)
                    ->whereNotNull('scheduled_at')->get()->contains(fn ($fixture) => $removed->contains(
                        \App\Domain\Draws\Services\ScheduleAvailability::courtKey((string) $fixture->court_label)));
                if ($scheduled || $teamScheduled) return response()->json([
                    'message' => 'A court being removed already has scheduled matches. Clear those bookings before reducing or replacing the courts.',
                ], 422);
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
                'duration' => 75, 'wave_minutes' => 90, 'court_gap' => 5, 'player_rest' => 60,
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
                        'duration' => 75, 'wave_minutes' => 90, 'court_gap' => 5, 'player_rest' => 60,
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
                $data['schedule']['round_venue_setups'] = $stored['round_venue_setups'] ?? [];
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
            if (empty($options['programme'])) $playoffs->prepareEvent($event, $options['draw_ids'] ?? []);

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
            if (empty($options['programme'])) $playoffs->prepareEvent($event, $options['draw_ids'] ?? []);

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
            'duration' => ['required', 'integer', 'min:15', 'max:480'],
            'court_gap' => ['required', 'integer', 'min:0', 'max:120'],
            'player_rest' => ['required', 'integer', 'min:0', 'max:480'],
            'round_progression' => ['sometimes', 'in:team_ready,all_round'],
        ]);

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
                $venueId = (int) $data['venue_id'];
                $court = trim((string) $data['court']);
                DB::table('venues')->where('id', $venueId)->lockForUpdate()->get();
                $error = $this->manualAssignmentError($event, $fixture, $venueId, $court, $data, $conflicts);
                if ($error) throw new \InvalidArgumentException($error);

                $slot = $scheduleEngine->saveFixture($fixture->draw, $fixture->id, $data['scheduled_at'],
                    $venueId, $court, (int) $data['duration'], true, true);
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
            'duration' => ['required', 'integer', 'min:15', 'max:480'],
            'court_gap' => ['required', 'integer', 'min:0', 'max:120'],
            'player_rest' => ['required', 'integer', 'min:0', 'max:480'],
            'round_progression' => ['sometimes', 'in:team_ready,all_round'],
        ]);

        if (($data['fixture_kind'] ?? 'individual') === 'team') {
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
        if ($fixture->draw->locked) {
            return 'Unlock the draw before manually scheduling this match.';
        }
        if ($fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0) return 'A match with play cannot be moved to another slot.';

        $assignedVenue = $fixture->draw->venues->firstWhere('id', $venueId);
        if (! $assignedVenue) return 'The selected venue is not assigned to this draw.';

        $activeCourts = DB::table('event_venue_courts')->where('event_id', $event->id)
            ->where('venue_id', $venueId)->where('active', true)->pluck('label')->map(fn ($label) => (string) $label);
        if ($activeCourts->isEmpty()) {
            $activeCourts = collect(range(1, max(1, (int) $assignedVenue->pivot->num_courts)))
                ->map(fn ($label) => (string) $label);
        }
        $allocatedCourts = DB::table('draw_venue_court_allocations')->where('draw_id', $fixture->draw_id)
            ->where('venue_id', $venueId)->pluck('court_label')->map(fn ($label) => (string) $label);
        $permittedCourts = $allocatedCourts->isNotEmpty() ? $allocatedCourts->intersect($activeCourts) : $activeCourts;
        if (! $permittedCourts->contains($court)) return 'Choose an active court allocated to this draw.';

        return $conflicts->conflict($fixture->draw, $fixture, $venueId, $court,
            $data['scheduled_at'], (int) $data['duration'], (int) $data['player_rest'],
            (int) $data['court_gap'], true);
    }

    private function validatedOptions(Request $request): array
    {
        return $request->validate([
            'start' => ['required', 'date'], 'end' => ['nullable', 'date', 'after:start'],
            'programme' => ['sometimes', 'array:days,rounds'],
            'programme.days' => ['required_with:programme', 'array', 'size:3'],
            'programme.days.*.start' => ['required', 'date'],
            'programme.days.*.end' => ['required', 'date'],
            'programme.days.*.break_start' => ['nullable', 'date'],
            'programme.days.*.break_end' => ['nullable', 'date'],
            'programme.rounds' => ['required_with:programme', 'array', 'min:1', 'max:2000'],
            'programme.rounds.*.draw_id' => ['required', 'integer'],
            'programme.rounds.*.round' => ['required', 'integer', 'min:1'],
            'programme.rounds.*.day' => ['required', 'integer', 'between:1,3'],
            'programme.rounds.*.sequence' => ['required', 'integer', 'between:1,100'],
            'duration' => ['required', 'integer', 'min:15', 'max:480'],
            'wave_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'court_gap' => ['required', 'integer', 'min:0', 'max:120'],
            'player_rest' => ['required', 'integer', 'min:0', 'max:480'],
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
