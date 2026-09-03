<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Services\Draw\FlexibleMonradService;
use Illuminate\Http\Request;

class FlexibleMonradController extends Controller
{
    public function __construct(private readonly FlexibleMonradService $monrad) {}

    public function show(Draw $draw)
    {
        $this->authorize('view', $draw);
        return $this->page($draw, false);
    }

    public function publicShow(Draw $draw)
    {
        abort_unless($draw->published && $draw->flexibleMonrad?->graph, 404);
        return $this->page($draw, true);
    }

    public function demo()
    {
        $players = array_map(fn ($id) => ['id' => $id, 'name' => sprintf('Demo Player %02d', $id)], range(1, 22));
        return view('backend.draw.flexible-monrad', ['title' => 'Flexible Monrad demo', 'config' => [
            'demo' => true, 'readOnly' => false, 'canEdit' => true, 'canScore' => true, 'canPublish' => false,
            'backUrl' => null, 'publicUrl' => null, 'urls' => ['generate' => route('flexible-monrad.demo-preview')],
            'state' => ['revision' => 0, 'draft' => ['size' => 32, 'slots' => (object) []],
                'players' => $players, 'matches' => (object) [], 'positions' => [], 'generated' => false, 'published' => false, 'locked' => false],
        ]]);
    }

    public function demoPreview(Request $request)
    {
        $data = $request->validate(['draft' => 'required|array', 'draft.size' => 'required|integer|in:4,8,16,32,64',
            'draft.slots' => 'present|array|max:64', 'draft.slots.*.type' => 'required|in:player,bye',
            'draft.slots.*.id' => 'nullable|integer|min:1|max:22']);
        $graph = app(\App\Services\Draw\FlexibleMonradCompiler::class)->compile($data['draft']);
        return response()->json($graph);
    }

    public function save(Request $request, Draw $draw)
    {
        $this->authorize('update', $draw);
        $data = $request->validate(['revision' => 'required|integer|min:0', 'draft' => 'required|array']);
        $this->monrad->save($draw, $data['draft'], $data['revision']);
        return response()->json($this->monrad->state($draw->fresh()));
    }

    public function generate(Request $request, Draw $draw)
    {
        $this->authorize('generateBrackets', $draw);
        $data = $request->validate(['revision' => 'required|integer|min:0']);
        $this->monrad->generate($draw, $data['revision']);
        return response()->json($this->monrad->state($draw->fresh()));
    }

    public function publish(Request $request, Draw $draw)
    {
        $this->authorize('publish', $draw);
        $data = $request->validate(['revision' => 'required|integer|min:0', 'published' => 'required|boolean']);
        $this->monrad->publish($draw, $data['revision'], $data['published']);
        return response()->json($this->monrad->state($draw->fresh()));
    }

    public function reopen(Request $request, Draw $draw)
    {
        $this->authorize('update', $draw);
        $data = $request->validate(['revision' => 'required|integer|min:0']);
        $this->monrad->reopen($draw, $data['revision']);
        return response()->json($this->monrad->state($draw->fresh()));
    }

    public function score(Request $request, Draw $draw, int $fixture)
    {
        $this->authorize($request->input('sets') === null ? 'deleteScore' : 'saveScore', $draw);
        $data = $request->validate(['revision' => 'required|integer|min:0', 'sets' => 'present|nullable|array|min:1|max:5',
            'sets.*' => 'array|size:2', 'sets.*.*' => 'required|integer|min:0|max:20', 'reset_dependents' => 'sometimes|boolean']);
        $this->monrad->score($draw, $fixture, $data['sets'], $data['revision'], $data['reset_dependents'] ?? false);
        return response()->json($this->monrad->state($draw->fresh()));
    }

    public function reconcileWithdrawals(Request $request, Draw $draw)
    {
        $this->authorize('saveScore', $draw);
        $data = $request->validate(['revision' => 'required|integer|min:0']);
        $this->monrad->reconcileWithdrawals($draw, $data['revision']);
        return response()->json($this->monrad->state($draw->fresh()));
    }

    private function page(Draw $draw, bool $public)
    {
        $state = $this->monrad->state($draw);
        $workflow = $draw->settings?->workflow ?? 'custom_monrad';
        $label = DrawSetupController::OPTIONS[$workflow][0] ?? 'Custom Monrad';
        if ($public) {
            $ids = $draw->flexibleMonrad->graph['players'];
            $state['players'] = $state['players']->whereIn('id', $ids)->values();
            unset($state['revision']);
        }
        return view('backend.draw.flexible-monrad', ['title' => $draw->drawName.' — '.$label, 'config' => [
            'workflow' => $workflow,
            'setupUrl' => $public ? null : route('draw.setup.show', $draw),
            'demo' => false, 'readOnly' => $public,
            'canEdit' => ! $public && auth()->user()->can('view', $draw),
            'canScore' => ! $public && auth()->user()->can('saveScore', $draw),
            'canPublish' => ! $public && auth()->user()->can('publish', $draw),
            'backUrl' => $public ? null : route('draws.manage', $draw),
            'publicUrl' => route('public.flexible-monrad.show', $draw),
            'urls' => $public ? [] : [
                'save' => route('flexible-monrad.save', $draw), 'generate' => route('flexible-monrad.generate', $draw),
                'publish' => route('flexible-monrad.publish', $draw),
                'reopen' => route('flexible-monrad.reopen', $draw),
                'withdrawals' => route('flexible-monrad.withdrawals', $draw),
                'score' => route('flexible-monrad.score', [$draw, '__FIXTURE__']),
            ], 'state' => $state,
        ]]);
    }
}
