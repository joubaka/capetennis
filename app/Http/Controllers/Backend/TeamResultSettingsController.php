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
            $locked->team_result_settings = $input;
            $locked->save();

            return $input;
        });

        return response()->json(['settings' => $settings])->header('Cache-Control', 'no-store, private');
    }
}
