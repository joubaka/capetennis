<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Player, Team};
use App\Services\{TeamDrawSideResolver, TeamSubstitutionService};
use Illuminate\Http\Request;

class TeamSubstitutionController extends Controller
{
    public function show(Team $team)
    {
        $this->authorize('team.players.manage', $team);
        $this->authorize('individual-draw.create', $team->category->event);
        $roster = app(TeamDrawSideResolver::class)->activeRoster($team);
        $history = $team->competitionSubstitutions()->reorder('id', 'desc')->limit(30)->get();
        $actors = \App\Models\User::whereIn('id', $history->pluck('actor_id'))->get(['id', 'name'])->keyBy('id');
        return view('backend.team-fixtures.substitute', compact('team', 'roster', 'history', 'actors'));
    }

    public function players(Request $request, Team $team)
    {
        $this->authorize('team.players.manage', $team);
        $this->authorize('individual-draw.create', $team->category->event);
        $search = $request->validate(['search' => 'required|string|min:2|max:80'])['search'];
        return Player::where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('surname', 'like', '%'.$search.'%'))
            ->orderBy('surname')->limit(30)->get(['id', 'name', 'surname']);
    }

    private function input(Request $request, bool $execute): array
    {
        $rules = ['old_type' => 'required|in:profile,imported', 'old_id' => 'required|integer|min:1',
            'new_type' => 'required|in:profile,imported', 'new_id' => 'required_if:new_type,profile|nullable|integer|min:1',
            'name' => 'required_if:new_type,imported|nullable|string|max:100', 'surname' => 'required_if:new_type,imported|nullable|string|max:100',
            'date_of_birth' => 'required_if:new_type,imported|nullable|date|before:today', 'gender' => 'required_if:new_type,imported|nullable|in:male,female',
            'scope' => 'required|in:next,round,specific', 'from_round' => 'required_if:scope,round|nullable|integer|min:1',
            'fixture_ids' => 'nullable|array|max:1000', 'fixture_ids.*' => 'integer|min:1|distinct', 'reason' => 'required|string|min:5|max:1000'];
        if ($execute) $rules += ['request_key' => 'required|string|min:16|max:80', 'fingerprint' => 'required|string|size:64'];
        return $request->validate($rules);
    }

    public function preview(Request $request, Team $team, TeamSubstitutionService $service)
    {
        return response()->json($service->preview($team, $this->input($request, false), $request->user()));
    }

    public function store(Request $request, Team $team, TeamSubstitutionService $service)
    {
        $record = $service->execute($team, $this->input($request, true), $request->user());
        return response()->json(['id' => $record->id, 'details' => $record->details]);
    }
}
