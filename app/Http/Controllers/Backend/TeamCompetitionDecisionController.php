<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Draw, TeamTie};
use App\Services\TeamCompetitionDecisionService;
use Illuminate\Http\Request;

final class TeamCompetitionDecisionController extends Controller
{
    public function standings(Request $request, Draw $draw, TeamCompetitionDecisionService $service)
    {
        $this->authorize('team-draw.createFormat', $draw->event);
        abort_unless($draw->isTeamDraw(), 404);
        $input = $request->validate($this->common() + ['order' => 'required|array|min:2|max:100', 'order.*' => 'required|integer|distinct',
            'excluded' => 'sometimes|array|max:100', 'excluded.*' => 'integer|distinct', 'accept_incomplete' => 'sometimes|accepted']);
        $service->saveStandings($draw, $input);
        return back()->with('success', 'Final standings decision recorded. Review qualifiers before creating playoffs.');
    }

    public function playoff(Request $request, Draw $draw, TeamTie $tie, TeamCompetitionDecisionService $service)
    {
        $this->authorize('team-draw.createFormat', $draw->event);
        abort_unless($draw->isTeamDraw() && (int) $tie->draw_id === (int) $draw->id, 404);
        $input = $request->validate($this->common() + ['winner_team_id' => 'required|integer', 'method' => 'required|in:sets_games,mixed_tiebreak,ruling',
            'home_score' => 'nullable|integer|min:0|max:999', 'away_score' => 'nullable|integer|min:0|max:999', 'participants' => 'nullable|string|max:500']);
        $service->savePlayoff($draw, $tie, $input);
        return back()->with('success', 'Playoff deciding result recorded.');
    }

    private function common(): array
    {
        return ['reason' => 'required|string|min:5|max:2000', 'token' => 'required|string|size:64', 'request_id' => 'required|uuid'];
    }
}
