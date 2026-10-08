<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TeamResultSelectionDraft;
use App\Services\TeamResultRankingService;
use App\Services\TeamResultSelectionDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamResultSelectionDraftController extends Controller
{
    public function show(Request $request, Event $event, TeamResultRankingService $rankings): JsonResponse
    {
        $this->authorize('event.manage', $event);
        $input = $request->validate(['group_key' => ['required', 'string', 'max:40']]);
        abort_unless($rankings->setup($event)['groups']->contains('key', $input['group_key']), 404);
        $draft = TeamResultSelectionDraft::where('event_id', $event->id)->where('group_key', $input['group_key'])->first();
        return response()->json(['draft' => $draft ? array_merge($draft->only(['group_key', 'region_ids', 'formats', 'selected_keys', 'reasons', 'version', 'snapshot', 'updated_at']), ['excluded_result_region_ids' => $draft->excluded_result_region_ids ?? []]) : null]);
    }

    public function store(Request $request, Event $event, TeamResultSelectionDraftService $drafts): JsonResponse
    {
        $this->authorize('event.manage', $event);
        $input = $request->validate([
            'group_key' => ['required', 'string', 'max:40'], 'version' => ['required', 'integer', 'min:0'],
            'region_ids' => ['present', 'array', 'max:100'], 'region_ids.*' => ['integer', 'distinct'],
            'excluded_result_region_ids' => ['sometimes', 'array', 'max:100'], 'excluded_result_region_ids.*' => ['integer', 'distinct'],
            'formats' => ['present', 'array', 'max:10'], 'formats.*' => ['string', 'distinct'],
            'selected_keys' => ['present', 'array', 'max:10'], 'selected_keys.*' => ['required', 'string', 'max:100', 'distinct'],
            'reasons' => ['present', 'array', 'max:500'], 'reasons.*' => ['nullable', 'string', 'max:2000'],
        ]);
        $input['version'] = (int) $input['version'];
        $draft = $drafts->save($event, $input, (int) $request->user()->id);
        return response()->json(['draft' => array_merge($draft->only(['group_key', 'region_ids', 'formats', 'selected_keys', 'reasons', 'version', 'snapshot', 'updated_at']), ['excluded_result_region_ids' => $draft->excluded_result_region_ids ?? []])]);
    }
}
