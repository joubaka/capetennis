<?php

namespace App\Http\Controllers\Frontend;

use App\Domain\Draws\Enums\FixtureState;
use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use App\Models\TeamFixture;
use App\Models\Venue;
use App\Services\Scheduling\VenueMatchOrder;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VenueScoringController extends Controller
{
    public function __construct(private readonly VenueMatchOrder $venueMatchOrder)
    {
    }

    public function index(Request $request, Event $event): View
    {
        $this->authorize('event.score', $event);
        $request->validate([
            'schedule_source' => ['nullable', 'in:published,working'], 'date' => ['nullable', 'date_format:Y-m-d'],
            'draw_ids' => ['sometimes', 'array', 'min:1', 'max:200'], 'draw_ids.*' => ['integer', 'distinct'],
        ]);
        $publishedSchedule = $request->input('schedule_source') === 'published';
        $publishedRows = $publishedSchedule
            ? app(\App\Services\Scheduling\SchedulePublicationService::class)->publishedRows($event)
            : collect();
        $user = $request->user();
        $restrictedVenueId = $user->is_event_score_keeper($event->id)
            ? $user->scoringVenueIdForEvent($event->id)
            : null;

        $draws = $event->draws()
            ->with(['settings', 'flexibleMonrad', 'draw_types', 'categoryEvent.category'])
            ->orderBy('drawName')
            ->get();
        $scheduleDrawIds = $request->input('draw_ids', []);
        if ($scheduleDrawIds) {
            abort_unless(count($scheduleDrawIds) === $draws->whereIn('id', $scheduleDrawIds)->count(), 404, 'A selected draw does not belong to this tournament.');
            $draws = $draws->whereIn('id', $scheduleDrawIds)->values();
        }
        $drawIds = $draws->pluck('id');

        $venueIds = OrderOfPlay::query()
            ->whereIn('draw_id', $drawIds)
            ->whereNotNull('venue_id')
            ->pluck('venue_id')
            ->merge($event->venues()->pluck('venues.id'))
            ->merge($publishedRows->pluck('venue_id'))
            ->unique()
            ->values();
        $venues = Venue::query()
            ->whereIn('id', $venueIds)
            ->when($restrictedVenueId !== null, fn ($query) => $query->whereKey($restrictedVenueId))
            ->orderBy('name')
            ->get();

        $selectedVenue = null;
        if ($request->filled('venue')) {
            abort_if($restrictedVenueId !== null && $request->integer('venue') !== $restrictedVenueId, 403);
            $selectedVenue = $venues->firstWhere('id', (int) $request->integer('venue'));
            abort_unless($selectedVenue, 404, 'This venue does not belong to the selected tournament.');
        } elseif ($restrictedVenueId !== null) {
            $selectedVenue = $venues->firstWhere('id', $restrictedVenueId);
            abort_unless($selectedVenue, 403, 'Your assigned scoring venue is unavailable. Contact the tournament organiser.');
        }
        if ($publishedSchedule && $selectedVenue) {
            $publishedRows = $publishedRows->where('venue_id', $selectedVenue->id)->values();
        }
        if ($publishedSchedule && $request->filled('date')) {
            $publishedRows = $publishedRows->filter(fn ($row) => substr($row['scheduled_at'], 0, 10) === $request->input('date'))->values();
        }

        $selectedDraw = null;
        if ($request->filled('draw')) {
            $selectedDraw = $draws->firstWhere('id', (int) $request->integer('draw'));
            abort_unless($selectedDraw, 404, 'This draw does not belong to the selected tournament.');
        }

        $fixtures = Fixture::query()
            ->whereIn('draw_id', $drawIds)
            ->when($selectedDraw, fn ($query) => $query->where('draw_id', $selectedDraw->id))
            ->when($publishedSchedule, fn ($query) => $query->whereIn('id', $publishedRows->where('fixture_kind', 'individual')->pluck('fixture_id')))
            ->when($selectedVenue && ! $publishedSchedule, fn ($query) => $query->whereHas(
                'orderOfPlay',
                fn ($schedule) => $schedule->where('venue_id', $selectedVenue->id)
            ))
            ->when(! $publishedSchedule && ! $selectedDraw && ! $selectedVenue, fn ($query) => $query->whereHas('orderOfPlay'))
            ->with([
                'draw.settings',
                'draw.flexibleMonrad',
                'draw.categoryEvent.category',
                'registration1.players',
                'registration2.players',
                'fixtureResults',
                'orderOfPlay.venue',
            ])
            ->orderBy(OrderOfPlay::select('time')->whereColumn('fixture_id', 'fixtures.id')->limit(1))
            ->orderBy('draw_id')
            ->orderBy('round')
            ->orderBy('match_nr')
            ->when(! $publishedSchedule, fn ($query) => $query->limit(500))
            ->get();

        $teamFixtures = TeamFixture::query()
            ->whereIn('draw_id', $drawIds)
            ->when($publishedSchedule, fn ($query) => $query->whereIn('id', $publishedRows->where('fixture_kind', 'team')->pluck('fixture_id')))
            ->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))
            ->when($selectedDraw, fn ($query) => $query->where('draw_id', $selectedDraw->id))
            ->when($selectedVenue && ! $publishedSchedule, fn ($query) => $query->where('venue_id', $selectedVenue->id))
            ->when(! $publishedSchedule && ! $selectedDraw && ! $selectedVenue, fn ($query) => $query->whereNotNull('venue_id'))
            ->with([
                'draw.settings',
                'draw.flexibleMonrad',
                'draw.categoryEvent.category',
                'fixturePlayers.player1',
                'fixturePlayers.player2',
                'fixtureResults',
                'venue',
                'homeTeam',
                'awayTeam',
                'region1Name',
                'region2Name',
            ])
            ->inPlayOrder()
            ->when(! $publishedSchedule, fn ($query) => $query->limit(500))
            ->get();

        app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($teamFixtures);

        $matches = $fixtures->concat($teamFixtures);
        if ($publishedSchedule) {
            foreach ($matches as $match) {
                $workingVenueId = $match instanceof Fixture ? $match->orderOfPlay?->venue_id : $match->venue_id;
                $match->setAttribute('scoring_venue_changed', $restrictedVenueId !== null && (int) $workingVenueId !== $restrictedVenueId);
            }
            app(\App\Services\Scheduling\SchedulePublicationService::class)->projectFixtures($matches);
            $matches = $matches->filter(fn ($match) => ! $selectedVenue || (int) $match->venue_id === (int) $selectedVenue->id);
        }
        $matches = $matches
            ->sort(fn ($left, $right): int => $this->venueMatchOrder->compare($left, $right))
            ->values();

        $completed = $matches->filter(fn ($match) => $match->fixtureResults->isNotEmpty())->count();
        $ready = $matches->filter(fn ($match) => $match instanceof Fixture
            ? ($match->registration1_id && $match->registration2_id)
            : ($match->fixturePlayers->isNotEmpty() || ($match->homeTeam && $match->awayTeam)))->count();
        $nextMatches = $matches->filter(fn ($match) =>
            $match->fixtureResults->isEmpty()
            && (int) ($match->match_status ?? 0) !== FixtureState::STATUS_PARTIAL
            && $this->scheduledTime($match) !== PHP_INT_MAX
            && $this->hasKnownParticipants($match)
        )->take(2)->values();

        $recentActivity = DrawAuditLog::query()
            ->whereIn('draw_id', $drawIds)
            ->whereIn('action', [
                'match_started', 'match_stopped',
                'score_saved', 'score_corrected', 'bracket_score_saved',
                'bracket_score_corrected', 'score_deleted', 'monrad_score_changed',
            ])
            ->with(['draw:id,drawName', 'user:id,name'])
            ->latest()
            ->limit(12)
            ->get();

        return view('frontend.scoring.workspace', [
            'event' => $event,
            'draws' => $draws,
            'venues' => $venues,
            'matches' => $matches,
            'selectedVenue' => $selectedVenue,
            'selectedDraw' => $selectedDraw,
            'completed' => $completed,
            'ready' => $ready,
            'nextMatches' => $nextMatches,
            'recentActivity' => $recentActivity,
            'operatorName' => (string) $request->session()->get('venue_scoring.operator', ''),
            'venueRestricted' => $restrictedVenueId !== null,
            'scheduleSource' => $publishedSchedule ? 'published' : 'working',
            'scheduleDate' => $publishedSchedule ? $request->input('date') : null,
            'scheduleDrawIds' => $scheduleDrawIds,
        ]);
    }

    public function printVenue(Request $request, Event $event, Venue $venue): View
    {
        $this->authorize('event.score', $event);
        $this->requireAssignedVenue($request, $event, (int) $venue->id);
        abort_unless($event->venues()->whereKey($venue->id)->exists()
            || $event->draws()->whereHas('venues', fn ($query) => $query->where('venues.id', $venue->id))->exists(), 404);
        $validated = $request->validate([
            'source' => ['nullable', 'in:published,working'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        // A venue sheet always prints the full venue, rather than a filtered scoring queue.
        $request->replace($validated);
        $renderer = app(\App\Http\Controllers\Backend\HeadOfficeController::class);
        if ((int) $event->eventType === 3) {
            return $renderer->renderVenueFixtures($request, $event, $venue);
        }
        $request->merge([
            'print_type' => 'venue', 'venue_id' => $venue->id,
            'include_standings' => false, 'download' => false,
            'schedule_source' => $validated['source'] ?? 'published',
        ]);

        return $renderer->renderDrawPack($request, $event);
    }

    public function operator(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('event.score', $event);
        $validated = $request->validate([
            'operator' => ['required', 'string', 'max:80'],
        ]);

        $request->session()->put('venue_scoring.operator', trim($validated['operator']));

        return back()->with('success', 'Scoring operator saved for this telephone.');
    }

    public function setFixturePlaying(Request $request, Event $event, Fixture $fixture): JsonResponse
    {
        $this->authorize('event.score', $event);
        $validated = $request->validate(['playing' => ['sometimes', 'boolean']]);
        $playing = array_key_exists('playing', $validated) ? (bool) $validated['playing'] : true;

        return DB::transaction(function () use ($request, $event, $fixture, $playing): JsonResponse {
            $fixture = Fixture::query()->lockForUpdate()->with(['draw', 'fixtureResults', 'orderOfPlay'])->findOrFail($fixture->id);

            abort_unless((int) $fixture->draw?->event_id === (int) $event->id, 404);
            abort_if($fixture->draw->locked, 403, 'Draw is locked.');
            abort_if($fixture->fixtureResults->isNotEmpty(), 422, 'A completed match cannot be moved on or off court.');
            if ($playing) {
                abort_unless($fixture->registration1_id && $fixture->registration2_id, 422, 'Both players must be known before the match can start.');
            }

            $venueId = $fixture->orderOfPlay?->venue_id;
            $this->requireAssignedVenue($request, $event, $venueId === null ? null : (int) $venueId);

            $status = $playing ? FixtureState::STATUS_PARTIAL : FixtureState::STATUS_PENDING;
            if ((int) $fixture->match_status !== $status) {
                $fixture->update(['match_status' => $status]);
                DrawAuditLog::record($fixture->draw_id, $playing ? 'match_started' : 'match_stopped', $fixture->id, [
                    'fixture_type' => 'individual',
                    'venue_id' => $venueId,
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => $playing ? 'playing' : 'outstanding',
                'message' => $playing ? 'Players are now marked on court.' : 'Players are now marked off court.',
            ]);
        });
    }

    /**
     * Backward compatibility for route caches created before the action was renamed.
     */
    public function startFixture(Request $request, Event $event, Fixture $fixture): JsonResponse
    {
        return $this->setFixturePlaying($request, $event, $fixture);
    }

    public function setTeamFixturePlaying(Request $request, Event $event, TeamFixture $fixture): JsonResponse
    {
        $this->authorize('event.score', $event);
        $validated = $request->validate(['playing' => ['sometimes', 'boolean'], 'participant_revision' => ['nullable', 'string', 'size:64']]);
        $playing = array_key_exists('playing', $validated) ? (bool) $validated['playing'] : true;

        return DB::transaction(function () use ($request, $event, $fixture, $playing): JsonResponse {
            \App\Models\Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $draw = \App\Models\Draw::whereKey($fixture->draw_id)->lockForUpdate()->firstOrFail();
            $tie = $fixture->team_tie_id ? \App\Models\TeamTie::whereKey($fixture->team_tie_id)->lockForUpdate()->firstOrFail() : null;
            $fixture = TeamFixture::query()->lockForUpdate()->findOrFail($fixture->id);
            abort_if($fixture->draw_id !== $draw->id || $fixture->team_tie_id !== $tie?->id, 409, 'Fixture relationships changed.');
            $fixture->setRelation('draw', $draw);
            $fixture->setRelation('fixtureResults', $fixture->fixtureResults()->lockForUpdate()->get());
            app(\App\Services\TeamParticipantHistoryService::class)->assertRevision($fixture, (int) $draw->event_id, $request->input('participant_revision'));

            abort_unless((int) $fixture->draw?->event_id === (int) $event->id, 404);
            $this->authorize('team-fixture.saveScore', $fixture);
            abort_if($fixture->draw->locked, 403, 'Draw is locked.');
            abort_if($fixture->fixtureResults->isNotEmpty(), 422, 'A completed match cannot be moved on or off court.');

            $this->requireAssignedVenue($request, $event, $fixture->venue_id === null ? null : (int) $fixture->venue_id);

            $status = $playing ? FixtureState::STATUS_PARTIAL : FixtureState::STATUS_PENDING;
            if ((int) $fixture->match_status !== $status) {
                $fixture->update(['match_status' => $status]);
                DrawAuditLog::record($fixture->draw_id, $playing ? 'match_started' : 'match_stopped', $fixture->id, [
                    'fixture_type' => 'team',
                    'venue_id' => $fixture->venue_id,
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => $playing ? 'playing' : 'outstanding',
                'message' => $playing ? 'Players are now marked on court.' : 'Players are now marked off court.',
            ]);
        });
    }

    /**
     * Backward compatibility for route caches created before the action was renamed.
     */
    public function startTeamFixture(Request $request, Event $event, TeamFixture $fixture): JsonResponse
    {
        return $this->setTeamFixturePlaying($request, $event, $fixture);
    }

    private function requireAssignedVenue(Request $request, Event $event, ?int $venueId): void
    {
        if ($request->user()->is_event_score_keeper($event->id)) {
            abort_unless($request->user()->canScoreVenue($event->id, $venueId), 403);
        }
    }

    private function scheduledTime(Fixture|TeamFixture $match): int
    {
        $time = $match instanceof Fixture ? $match->orderOfPlay?->time : $match->scheduled_at;

        if ($time instanceof CarbonInterface) {
            return $time->getTimestamp();
        }

        return $time ? strtotime((string) $time) : PHP_INT_MAX;
    }

    private function hasKnownParticipants(Fixture|TeamFixture $match): bool
    {
        return $match instanceof Fixture
            ? (bool) ($match->registration1_id && $match->registration2_id)
            : (bool) ($match->fixturePlayers->isNotEmpty() || ($match->homeTeam && $match->awayTeam));
    }

}
