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
        $setup = $this->rankings->setup($event);
        abort_unless($setup['groups']->contains('key', $input['group_key']), 404);
        $regions = array_map('intval', $input['region_ids']);
        if (array_diff($regions, $setup['regions']->pluck('id')->all()) || array_diff($input['formats'], $setup['formats']->all())) {
            throw ValidationException::withMessages(['setup' => 'Choose regions and singles formats belonging to this event.']);
        }
        $ranking = $this->rankings->ranking($event, $input['group_key'], $regions, $input['formats']);
        $candidates = $ranking->keyBy(fn ($row) => (string) $row['id']);
        $selected = array_map('strval', $input['selected_keys']);
        if (count($selected) !== count(array_unique($selected)) || array_diff($selected, $candidates->keys()->map(fn ($key) => (string) $key)->all())) {
            throw ValidationException::withMessages(['selected_keys' => 'Choose distinct ranked players from this event and setup.']);
        }
        $reasons = [];
        foreach ($input['reasons'] ?? [] as $key => $reason) {
            if (! $candidates->has((string) $key)) {
                throw ValidationException::withMessages(['reasons' => 'Reasons must refer to ranked players in this setup.']);
            }
            if (trim((string) $reason) !== '') $reasons[(string) $key] = trim((string) $reason);
        }
        foreach ($ranking as $row) {
            $included = in_array((string) $row['id'], $selected, true);
            if (($included && (! $row['suggested'] || $row['cutoff_tie'])) || (! $included && $row['suggested'])) {
                if (empty($reasons[(string) $row['id']])) {
                    throw ValidationException::withMessages(['reasons' => 'Record a reason for each departure from the suggested selection and each selected cutoff tie.']);
                }
            }
        }
        // Selection changes membership; its displayed order always follows the weighted evidence.
        $selected = $ranking->filter(fn ($row) => in_array((string) $row['id'], $selected, true))
            ->map(fn ($row) => (string) $row['id'])->values()->all();

        return DB::transaction(function () use ($event, $input, $actorId, $ranking, $regions, $selected, $reasons) {
            // The event lock also serializes creation when no draft row exists yet.
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $draft = TeamResultSelectionDraft::where('event_id', $event->id)->where('group_key', $input['group_key'])->lockForUpdate()->first();
            abort_if((int) ($draft?->version ?? 0) !== $input['version'], 409, 'This draft changed. Reload it before saving.');
            $draft ??= new TeamResultSelectionDraft(['event_id' => $event->id, 'group_key' => $input['group_key']]);
            $draft->fill(['version' => $input['version'] + 1, 'region_ids' => $regions,
                'formats' => $input['formats'], 'selected_keys' => $selected, 'reasons' => $reasons,
                'snapshot' => $ranking->values()->all(), 'updated_by' => $actorId])->save();
            DB::table('team_result_selection_revisions')->insert([
                'draft_id' => $draft->id, 'version' => $draft->version,
                'evidence' => json_encode($draft->only(['region_ids', 'formats', 'selected_keys', 'reasons', 'snapshot']), JSON_THROW_ON_ERROR),
                'created_by' => $actorId, 'created_at' => now(),
            ]);
            return $draft;
        });
    }
}
