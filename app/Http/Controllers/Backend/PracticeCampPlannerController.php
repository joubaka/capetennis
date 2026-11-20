<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, EventAdmin, PracticeCampPlan};
use App\Services\PracticeCampPlanner;
use Illuminate\Http\Request;

class PracticeCampPlannerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['super-user', 'admin']), 403);
        $plans = PracticeCampPlan::with('event')->whereNotNull('event_id')
            ->when(! $request->user()->hasRole('super-user'), fn ($q) => $q->whereIn('event_id', EventAdmin::where('user_id', $request->user()->id)->select('event_id')))
            ->latest('id')->paginate(20);
        $campEvents = Event::whereHas('eventTypeModel', fn ($q) => $q->where('type', 3)->orWhere(fn ($legacy) => $legacy->where(fn ($base) => $base->whereNull('type')->orWhere('type', 0))->whereRaw('LOWER(TRIM(name)) = ?', ['camp'])))
            ->when(! $request->user()->hasRole('super-user'), fn ($q) => $q->whereIn('id', EventAdmin::where('user_id', $request->user()->id)->select('event_id')))
            ->latest('start_date')->limit(50)->get(['id', 'name']);

        return view('backend.practice-camps.index', compact('plans', 'campEvents'));
    }

    public function store(Request $request, PracticeCampPlanner $planner)
    {
        $plan = $planner->create2027($request->user());

        return redirect()->route('backend.practice-camps.show', $plan)->with('status', 'Private camp draft ready. No invitations have been sent.');
    }

    public function show(Request $request, PracticeCampPlan $plan, PracticeCampPlanner $planner)
    {
        $planner->authorizePlan($request->user(), $plan);
        $data = $request->validate([
            'source_event_id' => 'nullable|integer|exists:events,id', 'region_id' => 'nullable|integer',
            'team_ids' => 'sometimes|array|max:100', 'team_ids.*' => 'integer|distinct',
            'source_q' => 'nullable|string|max:100', 'preview' => 'sometimes|boolean',
        ]);
        $events = Event::query()->where('id', '!=', $plan->event_id)
            ->whereHas('regions')
            ->when(! $request->user()->hasRole('super-user'), fn ($q) => $q->whereIn('id', EventAdmin::where('user_id', $request->user()->id)->select('event_id')))
            ->when($data['source_q'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('start_date')->limit(50)->get(['id', 'name', 'start_date']);
        $source = isset($data['source_event_id']) ? Event::findOrFail($data['source_event_id']) : null;
        $regions = collect();
        $teams = collect();
        $preview = null;
        if ($source) {
            abort_if((int) $source->id === (int) $plan->event_id, 422);
            $planner->authorizeManager($request->user(), $source);
            $regions = $source->regions()->orderBy('region_name')->limit(101)->get(['team_regions.id', 'region_name']);
            abort_if($regions->count() > 100, 422, 'This source has too many regions for one review.');
            if (isset($data['region_id'])) {
                $teams = $planner->teams($request->user(), $source, (int) $data['region_id']);
                abort_if($teams->count() > 100, 422, 'This region has too many teams for one review.');
                if ($request->boolean('preview')) {
                    $preview = $planner->preview($request->user(), $source, (int) $data['region_id'], $data['team_ids'] ?? []);
                }
            }
        }

        return view('backend.practice-camps.show', compact('plan', 'events', 'source', 'regions', 'teams', 'preview'));
    }

    public function add(Request $request, PracticeCampPlan $plan, PracticeCampPlanner $planner)
    {
        $data = $request->validate([
            'revision' => 'required|uuid', 'fingerprint' => 'required|string|size:64',
            'source_event_id' => 'required|integer|exists:events,id', 'region_id' => 'required|integer',
            'team_ids' => 'required|array|min:1|max:100', 'team_ids.*' => 'integer|distinct',
            'selected' => 'required|array|min:1|max:2000', 'selected.*' => 'required|string|distinct|max:100',
        ]);
        $planner->add($request->user(), $plan, Event::findOrFail($data['source_event_id']), $data);

        return redirect()->route('backend.practice-camps.show', $plan)->with('status', 'Selected players added to the private draft. No invitations have been sent.');
    }

    public function remove(Request $request, PracticeCampPlan $plan, PracticeCampPlanner $planner)
    {
        $data = $request->validate(['revision' => 'required|uuid', 'key' => 'required|string|max:100']);
        $planner->remove($request->user(), $plan, $data['revision'], $data['key']);

        return redirect()->route('backend.practice-camps.show', $plan)->with('status', 'Player removed from the camp draft. Source teams are unchanged.');
    }
}
