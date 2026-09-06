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
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VenueScoringController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        $this->authorize('event.score', $event);
        $user = $request->user();
        $restrictedVenueId = $user->is_event_score_keeper($event->id)
            ? $user->scoringVenueIdForEvent($event->id)
            : null;

        $draws = $event->draws()
            ->with(['settings', 'flexibleMonrad', 'draw_types', 'categoryEvent.category'])
            ->orderBy('drawName')
            ->get();
        $drawIds = $draws->pluck('id');

        $venueIds = OrderOfPlay::query()
            ->whereIn('draw_id', $drawIds)
            ->whereNotNull('venue_id')
            ->pluck('venue_id')
            ->merge($event->venues()->pluck('venues.id'))
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
        }

        $selectedDraw = null;
        if ($request->filled('draw')) {
            $selectedDraw = $draws->firstWhere('id', (int) $request->integer('draw'));
            abort_unless($selectedDraw, 404, 'This draw does not belong to the selected tournament.');
        }

        $fixtures = Fixture::query()
            ->whereIn('draw_id', $drawIds)
            ->when($selectedDraw, fn ($query) => $query->where('draw_id', $selectedDraw->id))
            ->when($selectedVenue, fn ($query) => $query->whereHas(
                'orderOfPlay',
                fn ($schedule) => $schedule->where('venue_id', $selectedVenue->id)
            ))
            ->when(! $selectedDraw && ! $selectedVenue, fn ($query) => $query->whereHas('orderOfPlay'))
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
            ->limit(500)
            ->get();

        $teamFixtures = TeamFixture::query()
            ->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))
            ->when($selectedDraw, fn ($query) => $query->where('draw_id', $selectedDraw->id))
            ->when($selectedVenue, fn ($query) => $query->where('venue_id', $selectedVenue->id))
            ->when(! $selectedDraw && ! $selectedVenue, fn ($query) => $query->whereNotNull('venue_id'))
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
            ->orderBy('scheduled_at')
            ->orderBy('round_nr')
            ->orderBy('home_rank_nr')
            ->limit(500)
            ->get();

        $matches = $fixtures->concat($teamFixtures)->sort(function ($left, $right): int {
            $timeOrder = $this->scheduledTime($left) <=> $this->scheduledTime($right);
            if ($timeOrder !== 0) {
                return $timeOrder;
            }

            $ageOrder = strnatcasecmp($this->ageGroup($left), $this->ageGroup($right));
            if ($ageOrder !== 0) {
                return $ageOrder;
            }

            $courtOrder = strnatcasecmp($this->courtNumber($left), $this->courtNumber($right));
            if ($courtOrder !== 0) {
                return $courtOrder;
            }

            $drawOrder = strnatcasecmp((string) $left->draw?->drawName, (string) $right->draw?->drawName);
            if ($drawOrder !== 0) {
                return $drawOrder;
            }

            return [$left instanceof TeamFixture ? 1 : 0, $left->id]
                <=> [$right instanceof TeamFixture ? 1 : 0, $right->id];
        })->values();

        $completed = $matches->filter(fn ($match) => $match->fixtureResults->isNotEmpty())->count();
        $ready = $matches->filter(fn ($match) => $match instanceof Fixture
            ? ($match->registration1_id && $match->registration2_id)
            : ($match->fixturePlayers->isNotEmpty() || ($match->homeTeam && $match->awayTeam)))->count();

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
            'recentActivity' => $recentActivity,
            'operatorName' => (string) $request->session()->get('venue_scoring.operator', ''),
            'venueRestricted' => $restrictedVenueId !== null,
        ]);
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
        $validated = $request->validate(['playing' => ['sometimes', 'boolean']]);
        $playing = array_key_exists('playing', $validated) ? (bool) $validated['playing'] : true;

        return DB::transaction(function () use ($request, $event, $fixture, $playing): JsonResponse {
            $fixture = TeamFixture::query()->lockForUpdate()->with(['draw', 'fixtureResults'])->findOrFail($fixture->id);

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

    private function ageGroup(Fixture|TeamFixture $match): string
    {
        return trim((string) (
            $match->draw?->categoryEvent?->category?->name
            ?: ($match instanceof TeamFixture ? $match->age : null)
            ?: $match->draw?->drawName
        ));
    }

    private function courtNumber(Fixture|TeamFixture $match): string
    {
        $court = $match instanceof Fixture ? $match->orderOfPlay?->court : $match->court_label;

        return $court === null || $court === '' ? "\u{10FFFF}" : (string) $court;
    }
}
