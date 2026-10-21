<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Draw, Event};
use App\Services\{TeamEventRulesService, TeamPlacementCompetitionService};
use Illuminate\Http\Request;

final class TeamPlacementCompetitionController extends Controller
{
    public function setup(Event $event, TeamPlacementCompetitionService $service)
    {
        $this->authorize('team-draw.createFormat', $event);
        abort_unless($event->isTeam(), 404);
        return view('backend.team-draw.competition', ['event' => $event, 'groups' => $service->groups($event)]);
    }

    public function store(Request $request, Event $event, TeamPlacementCompetitionService $service)
    {
        $this->authorize('team-draw.createFormat', $event);
        abort_unless($event->isTeam(), 404);
        $input = $request->validate(['category_ids' => 'required|array|min:2|max:10', 'category_ids.*' => 'required|integer|distinct',
            'round_robin' => 'required|array|min:1|max:100', 'playoff' => 'required|array|min:1|max:100']);
        $draw = $service->create($event, $input);
        return response()->json(['url' => route('draw.guide.show', [$event, $draw])]);
    }

    public function scoring(Request $request, Draw $draw, TeamPlacementCompetitionService $service, TeamEventRulesService $rules)
    {
        $this->authorize('team-draw.updateTeamDraw', $draw);
        abort_unless($draw->event->isTeam() && isset($draw->team_draw_selection['competition']), 404);
        if ($request->isMethod('get')) return view('backend.team-draw.competition-scoring', ['draw' => $draw,
            'rules' => $rules->forDraw($draw), 'orderOptions' => TeamEventRulesService::STANDINGS_ORDER]);
        $service->configureScoring($draw, $rules->validate($request));
        return redirect()->route('draw.guide.show', [$draw->event_id, $draw])->with('success', 'Scoring confirmed for this phase.');
    }

    public function playoffs(Draw $draw, TeamPlacementCompetitionService $service)
    {
        $this->authorize('team-draw.createFormat', $draw->event);
        abort_unless($draw->event->isTeam(), 404);
        $playoff = $service->playoffs($draw);
        return redirect()->route('draw.guide.show', [$playoff->event_id, $playoff]);
    }

    public function draft(Draw $draw, TeamPlacementCompetitionService $service)
    {
        $this->authorize('team-draw.createFormat', $draw->event);
        abort_unless($draw->event->isTeam(), 404);
        $playoff = $service->prepareDraft($draw);
        return redirect()->route('draw.guide.show', [$playoff->event_id, $playoff]);
    }
}
