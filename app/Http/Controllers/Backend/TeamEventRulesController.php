<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\Event;
use App\Services\TeamEventRulesService;
use App\Services\TeamStandingsService;
use Illuminate\Http\Request;

class TeamEventRulesController extends Controller
{
    public function edit(Event $event, TeamEventRulesService $service)
    {
        $this->authorize('team-draw.createFormat', $event);
        abort_unless($event->isTeam(), 404);
        return view('backend.team-draw.rules', ['event' => $event, 'rules' => $service->forEvent($event),
            'orderOptions' => TeamEventRulesService::STANDINGS_ORDER,
            'presets' => app(\App\Services\TeamEventFormatPresets::class)->all()]);
    }

    public function update(Request $request, Event $event, TeamEventRulesService $service)
    {
        $this->authorize('team-draw.createFormat', $event);
        abort_unless($event->isTeam(), 404);
        $service->save($event, $service->validate($request));
        return $request->expectsJson() ? response()->json(['success' => true])
            : back()->with('success', 'Scoring rules saved for future draws. Existing draws retain their rules.');
    }

    public function standings(Draw $draw, TeamStandingsService $service)
    {
        $this->authorize('team-fixture.view', $draw);
        abort_unless($draw->isTeamDraw(), 404);
        return view('backend.team-draw.standings', ['draw' => $draw, 'rows' => $service->forDraw($draw)]);
    }

    public function operations(Draw $draw)
    {
        $this->authorize('team-fixture.view', $draw);
        abort_unless($draw->isTeamDraw() && $draw->team_format_snapshot !== null, 404);
        return view('backend.team-draw.operations', ['draw' => $draw,
            'ties' => $draw->teamTies()->with(['homeTeam', 'awayTeam', 'rubbers'])->orderBy('round_nr')->orderBy('tie_nr')->get()]);
    }

    public function publicStandings(Draw $draw, TeamStandingsService $service)
    {
        $visibility = app(\App\Services\PublicTournamentVisibility::class);
        $visibility->ensureEventIsVisible($draw->event, auth()->user());
        $visibility->ensureDrawIsVisible($draw, auth()->user());
        abort_unless($draw->isTeamDraw() && $draw->published && $draw->event->standings_published, 404);
        $data = app(\App\Services\EventStandingsService::class)->forEvent($draw->event, publishedOnly: true, drawId: $draw->id);

        return response()->view('frontend.fixtures.team-standings', ['draw' => $draw, 'rows' => $data['sections'][0]['rows'] ?? []])
            ->header('Cache-Control', 'private, no-store');
    }
}
