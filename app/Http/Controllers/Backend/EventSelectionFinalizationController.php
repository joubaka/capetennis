<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventSelectionFinalizationService;
use Illuminate\Http\Request;

class EventSelectionFinalizationController extends Controller
{
    public function reopen(Request $request, Event $event, EventSelectionFinalizationService $finalizations)
    {
        $this->authorize('event.manage', $event);
        $input = $request->validate(['finalization_id' => 'required|integer|min:1', 'reason' => 'required|string|max:1000', 'confirm_unlock' => 'accepted',
            'age' => 'nullable|integer|min:1|max:99', 'gender' => 'nullable|in:boys,girls']);
        $finalizations->reopen($event, $request->user(), (int) $input['finalization_id'], $input['reason']);
        if ($request->expectsJson()) return response()->json(['unlocked' => true])->header('Cache-Control', 'no-store, private');
        return redirect()->route('backend.selection-preview.show', ['event' => $event, 'age' => $input['age'] ?? null, 'gender' => $input['gender'] ?? null])
            ->with('success', 'All saved teams unlocked for editing. The finalized snapshot is retained in history.')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Event $event, EventSelectionFinalizationService $finalizations)
    {
        $this->authorize('event.manage', $event);
        $input = $request->validate(['versions' => ['required', 'array', 'min:1', 'max:198'], 'versions.*' => ['required', 'integer', 'min:1'],
            'team_size' => ['required', 'integer', 'min:1', 'max:12'], 'reserve_count' => ['required', 'integer', 'min:0', 'max:11'],
            'age' => ['nullable', 'integer', 'min:1', 'max:99'], 'gender' => ['nullable', 'string', 'in:boys,girls']]);
        $final = $finalizations->finalize($event, $request->user(), $input['versions'], (int) $input['team_size'], (int) $input['reserve_count']);
        if ($request->expectsJson()) return response()->json(['finalization_id' => $final->id, 'finalized_groups' => array_keys($final->groups)])->header('Cache-Control', 'no-store, private');
        return redirect()->route('backend.selection-preview.show', ['event' => $event, 'age' => $input['age'] ?? null, 'gender' => $input['gender'] ?? null])
            ->with('success', 'All saved teams finalized. The ranking is frozen and remains private.')->header('Cache-Control', 'no-store, private');
    }
}
