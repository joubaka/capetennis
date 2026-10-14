<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TeamResultSelectionDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamResultSelectionDraftService
{
    public function __construct(private TeamResultRankingService $rankings) {}

    public function save(Event $event, array $input, int $actorId): TeamResultSelectionDraft
    {
        $event = $event->fresh();
        $criteriaFingerprint = app(TeamSelectionCriteria::class)->fingerprint($event);
        if (isset($event->team_result_settings['criteria_preset']) || isset($input['criteria_fingerprint'])) {
            abort_unless(hash_equals($criteriaFingerprint, $input['criteria_fingerprint'] ?? ''), 409, 'Selection criteria changed. Reload the ranking before saving.');
        }
        $setup = $this->rankings->setup($event);
        abort_unless($setup['groups']->contains('key', $input['group_key']), 404);
        $regions = array_map('intval', $input['region_ids']);
        $excludedResultRegions = array_map('intval', $input['excluded_result_region_ids'] ?? []);
        if (array_diff($regions, $setup['regions']->pluck('id')->all()) || array_diff($excludedResultRegions, $setup['regions']->pluck('id')->all()) || array_diff($input['formats'], $setup['formats']->all())) {
            throw ValidationException::withMessages(['setup' => 'Choose regions and singles formats belonging to this event.']);
        }
        if (app(TeamSelectionCriteria::class)->isPlatteland($event)) {
            $excludedResultRegions = app(TeamSelectionCriteria::class)->context($event, $excludedResultRegions)['excluded_result_region_ids'];
            $input['formats'] = ['singles', 'reverse_singles'];
        }
        $ranking = $this->rankings->ranking($event, $input['group_key'], $regions, $input['formats'], $excludedResultRegions);
        $candidates = $ranking->keyBy(fn ($row) => (string) $row['id']);
        $selected = array_map('strval', $input['selected_keys']);
        if (count($selected) > 12 || count($selected) !== count(array_unique($selected)) || array_diff($selected, $candidates->keys()->map(fn ($key) => (string) $key)->all())) {
            throw ValidationException::withMessages(['selected_keys' => 'Choose distinct ranked players from this event and setup.']);
        }
        $reasons = [];
        foreach ($input['reasons'] ?? [] as $key => $reason) {
            if (! $candidates->has((string) $key)) {
                throw ValidationException::withMessages(['reasons' => 'Comments must refer to ranked players in this setup.']);
            }
            if (trim((string) $reason) !== '') $reasons[(string) $key] = trim((string) $reason);
        }
        $calculatedOrder = $ranking->map(fn ($row) => (string) $row['id'])->values()->all();
        $draftOrder = array_values(array_map('strval', $input['draft_order'] ?? $calculatedOrder));
        if (count($draftOrder) !== count($calculatedOrder) || count($draftOrder) !== count(array_unique($draftOrder)) || array_diff($draftOrder, $calculatedOrder)) {
            throw ValidationException::withMessages(['draft_order' => 'Include each ranked player in this setup exactly once.']);
        }
        $selected = array_values(array_filter($draftOrder, fn ($key) => in_array($key, $selected, true)));
        // Keep calculated evidence order and values; only add the reviewed draft position.
        $positions = array_flip($draftOrder);
        $ranking = $ranking->map(fn ($row) => $row + ['draft_position' => $positions[(string) $row['id']] + 1]);

        return DB::transaction(function () use ($event, $input, $actorId, $ranking, $regions, $excludedResultRegions, $selected, $reasons, $criteriaFingerprint) {
            // The event lock also serializes creation when no draft row exists yet.
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($criteriaFingerprint, app(TeamSelectionCriteria::class)->fingerprint($locked)), 409, 'Selection criteria changed. Reload the ranking before saving.');
            abort_if(\App\Models\EventSelectionFinalization::active()->where('event_id', $event->id)->exists(), 409, 'This event ranking is finalized. Draft order and selections are locked.');
            $draft = TeamResultSelectionDraft::where('event_id', $event->id)->where('group_key', $input['group_key'])->lockForUpdate()->first();
            abort_if((int) ($draft?->version ?? 0) !== $input['version'], 409, 'This draft changed. Reload it before saving.');
            $draft ??= new TeamResultSelectionDraft(['event_id' => $event->id, 'group_key' => $input['group_key']]);
            $draft->fill(['version' => $input['version'] + 1, 'region_ids' => $regions,
                'excluded_result_region_ids' => $excludedResultRegions,
                'formats' => $input['formats'], 'selected_keys' => $selected, 'reasons' => $reasons,
                'snapshot' => $ranking->values()->all(), 'updated_by' => $actorId])->save();
            DB::table('team_result_selection_revisions')->insert([
                'draft_id' => $draft->id, 'version' => $draft->version,
                'evidence' => json_encode($draft->only(['region_ids', 'excluded_result_region_ids', 'formats', 'selected_keys', 'reasons', 'snapshot']), JSON_THROW_ON_ERROR),
                'created_by' => $actorId, 'created_at' => now(),
            ]);
            return $draft;
        });
    }
}
