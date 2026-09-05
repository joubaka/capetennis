<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueScoringController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        $this->authorize('event.score', $event);

        $draws = $event->draws()
            ->with(['settings', 'flexibleMonrad', 'draw_types'])
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
        $venues = Venue::query()->whereIn('id', $venueIds)->orderBy('name')->get();

        $selectedVenue = null;
        if ($request->filled('venue')) {
            $selectedVenue = $venues->firstWhere('id', (int) $request->integer('venue'));
            abort_unless($selectedVenue, 404, 'This venue does not belong to the selected tournament.');
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

        $completed = $fixtures->filter(fn (Fixture $fixture) => $fixture->fixtureResults->isNotEmpty())->count();
        $ready = $fixtures->filter(fn (Fixture $fixture) => $fixture->registration1_id && $fixture->registration2_id)->count();

        $recentActivity = DrawAuditLog::query()
            ->whereIn('draw_id', $drawIds)
            ->whereIn('action', [
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
            'fixtures' => $fixtures,
            'selectedVenue' => $selectedVenue,
            'selectedDraw' => $selectedDraw,
            'completed' => $completed,
            'ready' => $ready,
            'recentActivity' => $recentActivity,
            'operatorName' => (string) $request->session()->get('venue_scoring.operator', ''),
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
}
