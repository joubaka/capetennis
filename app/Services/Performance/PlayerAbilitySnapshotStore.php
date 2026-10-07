<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};

/** Derived private materialization, never a canonical player or ranking record. */
class PlayerAbilitySnapshotStore
{
    public const POLICY_VERSION = 1;
    private ?array $loaded = null;

    public function rules(): array
    {
        return ['model' => PlayerSharedAbilityService::VERSION, 'confidence' => SharedAbilityConfidencePolicy::VERSION,
            'policy' => self::POLICY_VERSION, 'played_event_incident_budget' => 1, 'played_half_life_days' => 180,
            'unanchored_prior' => 0.5, 'ordinal_weight' => SharedAbilityModel::ORDINAL_WEIGHT,
            'ordinal_target_span' => SharedAbilityModel::ORDINAL_TARGET_SPAN,
            'main_trial_event_type' => TrialBaselinePolicy::MAIN_TRIAL_EVENT_TYPE,
            'main_trial_type_name' => 'Cavaliers Trials', 'anchor_weight' => TrialBaselinePolicy::ANCHOR_WEIGHT,
            'anchor_target_limit' => TrialBaselinePolicy::TARGET_LIMIT, 'anchor_half_life_days' => 180,
            'confidence_half_life_days' => 90, 'confidence_finish_only_ceiling' => 15,
            'confidence_terms' => ['matches' => [50, 12], 'opponents' => [25, 6], 'events' => [25, 3]],
            'confidence_sparse_caps' => ['under_3' => 25, 'under_5' => 45, 'narrow_diversity' => 65, 'inferred_or_bridge' => 75],
            'confidence_bands' => ['very_low_under' => 20, 'low_under' => 40, 'moderate_under' => 70],
            'timezone' => 'Africa/Johannesburg'];
    }

    public function policyHash(): string { return hash('sha256', json_encode($this->rules(), JSON_THROW_ON_ERROR)); }

