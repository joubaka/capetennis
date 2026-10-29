<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};

/** Derived private materialization, never a canonical player or ranking record. */
class PlayerAbilitySnapshotStore
{
    public const POLICY_VERSION = 3;
    private const PUBLICATION_CHECK_CHUNK = 2000;
    private ?array $loaded = null;

    public function rules(): array
    {
        return ['model' => PlayerSharedAbilityService::VERSION, 'confidence' => SharedAbilityConfidencePolicy::VERSION,
            'all_age' => ['version' => AllAgeRatingModel::VERSION, 'regularization' => AllAgeRatingModel::REGULARIZATION, 'window_days' => AllAgeRatingModel::WINDOW_DAYS, 'recent_window_days' => AllAgeRatingModel::RECENT_WINDOW_DAYS,
                'evidence' => 'played singles only; no age offsets, ordinal fields or cohort trial anchors',
                'reference' => 'historical component containing the largest recent connected backbone; no reference without recent matches',
                'historical_connection' => 'minimax played-link age from the recent backbone; edge age is freshest actual result between opponents',
                'historical_connection_confidence_cap' => AllAgeRatingModel::HISTORICAL_CONFIDENCE_CAP,
                'format' => 'explicit standard/yellow or conventional U11+ legacy assumption; modified and unresolved young formats excluded'],
            'all_age_team_reader' => ['version' => 1, 'demographics' => 'labels do not establish identity or restrict shared singles',
                'cross_context_format' => 'explicit standard/yellow required', 'source_gates' => 'independent pre-cohort identity, event-side, roster, singles, score, date and publication checks'],
            'policy' => self::POLICY_VERSION, 'played_event_incident_budget' => 1, 'played_half_life_days' => 180,
            'unanchored_prior' => 0.5, 'ordinal_weight' => SharedAbilityModel::ORDINAL_WEIGHT,
            'played_margin_policy' => PlayedMatchMarginPolicy::VERSION, 'played_margin_floor' => 0.75,
            'imported_match_identity_policy' => ImportedMatchIdentityResolver::VERSION,
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

    public function matches(CarbonImmutable $asOf, string $fingerprint): bool
    {
        return DB::table('player_ability_snapshots')->where('snapshot_key', 'current')
            ->where('as_of', $asOf->toDateString())->where('source_fingerprint', $fingerprint)
            ->where('model_version', PlayerSharedAbilityService::VERSION)->where('policy_hash', $this->policyHash())->exists();
    }

    public function current(): array
    {
        if ($this->loaded !== null) { return $this->loaded; }
        $pending = ['cohorts' => [], 'names' => [], 'reason' => 'Ability update pending. A saved snapshot is not yet available.',
            'built_at' => 'Not yet updated', 'snapshot_as_of' => null, 'snapshot_stale' => true, 'snapshot_version' => null];
        if (!Schema::hasTable('player_ability_snapshots')) { return $this->loaded = $pending; }
        $row = DB::table('player_ability_snapshots')->where('snapshot_key', 'current')->first();
        if (!$row) { return $this->loaded = $pending; }
        $pending['built_at'] = $row->built_at;
        $pending['snapshot_version'] = hash('sha256', json_encode([$row->built_at, $row->as_of, $row->source_fingerprint,
            $row->publication_fingerprint, $row->policy_hash, $row->model_version], JSON_THROW_ON_ERROR));
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
            if (array_key_exists('all_age', $payload)) {
                $this->assertAllAgeGraph($payload['all_age'], $row->as_of);
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
        return $this->loaded = $payload + ['snapshot_as_of' => $row->as_of, 'snapshot_stale' => $row->as_of < $today,
            'snapshot_version' => $pending['snapshot_version']];
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
        $capture('team_players', ['id', 'team_id', 'player_id', 'rank'], DB::table('team_players')->whereIn('team_id', $teams));
        $capture('no_profile_team_players', ['id', 'team_id', 'player_profile', 'rank'], DB::table('no_profile_team_players')->whereIn('team_id', $teams));
        $capture('team_fixture_players', ['id', 'team_fixture_id', 'team1_id', 'team2_id', 'team1_no_profile_id', 'team2_no_profile_id', 'participant_snapshot'], DB::table('team_fixture_players')->whereIn('team_fixture_id', $team));
        $capture('team_fixtures', ['id', 'draw_id', 'team_tie_id', 'region1', 'region2', 'fixture_type', 'rubber_code', 'age', 'numSets', 'scheduled_at', 'venue_id', 'court_label'], DB::table('team_fixtures')->whereIn('id', $team));
        if (Schema::hasTable('historical_identity_recoveries')) {
            $recoveries = DB::table('historical_identity_recoveries')->whereIn('fixture_player_id', array_column($manifest['team_fixture_players'], 'id'));
            $capture('historical_identity_recoveries', ['*'], $recoveries);
            $manifest['historical_recovery_sources'] = app(HistoricalIdentityRecoveryService::class)->context($manifest['historical_identity_recoveries']);
        }
        $capture('fixtures', ['id', 'draw_id', 'registration1_id', 'registration2_id'], DB::table('fixtures')->whereIn('id', $individual));
        $capture('category_event_registrations', ['id', 'category_event_id', 'registration_id'], DB::table('category_event_registrations')->whereIn('category_event_id', $fields));
        $registrationIds = array_merge(array_column($manifest['category_event_registrations'], 'registration_id'), array_column($manifest['fixtures'], 'registration1_id'), array_column($manifest['fixtures'], 'registration2_id'));
        $manifest['player_registrations'] = DB::table('player_registrations')->whereIn('registration_id', array_unique($registrationIds))->limit(50001)->get(['registration_id', 'player_id'])->map(fn ($r) => (array) $r)->all();
        if (count($manifest['player_registrations']) > 50000) { throw new \OverflowException('Snapshot registrations exceed the context limit.'); }
        if (isset($payload['all_age'])) { $manifest['all_age_format_context'] = $this->formatContext($events, $draws, $individual); }
        return $manifest;
    }

    /** Recapture whole scoped groups, including empty groups, so newly declared ball types invalidate assumptions. */
    private function formatContext(array $events, array $draws, array $individual): array
    {
        $read = function (string $table, string $column, array $ids): array {
            if (!Schema::hasTable($table)) { return []; }
            $rows = DB::table($table)->whereIn($column, $ids)->orderBy('id')->limit(50001)->get()->map(fn ($row) => (array) $row)->all();
            if (count($rows) > 50000) { throw new \OverflowException('All-age format context exceeds its safe limit.'); }
            return $rows;
        };
        sort($events); sort($draws); sort($individual);
        return ['event_ids' => $events, 'draw_ids' => $draws, 'fixture_ids' => $individual,
            'courts' => $read('event_venue_courts', 'event_id', $events),
            'allocations' => $read('draw_venue_court_allocations', 'draw_id', $draws),
            'schedules' => $read('order_of_plays', 'fixture_id', $individual)];
    }

    public function published(array $manifest): bool
    {
        if (!empty($manifest['historical_identity_recoveries']) && !is_array($manifest['historical_recovery_sources'] ?? null)) { return false; }
        foreach ($manifest as $table => $captured) {
            if ($table === 'all_age_format_context') {
                if (!is_array($captured) || !isset($captured['event_ids'], $captured['draw_ids'], $captured['fixture_ids'])
                    || $this->canonicalRecoveryContext($captured) !== $this->canonicalRecoveryContext($this->formatContext($captured['event_ids'], $captured['draw_ids'], $captured['fixture_ids']))) { return false; }
                continue;
            }
            if ($table === 'historical_recovery_sources') {
                // Recapture complete scoped groups, including previously empty rosters.
                // Normalize object key order only; preserve scalar types and list order.
                if ($this->canonicalRecoveryContext($captured) !== $this->canonicalRecoveryContext(app(HistoricalIdentityRecoveryService::class)->context($manifest['historical_identity_recoveries'] ?? []))) { return false; }
                continue;
            }
            if (!in_array($table, ['events', 'draws', 'team_ties', 'eventtypes', 'category_events', 'categories', 'teams', 'team_regions', 'event_regions', 'team_players', 'no_profile_team_players', 'team_fixture_players', 'fixtures', 'team_fixtures', 'category_event_registrations', 'player_registrations', 'historical_identity_recoveries'], true)) { return false; }
            if ($table === 'historical_identity_recoveries') {
                if (!Schema::hasTable($table)) { return false; }
            }
            $groupColumn = match ($table) {
                'team_fixture_players' => 'team_fixture_id', 'category_event_registrations' => 'category_event_id',
                'category_events' => 'event_id', 'team_players', 'no_profile_team_players' => 'team_id', default => null,
            };
            if ($groupColumn) {
                $oldGroups = collect($captured)->groupBy($groupColumn);
                foreach (array_chunk(array_unique(array_column($captured, $groupColumn)), self::PUBLICATION_CHECK_CHUNK) as $groups) {
                    $rows = DB::table($table)->whereIn($groupColumn, $groups)->limit(50001)->get(['id', $groupColumn, ...($table === 'category_events' ? ['category_id'] : [])]);
                    if ($rows->count() > 50000) { return false; }
                    $currentGroups = $rows->groupBy($groupColumn);
                    foreach ($groups as $group) {
                        $old = $oldGroups[$group];
                        $new = $currentGroups->get($group, collect());
                        if ($table === 'category_events') {
                            $new = $new->whereIn('category_id', $old->pluck('category_id')->all());
                        }
                        if ($old->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all() !== $new->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all()) { return false; }
                    }
                }
            }
            if ($table === 'player_registrations') {
                $groups = collect($captured)->groupBy('registration_id');
                foreach (array_chunk($groups->keys()->all(), self::PUBLICATION_CHECK_CHUNK) as $ids) {
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
            foreach (array_chunk($captured, self::PUBLICATION_CHECK_CHUNK) as $chunk) {
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

    private function canonicalRecoveryContext(mixed $value): mixed
    {
        if (!is_array($value)) { return $value; }
        foreach ($value as $key => $item) { $value[$key] = $this->canonicalRecoveryContext($item); }
        if (!array_is_list($value)) { ksort($value, SORT_STRING); }
        return $value;
    }

    /** Validate the shared projection on both read and replacement; malformed data fails closed. */
    public function assertAllAgeGraph(mixed $shared, ?string $asOf = null): void
    {
        if (!is_array($shared) || !is_array($shared['ratings'] ?? null) || !is_array($shared['components'] ?? null) || !is_array($shared['players'] ?? null)
            || !array_key_exists('reference_component', $shared)) { throw new \UnexpectedValueException('Invalid all-age snapshot.'); }
        $reference = $shared['reference_component'];
        if ($shared['components'] === []) {
            if ($reference !== null || $shared['ratings'] !== [] || $shared['players'] !== []) { throw new \UnexpectedValueException('Invalid empty all-age graph.'); }
            return;
        }
        $policy = $shared['policy'] ?? [];
        if (($policy['version'] ?? null) !== AllAgeRatingModel::VERSION || ($policy['regularization'] ?? null) !== AllAgeRatingModel::REGULARIZATION || ($policy['window_days'] ?? null) !== AllAgeRatingModel::WINDOW_DAYS
            || ($policy['recent_window_days'] ?? null) !== AllAgeRatingModel::RECENT_WINDOW_DAYS || !is_string($policy['as_of'] ?? null)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $policy['as_of']) || ($asOf !== null && $policy['as_of'] !== $asOf)) { throw new \UnexpectedValueException('Invalid saved all-age connection policy.'); }
        $policyDate = CarbonImmutable::createFromFormat('!Y-m-d', $policy['as_of']);
        if ($policyDate->toDateString() !== $policy['as_of'] || !array_key_exists('recent_backbone', $shared)) { throw new \UnexpectedValueException('Invalid all-age connection date or backbone.'); }
        $backbone = $shared['recent_backbone'];
        if ($reference === null) {
            if ($backbone !== null) { throw new \UnexpectedValueException('A recent backbone requires a shared reference.'); }
        } elseif (!is_string($reference) || !isset($shared['components'][$reference]) || !is_array($backbone)
            || !is_array($backbone['members'] ?? null) || !array_is_list($backbone['members']) || count($backbone['members']) < 2
            || ($backbone['window_days'] ?? null) !== AllAgeRatingModel::RECENT_WINDOW_DAYS || ($backbone['as_of'] ?? null) !== $policy['as_of']) { throw new \UnexpectedValueException('Invalid shared reference component or recent backbone.'); }
        $members = [];
        foreach ($shared['components'] as $componentId => $component) {
            if (!is_array($component) || ($component['converged'] ?? null) !== true || !is_array($component['members'] ?? null)
                || !array_is_list($component['members']) || count($component['members']) < 2) { throw new \UnexpectedValueException('Incomplete or invalid all-age component.'); }
            foreach ($component['members'] as $id) {
                if (!is_int($id) || $id < 1 || isset($members[$id]) || !isset($shared['ratings'][$id], $shared['players'][$id]) || !is_array($shared['players'][$id])) { throw new \UnexpectedValueException('Invalid all-age component membership.'); }
                $members[$id] = $componentId;
            }
        }
        if (count($members) !== count($shared['ratings']) || count($members) !== count($shared['players'])) { throw new \UnexpectedValueException('All-age graph membership is inconsistent.'); }
        $backboneMembers = array_fill_keys($backbone['members'] ?? [], true);
        if ($backbone) {
            if (count($backboneMembers) !== count($backbone['members']) || ($backbone['component'] ?? null) !== substr(hash('sha256', AllAgeRatingModel::COHORT.'|'.implode(',', $backbone['members'])), 0, 12)) { throw new \UnexpectedValueException('Invalid recent backbone membership.'); }
            foreach ($backbone['members'] as $id) {
                if (!is_int($id) || ($members[$id] ?? null) !== $reference) { throw new \UnexpectedValueException('The recent backbone is outside the shared reference.'); }
            }
        }
        foreach ($shared['ratings'] as $id => $rating) {
            $component = $members[$id] ?? null;
            $comparable = $component === $reference;
            if (!is_array($rating) || ($rating['rating_scope'] ?? null) !== 'all_age' || ($rating['cohort'] ?? null) !== AllAgeRatingModel::COHORT
                || ($rating['component'] ?? null) !== $component || ($rating['comparison_group'] ?? null) !== $component
                || ($rating['comparable'] ?? null) !== $comparable || ($rating['rating_status'] ?? null) !== ($comparable ? 'provisional' : 'disconnected')
                || !is_numeric($rating['strength'] ?? null) || !is_finite((float) $rating['strength'])
                || !array_key_exists('score', $rating) || ($comparable && $rating['score'] === null) || (!$comparable && $rating['score'] !== null)
                || ($rating['score'] !== null && (!is_numeric($rating['score']) || !is_finite((float) $rating['score']) || $rating['score'] < 0 || $rating['score'] > 100))) { throw new \UnexpectedValueException('Invalid all-age estimate.'); }
            $age = $rating['connection_age_days'] ?? null;
            $historical = $comparable && !isset($backboneMembers[$id]);
            if (($rating['historical_connection'] ?? null) !== $historical
                || ($rating['connection_status'] ?? null) !== (!$comparable ? 'disconnected' : ($historical ? 'historical' : 'recent'))
                || ($comparable && (!is_int($age) || ($historical ? $age <= AllAgeRatingModel::RECENT_WINDOW_DAYS || $age > AllAgeRatingModel::WINDOW_DAYS : $age !== 0)))
                || (!$comparable && ($age !== null || ($rating['connection_reference_date'] ?? null) !== null))
                || ($comparable && ($rating['connection_reference_date'] ?? null) !== $policyDate->subDays($age)->toDateString())
                || ($historical && (!is_int($rating['confidence_index'] ?? null) || $rating['confidence_index'] < 0 || $rating['confidence_index'] > AllAgeRatingModel::HISTORICAL_CONFIDENCE_CAP
                    || ($rating['confidence_label'] ?? null) !== 'Low' || ($rating['confidence_band'] ?? null) !== 'Low'))) { throw new \UnexpectedValueException('Invalid historical connection confidence or provenance.'); }
        }
    }

    public function replace(array $payload, CarbonImmutable $asOf, string $fingerprint, array $manifest): void
    {
        if ($payload['preview_only'] ?? false) { throw new \LogicException('Hypothetical review snapshots cannot replace saved ratings.'); }
        foreach ($payload['cohorts'] as $cohort) {
            foreach ($cohort['components'] as $component) {
                if (!$component['converged']) { throw new \RuntimeException('Incomplete model cannot replace the saved snapshot.'); }
            }
        }
        if (array_key_exists('all_age', $payload)) { $this->assertAllAgeGraph($payload['all_age'], $asOf->toDateString()); }
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
