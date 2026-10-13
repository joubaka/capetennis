<?php

namespace App\Services;

use App\Models\{Event, EventSelectionFinalization, TeamResultSelectionDraft, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventSelectionFinalizationService
{
    public function finalize(Event $event, User $actor, array $expected, int $expectedSize, int $expectedReserves): EventSelectionFinalization
    {
        abort_unless($actor->can('event.manage', $event), 403);
        $expected = array_map('intval', $expected); ksort($expected);
        return DB::transaction(function () use ($event, $actor, $expected, $expectedSize, $expectedReserves) {
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $existing = EventSelectionFinalization::where('event_id', $event->id)->first();
            if ($existing) {
                $versions = $existing->draft_versions; ksort($versions);
                abort_unless($versions === $expected && (int) $existing->team_size === $expectedSize && (int) $existing->reserve_count === $expectedReserves, 409, 'This event has already been finalized from different reviewed settings or draft versions.');
                return $existing;
            }
            $drafts = TeamResultSelectionDraft::where('event_id', $event->id)->orderBy('group_key')->lockForUpdate()->get();
            $versions = $drafts->mapWithKeys(fn ($draft) => [$draft->group_key => (int) $draft->version])->all(); ksort($versions);
            abort_unless($versions !== [] && $versions === $expected, 409, 'Saved teams changed. Reload the preview and review every saved group before finalizing.');
            $size = max(1, min(12, (int) ($locked->team_result_settings['team_size'] ?? 10)));
            $reserves = max(0, min(12 - $size, (int) ($locked->team_result_settings['reserve_count'] ?? 2)));
            abort_unless($size === $expectedSize && $reserves === $expectedReserves, 409, 'Team or reserve places changed. Reload the preview and review the settings before finalizing.');
            $groups = [];
            foreach ($drafts as $draft) {
                $label = 'u/'.str_replace('-', ' ', $draft->group_key);
                $selected = array_map('strval', $draft->selected_keys ?? []);
                $snapshot = collect($draft->snapshot ?? []);
                $keys = $snapshot->map(fn ($row) => (string) $row['id'])->all();
                if ($selected === [] || count($selected) > $size + $reserves || count($selected) !== count(array_unique($selected)) || array_diff($selected, $keys)) {
                    throw ValidationException::withMessages(['versions' => $label.' must have selected players within the configured '.$size.' main and '.$reserves.' reserve places. No teams were finalized.']);
                }
                $ordered = $snapshot->sortBy(fn ($row) => $row['draft_position'] ?? $row['position'] ?? PHP_INT_MAX)->values();
                $selectedOrder = $ordered->filter(fn ($row) => in_array((string) $row['id'], $selected, true))->map(fn ($row) => (string) $row['id'])->values()->all();
                $statuses = [];
                foreach ($selectedOrder as $position => $key) $statuses[$key] = ['position' => $position + 1, 'status' => $position < $size ? 'main' : 'reserve'];
                $groups[$draft->group_key] = $draft->only(['region_ids', 'formats', 'excluded_result_region_ids', 'reasons', 'snapshot']) +
                    ['draft_version' => (int) $draft->version, 'selected_keys' => $selectedOrder, 'selection_statuses' => $statuses, 'team_size' => $size, 'reserve_count' => $reserves];
            }
            $fingerprint = hash('sha256', json_encode([$versions, $size, $reserves, $groups], JSON_THROW_ON_ERROR));
            return EventSelectionFinalization::create(['event_id' => $event->id, 'finalized_by' => $actor->id, 'draft_versions' => $versions,
                'groups' => $groups, 'fingerprint' => $fingerprint, 'team_size' => $size, 'reserve_count' => $reserves, 'finalized_at' => now()]);
        });
    }
}