    public function current(): array
    {
        if ($this->loaded !== null) { return $this->loaded; }
        $pending = ['cohorts' => [], 'names' => [], 'reason' => 'Ability update pending. A saved nightly snapshot is not yet available.',
            'built_at' => 'Not yet updated', 'snapshot_as_of' => null, 'snapshot_stale' => true];
        if (!Schema::hasTable('player_ability_snapshots')) { return $this->loaded = $pending; }
        $row = DB::table('player_ability_snapshots')->where('snapshot_key', 'current')->first();
        if (!$row) { return $this->loaded = $pending; }
        $pending['built_at'] = $row->built_at;
        if ((int) $row->model_version !== PlayerSharedAbilityService::VERSION || $row->policy_hash !== $this->policyHash()) {
            $pending['reason'] = 'Ability update pending: the saved calculation uses an earlier policy.';
            return $this->loaded = $pending;
        }
        $today = CarbonImmutable::today('Africa/Johannesburg')->toDateString();
        try {
            $manifest = json_decode($row->publication_manifest, true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || !is_array($payload) || !isset($payload['cohorts'], $payload['names'], $payload['built_at'])
                || !array_key_exists('reason', $payload) || !is_array($payload['cohorts']) || !is_array($payload['names'])) {
                throw new \UnexpectedValueException('Invalid saved snapshot shape.');
            }
            foreach ($payload['cohorts'] as $data) {
                if (!is_array($data) || !isset($data['ratings'], $data['components'], $data['players'], $data['baseline'])
                    || !is_array($data['ratings']) || !is_array($data['components']) || !is_array($data['players']) || !is_array($data['baseline'])) {
                    throw new \UnexpectedValueException('Invalid saved cohort.');
                }
                foreach ($data['ratings'] as $id => $rating) {
                    if (!is_array($rating) || !isset($rating['component'], $rating['strength'])
                        || !isset($data['components'][$rating['component']], $data['players'][$id])) {
                        throw new \UnexpectedValueException('Invalid saved player estimate.');
                    }
                }
            }
            if (isset($payload['badge_players']) && !is_array($payload['badge_players'])) { throw new \UnexpectedValueException('Invalid saved badges.'); }
        } catch (\Throwable) {
            $pending['reason'] = 'Ability update pending: the saved snapshot is unreadable.';
            return $this->loaded = $pending;
        }
        try { $validPublication = $this->published($manifest); } catch (\Throwable) { $validPublication = false; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $row->as_of) || $row->as_of > $today || !$validPublication) {
            $pending['reason'] = 'Saved ability estimates are withheld because their source publication or context changed.';
            return $this->loaded = $pending;
        }
        return $this->loaded = $payload + ['snapshot_as_of' => $row->as_of, 'snapshot_stale' => $row->as_of < $today];
    }

    /** Capture positive gates only. New sources/publications wait until the next refresh. */
    public function manifest(array $payload): array
    {
        $events = $payload['source_events'] ?? [];
        $individual = []; $team = [];
        foreach ($payload['source_matches'] ?? [] as $source) {
            [$kind, $id] = explode(':', $source, 2);
            if ($kind === 'match') { $individual[] = (int) $id; } else { $team[] = (int) $id; }
        }
        $manifest = [];
        $capture = function (string $table, array $columns, $query) use (&$manifest): array {
            $rows = $query->select($columns)->limit(50001)->get()->map(fn ($r) => (array) $r)->all();
            if (count($rows) > 50000) { throw new \OverflowException('Snapshot context exceeds its bounded publication check.'); }
            $manifest[$table] = $rows;
            return array_column($rows, 'id');
        };
        $capture('events', ['id', 'published', 'results_published', 'eventType', 'start_date', 'end_date'], DB::table('events')->whereIn('id', $events));
        $draws = $capture('draws', ['id', 'published', 'event_id', 'category_event_id', 'team_scoring_rules', 'drawName', 'team_draw_selection'], DB::table('draws')->whereIn('event_id', $events)->where('published', true));
        $capture('team_ties', ['id', 'published_at', 'status', 'draw_id', 'home_team_id', 'away_team_id'], DB::table('team_ties')->whereIn('draw_id', $draws));
        $capture('eventtypes', ['id', 'name', 'code', 'type'], DB::table('eventtypes')->whereIn('id', array_merge([TrialBaselinePolicy::MAIN_TRIAL_EVENT_TYPE], array_column($manifest['events'], 'eventType'))));
        $fields = $capture('category_events', ['id', 'event_id', 'category_id'], DB::table('category_events')->whereIn('event_id', $events));
        $capture('categories', ['id', 'name'], DB::table('categories')->whereIn('id', array_column($manifest['category_events'], 'category_id')));
        $capture('event_regions', ['id', 'event_id', 'region_id'], DB::table('event_regions')->whereIn('event_id', $events));
        $regionIds = array_column($manifest['event_regions'], 'region_id');
        $capture('team_regions', ['id', 'region_name'], DB::table('team_regions')->whereIn('id', $regionIds));
        $teams = $capture('teams', ['id', 'name', 'year', 'region_id', 'category_event_id'], DB::table('teams')->where(fn ($q) => $q->whereIn('category_event_id', $fields)->orWhere(fn ($q) => $q->whereNull('category_event_id')->whereIn('region_id', $regionIds))));
        $capture('team_players', ['id', 'team_id', 'player_id'], DB::table('team_players')->whereIn('team_id', $teams));
        $capture('team_fixture_players', ['id', 'team_fixture_id', 'team1_id', 'team2_id', 'team1_no_profile_id', 'team2_no_profile_id', 'participant_snapshot'], DB::table('team_fixture_players')->whereIn('team_fixture_id', $team));
        $capture('team_fixtures', ['id', 'draw_id', 'team_tie_id', 'region1', 'region2', 'fixture_type', 'rubber_code', 'age', 'numSets'], DB::table('team_fixtures')->whereIn('id', $team));
        $capture('fixtures', ['id', 'draw_id', 'registration1_id', 'registration2_id'], DB::table('fixtures')->whereIn('id', $individual));
        $capture('category_event_registrations', ['id', 'category_event_id', 'registration_id'], DB::table('category_event_registrations')->whereIn('category_event_id', $fields));
        $registrationIds = array_merge(array_column($manifest['category_event_registrations'], 'registration_id'), array_column($manifest['fixtures'], 'registration1_id'), array_column($manifest['fixtures'], 'registration2_id'));
        $manifest['player_registrations'] = DB::table('player_registrations')->whereIn('registration_id', array_unique($registrationIds))->limit(50001)->get(['registration_id', 'player_id'])->map(fn ($r) => (array) $r)->all();
        if (count($manifest['player_registrations']) > 50000) { throw new \OverflowException('Snapshot registrations exceed the context limit.'); }
        return $manifest;
    }

    public function published(array $manifest): bool
    {
        foreach ($manifest as $table => $captured) {
            if (!in_array($table, ['events', 'draws', 'team_ties', 'eventtypes', 'category_events', 'categories', 'teams', 'team_regions', 'event_regions', 'team_players', 'team_fixture_players', 'fixtures', 'team_fixtures', 'category_event_registrations', 'player_registrations'], true)) { return false; }
            $groupColumn = match ($table) {
                'team_fixture_players' => 'team_fixture_id', 'category_event_registrations' => 'category_event_id',
                'category_events' => 'event_id', default => null,
            };
            if ($groupColumn) {
                $oldGroups = collect($captured)->groupBy($groupColumn);
                foreach (array_chunk(array_unique(array_column($captured, $groupColumn)), 500) as $groups) {
                    $rows = DB::table($table)->whereIn($groupColumn, $groups)->limit(50001)->get(['id', $groupColumn, ...($table === 'category_events' ? ['category_id'] : [])]);
                    if ($rows->count() > 50000) { return false; }
                    foreach ($groups as $group) {
                        $old = $oldGroups[$group];
                        $new = $rows->where($groupColumn, $group);
                        if ($table === 'category_events') {
                            $new = $new->whereIn('category_id', $old->pluck('category_id')->all());
                        }
                        if ($old->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all() !== $new->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all()) { return false; }
                    }
                }
            }
            if ($table === 'player_registrations') {
                $groups = collect($captured)->groupBy('registration_id');
                foreach (array_chunk($groups->keys()->all(), 500) as $ids) {
                    $rows = DB::table($table)->whereIn('registration_id', $ids)->limit(50001)->get(['registration_id', 'player_id'])->groupBy('registration_id');
                    if ($rows->sum(fn ($group) => $group->count()) > 50000) { return false; }
                    foreach ($ids as $id) {
                        $oldPlayers = $groups[$id]->pluck('player_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
                        $newPlayers = ($rows[$id] ?? collect())->pluck('player_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
                        if ($oldPlayers !== $newPlayers) { return false; }
                    }
                }
                continue;
            }
            foreach (array_chunk($captured, 500) as $chunk) {
                $rows = DB::table($table)->whereIn('id', array_column($chunk, 'id'))->get(array_keys($chunk[0]))->keyBy('id');
                foreach ($chunk as $old) {
                    $current = isset($rows[$old['id']]) ? (array) $rows[$old['id']] : null;
                    if (!$current) { return false; }
                    foreach ($old as $key => $value) {
                        if ($table === 'team_ties' && $key === 'published_at' && !$value) { continue; }
                        // Adding a publication is allowed; revoking a captured positive gate is not.
                        if ($table === 'events' && in_array($key, ['published', 'results_published'], true) && !$value) { continue; }
                        if ((string) $current[$key] !== (string) $value) { return false; }
                    }
                }
            }
        }
        return true;
    }

    public function replace(array $payload, CarbonImmutable $asOf, string $fingerprint, array $manifest): void
    {
        foreach ($payload['cohorts'] as $cohort) {
            foreach ($cohort['components'] as $component) {
                if (!$component['converged']) { throw new \RuntimeException('Incomplete model cannot replace the saved snapshot.'); }
            }
        }
        if ($asOf->toDateString() > CarbonImmutable::today('Africa/Johannesburg')->toDateString()) { throw new \RuntimeException('Future snapshots cannot be persisted.'); }
        if ($payload['reason'] || !$this->published($manifest)) { throw new \RuntimeException('Snapshot sources are not ready for replacement.'); }
        DB::transaction(function () use ($payload, $asOf, $fingerprint, $manifest) {
            $existing = DB::table('player_ability_snapshots')->where('snapshot_key', 'current')->lockForUpdate()->first();
            if ($existing && ((int) $existing->model_version > PlayerSharedAbilityService::VERSION || $existing->as_of > $asOf->toDateString() || ($existing->as_of === $asOf->toDateString() && $existing->built_at > $payload['built_at']))) {
                throw new \RuntimeException('An older calculation cannot replace the last successful snapshot.');
            }
            if (!$this->published($manifest)) { throw new \RuntimeException('Source publication changed before replacement.'); }
            DB::table('player_ability_snapshots')->updateOrInsert(['snapshot_key' => 'current'], [
                'model_version' => PlayerSharedAbilityService::VERSION, 'policy_hash' => $this->policyHash(),
                'as_of' => $asOf->toDateString(), 'built_at' => $payload['built_at'],
                'source_fingerprint' => $fingerprint, 'publication_fingerprint' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)),
                'publication_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
                'weighting_rules' => json_encode($this->rules(), JSON_THROW_ON_ERROR), 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
        $this->loaded = null;
    }
}
