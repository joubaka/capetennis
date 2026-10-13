<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\TeamResultRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamResultSettingsController extends Controller
{
    public function store(Request $request, Event $event, TeamResultRankingService $rankings): JsonResponse
    {
        $this->authorize('event.manage', $event);

        $settings = DB::transaction(function () use ($request, $event, $rankings) {
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $setup = $rankings->setup($locked);
            $regions = $setup['regions']->pluck('id')->all();
            $input = $request->validate([
                'region_ids' => ['present', 'array', 'max:100'],
                'region_ids.*' => ['integer', 'distinct', Rule::in($regions)],
                'excluded_result_region_ids' => ['present', 'array', 'max:100'],
                'excluded_result_region_ids.*' => ['integer', 'distinct', Rule::in($regions)],
                'formats' => ['present', 'array', 'max:10'],
                'formats.*' => ['string', 'distinct', Rule::in($setup['formats']->all())],
            ]);
            $input['region_ids'] = array_map('intval', $input['region_ids']);
            $input['excluded_result_region_ids'] = array_map('intval', $input['excluded_result_region_ids']);
            $input = array_replace($locked->team_result_settings ?? [], $input);
            $locked->team_result_settings = $input;
            $locked->save();

            return $input;
        });

        return response()->json(['settings' => $settings])->header('Cache-Control', 'no-store, private');
    }

    public function storeTeamSize(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $input = $request->validate([
            'team_size' => ['required', 'integer', 'min:1', 'max:12'],
            'reserve_count' => ['required', 'integer', 'min:0', 'max:11'],
            'age' => ['nullable', 'integer', 'min:1', 'max:99'],
            'gender' => ['nullable', 'string', 'in:boys,girls'],
        ]);
        $teamSize = (int) $input['team_size'];
        $reserveCount = (int) $input['reserve_count'];
        if ($teamSize + $reserveCount > 12) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reserve_count' => 'Players per team and reserve places may total no more than 12.']);
        }
        $settings = DB::transaction(function () use ($event, $teamSize, $reserveCount) {
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if(\App\Models\EventSelectionFinalization::active()->where('event_id', $event->id)->exists(), 409, 'Finalized team and reserve places are locked.');
            $settings = array_replace($locked->team_result_settings ?? [], ['team_size' => $teamSize, 'reserve_count' => $reserveCount]);
            $locked->team_result_settings = $settings;
            $locked->save();
            return $settings;
        });
        if ($request->expectsJson()) return response()->json(['settings' => $settings])->header('Cache-Control', 'no-store, private');

        return redirect()->route('backend.selection-preview.show', ['event' => $event, 'age' => $input['age'] ?? null, 'gender' => $input['gender'] ?? null])
            ->with('success', 'Team and reserve places saved for this event. Existing draft selections were kept.')
            ->header('Cache-Control', 'no-store, private');
    }
}
