<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Draw, DrawAuditLog};
use App\Services\Draw\FlexibleMonradService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DrawSetupController extends Controller
{
    public const OPTIONS = [
        'round_robin' => ['Round robin only', 'Everyone plays within their group. Final standings decide the result; there are no playoffs.'],
        'round_robin_playoffs' => ['Round robin → playoffs', 'Build groups first, then choose how players advance to playoffs.'],
        'playoffs' => ['Playoffs only', 'Start with a knockout bracket. Winners advance; no placement matches.'],
        'monrad' => ['Monrad only', 'Start everyone in the opening round. Winners and losers play for finishing positions.'],
        'custom_monrad' => ['Custom Monrad', 'Place players in different starting rounds, then play for finishing positions.'],
    ];

    public function show(Draw $draw)
    {
        $this->authorize('view', $draw);
        return view('backend.draw.setup', ['draw' => $draw, 'options' => self::OPTIONS]);
    }

    public function store(Request $request, Draw $draw)
    {
        $this->authorize('update', $draw);
        $data = $request->validate([
            'workflow' => 'required|in:'.implode(',', array_keys(self::OPTIONS)),
            'category_event_id' => ['nullable', 'integer', Rule::exists('category_events', 'id')->where('event_id', $draw->event_id)],
        ]);
        if (! $draw->category_event_id && ! in_array($data['workflow'], ['round_robin', 'round_robin_playoffs'], true) && empty($data['category_event_id'])) {
            return view('backend.draw.setup-category', [
                'draw' => $draw, 'workflow' => $data['workflow'], 'label' => self::OPTIONS[$data['workflow']][0],
                'categories' => \App\Models\CategoryEvent::where('event_id', $draw->event_id)->with('category')->get(),
            ]);
        }
        DB::transaction(function () use ($draw, $data) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->locked || $draw->published, 409, 'Unlock and unpublish the draw before choosing a format.');
            abort_if($draw->team_category_id || $draw->event?->isTeam(), 422, 'Use the team draw setup for team fixtures.');
            if ($draw->settings?->workflow === $data['workflow']) return;
            $isRoundRobinPlayoffUpgrade = in_array($draw->settings?->workflow, [null, 'round_robin'], true)
                && $data['workflow'] === 'round_robin_playoffs'
                && ! $draw->drawFixtures()->where('stage', '!=', 'RR')->exists()
                && empty($draw->flexibleMonrad?->draft['slots'])
                && ! $draw->flexibleMonrad?->graph;
            // Never discard existing assignments, fixtures, or custom starting paths.
            abort_if(! $isRoundRobinPlayoffUpgrade && ($draw->drawFixtures()->exists()
                || $draw->groups()->whereHas('registrations')->exists()
                || ! empty($draw->flexibleMonrad?->draft['slots']) || $draw->flexibleMonrad?->graph),
                409, 'This draw already has assignments or fixtures. Use a new empty draw to choose a different format.');
            if (! $draw->category_event_id && ! in_array($data['workflow'], ['round_robin', 'round_robin_playoffs'], true)) {
                abort_if($draw->registrations()->reorder()->exists(), 409, 'Choose a new empty draw before changing its player category.');
                $draw->update(['category_event_id' => $data['category_event_id']]);
            }
            $draw->settings()->updateOrCreate(['draw_id' => $draw->id], ['workflow' => $data['workflow']]);
            if (in_array($data['workflow'], ['round_robin', 'round_robin_playoffs'], true)) {
                $draw->flexibleMonrad()->delete(); // Only an empty, ungenerated draft can reach here.
                $draw->settings()->update(['draw_format_id' => \App\Models\DrawFormats::where('name', 'Round Robin')->value('id')]);
            } else {
                app(FlexibleMonradService::class)->save($draw->fresh(), ['size' => 32, 'slots' => []], $draw->flexibleMonrad?->revision ?? 0);
            }
            DrawAuditLog::record($draw->id, 'workflow_selected', null, $data);
        });
        return redirect()->route('backend.draw.roundrobin.show', $draw);
    }
}
