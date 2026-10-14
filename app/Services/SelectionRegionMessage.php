<?php

namespace App\Services;

use App\Models\{Event, EventSelectionFinalization, User};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SelectionRegionMessage
{
    public function regions(Event $event, User $actor)
    {
        Gate::forUser($actor)->authorize('event.manage', $event);
        abort_unless(app(EventCommunicationService::class)->managesWholeEvent($event, $actor), 403);
        $ids = app(EventTeamScope::class)->query($event)->pluck('region_id')->unique();
        return app(EventCommunicationService::class)->regions($event, $actor)->whereIn('region_id', $ids)->values();
    }

    public function draft(Event $event): array
    {
        $final = EventSelectionFinalization::active()->where('event_id', $event->id)->first();
        $drafts = app(SelectionPreviewSnapshotService::class)->drafts($event, $final)->sortBy('group_key');
        abort_if($drafts->isEmpty(), 422, 'Save selected teams before composing this email.');
        $size = $final?->team_size ?? (int) ($event->team_result_settings['team_size'] ?? 10);
        $reserves = $final?->reserve_count ?? (int) ($event->team_result_settings['reserve_count'] ?? 2);
        $lines = [$final ? 'Finalized selected teams and reserves' : 'Draft selected teams and reserves — subject to change'];
        foreach ($drafts as $key => $draft) {
            $snapshot = collect($draft->snapshot)->keyBy(fn ($row) => (string) $row['id']);
            $selected = collect($draft->selected_keys)->map(fn ($id) => $snapshot->get((string) $id))->filter()
                ->sortBy(fn ($row) => $row['draft_position'] ?? $row['position'] ?? PHP_INT_MAX)->values();
            abort_if($selected->count() !== count($draft->selected_keys ?? []) || $selected->count() > $size + $reserves, 422, 'Review missing or over-capacity selections before emailing.');
            $lines[] = '';
            $lines[] = 'u/'.str_replace('-', ' ', $key);
            $lines[] = 'Main team';
            foreach ($selected->take($size) as $i => $row) $lines[] = ($i + 1).'. '.$row['name'].' ('.($row['region'] ?? implode(', ', $row['teams'] ?? [])).')';
            if ($selected->isEmpty()) $lines[] = 'No saved selections.';
            $lines[] = 'Reserves';
            foreach ($selected->slice($size)->values() as $i => $row) $lines[] = 'Reserve '.($i + 1).': '.$row['name'].' ('.($row['region'] ?? implode(', ', $row['teams'] ?? [])).')';
            if ($selected->count() <= $size) $lines[] = 'No reserves selected.';
        }
        return ['subject' => $event->name.' — Selected teams and reserves',
            'body' => "Dear players and parents,\n\nPlease review the selected teams and reserves for {$event->name} below.\n\n".implode("\n", $lines)."\n\nKind regards,\nCape Tennis",
            'selection_fingerprint' => hash('sha256', json_encode([$size, $reserves, array_slice($lines, 1)], JSON_THROW_ON_ERROR))];
    }

    public function audience(Event $event, User $actor, array $options): array
    {
        $regions = $this->regions($event, $actor);
        $ids = collect($options['selection_region_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        abort_if($ids->isEmpty() || $ids->count() > 500, 422);
        abort_unless($ids->diff($regions->pluck('region_id'))->isEmpty(), 403);
        if (! hash_equals($this->draft($event)['selection_fingerprint'], $options['selection_fingerprint'] ?? '')) {
            throw ValidationException::withMessages(['preview' => 'Saved teams or reserve places changed. Reopen the email and review a fresh preview.']);
        }
        return app(EventCommunicationService::class)->rosterEntries($event, $ids->all(), true)->all();
    }
}
