<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Draw\{CtrDrawPlanner, CtrPlannerAccess};
use Illuminate\Http\Request;

class CtrDrawPlannerController extends Controller
{
    public function show(Request $request, Event $event, CtrDrawPlanner $planner)
    {
        app(CtrPlannerAccess::class)->authorize($request->user(), $event, false);
        $certified = $event->ctrCertification()->where('certified', true)->exists();
        $plan = $certified && $request->filled('plan') ? $planner->owned($event, $request->user(), (string) $request->query('plan')) : null;
        $source = $certified ? $planner->source($event, $request->user()) : null;
        return view('backend.draw.ctr-planner', compact('event', 'certified', 'source', 'plan', 'planner'));
    }

    public function certify(Request $request, Event $event, CtrDrawPlanner $planner)
    {
        $data = $request->validate(['certified' => 'required|boolean']);
        $planner->certify($event, $request->user(), (bool) $data['certified']);
        return redirect()->route('backend.ctr-planner.show', $event)->with('success', $data['certified'] ? 'Event marked CTR Certified.' : 'CTR certification removed.');
    }

    public function preview(Request $request, Event $event, CtrDrawPlanner $planner)
    {
        $data = $request->validate(['target' => 'required|integer|min:3|max:5']);
        $plan = $planner->preview($event, $request->user(), (int) $data['target']);
        return redirect()->route('backend.ctr-planner.show', [$event, 'plan' => $plan->token]);
    }

    public function review(Request $request, Event $event, CtrDrawPlanner $planner)
    {
        $data = $request->validate(['token' => 'required|uuid', 'revision' => 'required|string|size:64',
            'placements' => 'required|array|max:1600', 'placements.*' => 'required|integer|min:1|max:1600', 'manual_reviewed' => 'sometimes|boolean',
            'excluded_categories' => 'sometimes|array|max:256', 'excluded_categories.*' => 'integer|distinct', 'short_matches_reviewed' => 'sometimes|boolean']);
        $plan = $planner->review($event, $request->user(), $data['token'], $data['revision'], $data['placements'], (bool) ($data['manual_reviewed'] ?? false), $data['excluded_categories'] ?? [], (bool) ($data['short_matches_reviewed'] ?? false));
        return redirect()->route('backend.ctr-planner.show', [$event, 'plan' => $plan->token])->with('success', 'Placements reviewed. Check the groups and confirm when ready.');
    }

    public function apply(Request $request, Event $event, CtrDrawPlanner $planner)
    {
        $data = $request->validate(['token' => 'required|uuid', 'revision' => 'required|string|size:64']);
        $plan = $planner->apply($event, $request->user(), $data['token'], $data['revision']);
        return redirect()->route('backend.ctr-planner.show', [$event, 'plan' => $plan->token])->with('success', 'Draws created. Review them before scheduling or publishing.');
    }
}
