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
        $data = $request->validate(['target' => 'required|integer|min:3|max:5',
            'category_groups' => 'sometimes|array|max:256', 'category_groups.*' => 'required|in:auto,one,two']);
        $plan = $planner->preview($event, $request->user(), (int) $data['target'], $data['category_groups'] ?? []);
        return redirect()->route('backend.ctr-planner.show', [$event, 'plan' => $plan->token]);
    }

    public function review(Request $request, Event $event, CtrDrawPlanner $planner)
    {
        $data = $request->validate(['token' => 'required|uuid', 'revision' => 'required|string|size:64',
            'placements' => 'required|array|max:1600', 'placements.*' => 'required|integer|min:1|max:1600', 'manual_reviewed' => 'sometimes|boolean',
            'excluded_categories' => 'sometimes|array|max:256', 'excluded_categories.*' => 'integer|distinct', 'short_matches_reviewed' => 'sometimes|boolean',
            'regroup_category' => 'sometimes|integer', 'category_layouts' => 'sometimes|array|max:256', 'category_layouts.*' => 'nullable|string|max:3200',
            'custom_layouts' => 'sometimes|array|max:256', 'custom_layouts.*' => 'nullable|string|max:3200',
            'scoring' => 'sometimes|array:score_format,playoff_score_format,match_duration_minutes,changeover_minutes',
            'scoring.score_format' => ['nullable', \Illuminate\Validation\Rule::in(\App\Domain\Draws\Services\TennisScoreFormat::keys())],
            'scoring.playoff_score_format' => ['nullable', \Illuminate\Validation\Rule::in(\App\Domain\Draws\Services\TennisScoreFormat::keys())],
            'scoring.match_duration_minutes' => 'nullable|integer|min:1|max:240', 'scoring.changeover_minutes' => 'nullable|integer|min:0|max:60']);
        $categoryId = isset($data['regroup_category']) ? (int) $data['regroup_category'] : null;
        $sizes = $categoryId === null ? null : ($data['custom_layouts'][$categoryId] ?? $data['category_layouts'][$categoryId] ?? null);
        $plan = $planner->review($event, $request->user(), $data['token'], $data['revision'], $data['placements'], (bool) ($data['manual_reviewed'] ?? false), $data['excluded_categories'] ?? [], (bool) ($data['short_matches_reviewed'] ?? false), $categoryId, $sizes, $data['scoring'] ?? null);
        if ($request->expectsJson()) {
            $competitions = $planner->competitions($plan->proposal);
            $excluded = $plan->proposal['excluded_category_ids'] ?? [];
            $ready = true;
            try { $planner->validateProposal($plan->proposal); } catch (\Illuminate\Validation\ValidationException) { $ready = false; }
            return response()->json(['saved' => true, 'token' => $plan->token, 'event_id' => (int) $event->id,
                'revision' => $planner->revision($plan), 'ready' => $ready, 'excluded_category_ids' => $excluded,
                'totals' => ['competitions' => count($competitions), 'matches' => (int) array_sum(array_column($competitions, 'matches')),
                    'included_players' => count(array_filter($plan->proposal['rows'], fn ($row) => !in_array((int) $row['category_id'], $excluded, true))),
                    'winner_prizes' => count($competitions), 'sportsmanship_prizes' => count($competitions)],
                'expires_at' => $plan->expires_at->toIso8601String(),
            ])->header('Cache-Control', 'no-store, private');
        }
        return redirect()->route('backend.ctr-planner.show', [$event, 'plan' => $plan->token])->with('success', 'Placements reviewed. Check the groups and confirm when ready.');
    }

    public function apply(Request $request, Event $event, CtrDrawPlanner $planner)
    {
        $data = $request->validate(['token' => 'required|uuid', 'revision' => 'required|string|size:64']);
        $plan = $planner->apply($event, $request->user(), $data['token'], $data['revision']);
        return redirect()->route('backend.ctr-planner.show', [$event, 'plan' => $plan->token])->with('success', 'Draws created. Review them before scheduling or publishing.');
    }
}
