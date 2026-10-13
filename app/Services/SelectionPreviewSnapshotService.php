<?php

namespace App\Services;

use App\Models\{Event, EventSelectionFinalization, TeamResultSelectionDraft};

class SelectionPreviewSnapshotService
{
    public function drafts(Event $event, ?EventSelectionFinalization $finalization = null): \Illuminate\Support\Collection
    {
        $finalization ??= EventSelectionFinalization::active()->where('event_id', $event->id)->first();
        if (! $finalization) return TeamResultSelectionDraft::where('event_id', $event->id)->get()->keyBy('group_key');
        return collect($finalization->groups)->mapWithKeys(fn ($group, $key) => [$key => new TeamResultSelectionDraft(array_merge($group,
            ['event_id' => $event->id, 'group_key' => $key, 'version' => $group['draft_version'], 'updated_at' => $finalization->finalized_at]))]);
    }
}
