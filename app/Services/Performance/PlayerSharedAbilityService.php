<?php

namespace App\Services\Performance;

use App\Models\{Event, Player};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache, DB, Schema};

/** Private v6 shared-opponent estimates. No saved financial/player/ranking state. */
class PlayerSharedAbilityService
{
    public const VERSION = 6;
    private const EVENT_CAP = 1000;
    private const PLAYER_CAP = 5000;
    private const EDGE_CAP = 50000;
    private const EVENT_FIELD_CAP = 256;

    /** Compact data for name badges; caller enforces the private audience. */
    public function badgeSnapshot(): array
    {
        if (!auth()->user()?->hasRole('super-user')) { return []; }
        $snapshot = app(PlayerAbilitySnapshotStore::class)->current();
        if ($snapshot['reason']) { return []; }
        $players = $snapshot['badge_players'] ?? [];
        foreach ($players as $id => &$ratings) {
            foreach ($ratings as &$rating) {
                $data = $snapshot['cohorts'][$rating['cohort']] ?? [];
                $component = $data['components'][$rating['component']] ?? [];
                $own = $data['players'][$id] ?? [];
                if ($component) {
                    $rating['component_inferred'] = $component['inferred'];
                    $rating['bridge_count'] = $component['bridge_count'];
                    $rating['played'] = $own['played'] ?? 0;
                    $linked = !empty($component['trial_anchored']);
                    $direct = $linked && isset($data['baseline']['anchors'][$id]);
                    $rating['baseline_status'] = $direct ? 'Direct main-trial baseline' : ($linked ? 'Linked to main-trial baseline' : 'No connected main-trial baseline');
                    $rating['baseline_source'] = $linked ? ($data['baseline']['metadata'] ?? null) : null;
                }
                $rating = AbilityConfidenceDisplay::normalize($rating);
            }
            unset($rating);
        }
        unset($ratings);
        if ($snapshot['snapshot_stale'] ?? false) {
            foreach ($players as &$ratings) { foreach ($ratings as &$rating) { $rating['snapshot_stale'] = true; } unset($rating); } unset($ratings);
        }
        return $players;
    }

    public function forPlayer(Player $player, ?CarbonImmutable $asOf = null): array
    {
        if ($asOf === null) {
            $snapshot = app(PlayerAbilitySnapshotStore::class)->current();
            $asOf = CarbonImmutable::parse($snapshot['snapshot_as_of'] ?? CarbonImmutable::today('Africa/Johannesburg')->toDateString(), 'Africa/Johannesburg');
        } else {
            $fingerprint = $this->fingerprint();
            $key = 'player-shared-ability:v'.self::VERSION.':confidence'.SharedAbilityConfidencePolicy::VERSION.':'.$asOf->toDateString().':'.$fingerprint;
            $snapshot = Cache::store(app()->environment('testing') ? 'array' : 'file')->remember($key, 300, fn () => $this->build($asOf));
            if ($fingerprint !== $this->fingerprint()) {
                return ['headline' => null, 'cohorts' => collect(), 'reason' => 'Published source data changed during preview calculation.', 'built_at' => $snapshot['built_at'], 'snapshot_as_of' => $snapshot['snapshot_as_of'] ?? $asOf->toDateString(), 'snapshot_stale' => $snapshot['snapshot_stale'] ?? false];
            }
        }
        $cohorts = collect();
        foreach ($snapshot['cohorts'] as $cohort => $data) {
            $rating = $data['ratings'][$player->id] ?? null;
            if (!$rating) { continue; }
            $component = $data['components'][$rating['component']];
            $own = $data['players'][$player->id];
            $comparators = collect($component['members'])->reject(fn ($id) => $id === $player->id)
                ->sort(fn ($a, $b) => abs($data['ratings'][$a]['strength'] - $rating['strength']) <=> abs($data['ratings'][$b]['strength'] - $rating['strength']) ?: $a <=> $b)
                ->take(5)->map(fn ($id) => ['id' => $id, 'name' => $snapshot['names'][$id] ?? 'Player #'.$id, 'score' => $data['ratings'][$id]['score']])->values();
            $anchors = collect($component['members'])->filter(fn ($id) => count($data['players'][$id]['events']) > 1)
                ->sort(fn ($a, $b) => count($data['players'][$b]['events']) <=> count($data['players'][$a]['events']) ?: $a <=> $b)->take(5)
                ->map(fn ($id) => ['id' => $id, 'name' => $snapshot['names'][$id] ?? 'Player #'.$id,
                    'events' => array_values($data['players'][$id]['events'])])->values();
            $confidence = $component['played'] === 0 ? 'Limited — finishing-order evidence only'
                : ($own['played'] === 0 ? 'Limited — no direct played-match evidence'
                    : ($component['inferred'] > 0 || $component['bridge_count'] > 0 || $own['played'] < 5 ? 'Limited — includes inferred field links, sparse matches or narrow connections' : 'Developing — connected played-match evidence'));
            $cohorts->push($rating + ['cohort' => $cohort, 'last_played' => $own['last_played'], 'played' => $own['played'],
                'inferred' => $own['inferred'], 'component_players' => count($component['members']), 'component_played' => $component['played'],
                'component_inferred' => $component['inferred'], 'bridge_count' => $component['bridge_count'], 'division_links' => $component['division_links'], 'confidence' => $confidence,
                'comparators' => $comparators, 'anchors' => $anchors,
                'reason' => $rating['score'] === null ? 'The shared model did not converge; no estimate is displayed.' : null]
                + $this->reference($data, $player->id, $rating['component'])
                + app(SharedAbilityConfidencePolicy::class)->evaluate($own, $component, $asOf));
        }
        $cohorts = $cohorts->map(function ($estimate) {
            return AbilityConfidenceDisplay::normalize($estimate);
        });
        $cohorts = $cohorts->sort(fn ($a, $b) => strcmp($b['last_played'], $a['last_played']) ?: strcmp($a['cohort'], $b['cohort']))->values();
        return ['headline' => $cohorts->first(), 'cohorts' => $cohorts, 'reason' => $snapshot['reason'], 'built_at' => $snapshot['built_at'], 'snapshot_as_of' => $snapshot['snapshot_as_of'] ?? $asOf->toDateString(), 'snapshot_stale' => $snapshot['snapshot_stale'] ?? false];
    }

    /** Explicit background/preview calculation; never called by ordinary page reads. */
    public function calculateSnapshot(CarbonImmutable $asOf): array
    {
        $snapshot = $this->build($asOf);
        $snapshot['badge_players'] = $this->formatBadgeSnapshot($snapshot, $asOf);
        return $snapshot;
    }

    /** Retrospective validation only: excludes all held-out/overlapping event evidence and bypasses caches. */
    public function validationSnapshot(CarbonImmutable $eventStart, ?int $excludedEventId = null): array
    {
        return $this->build($eventStart->startOfDay()->subDay(), $eventStart->startOfDay(), $excludedEventId);
    }

    private function formatBadgeSnapshot(array $snapshot, CarbonImmutable $asOf): array
    {
        $players = [];
        foreach ($snapshot['cohorts'] as $cohort => $data) {
            foreach ($data['ratings'] as $id => $rating) {
                if ($rating['score'] === null || !is_finite($rating['score'])) { continue; }
                $players[$id][] = ['score' => $rating['score'], 'cohort' => $cohort,
                    'component' => $rating['component'], 'last_played' => $data['players'][$id]['last_played'], 'snapshot_stale' => $snapshot['snapshot_stale'] ?? false]
                    + $this->reference($data, (int) $id, $rating['component'])
                    + app(SharedAbilityConfidencePolicy::class)->evaluate($data['players'][$id], $data['components'][$rating['component']], $asOf);
            }
        }
        return $players;
    }

    /** Exact result/publication values also detect external edits without timestamps. */
    public function fingerprint(): string
    {
        $hash = hash_init('sha256');
        foreach (['events' => ['id', 'published', 'results_published', 'start_date', 'end_date', 'eventType'],
            'draws' => ['id', 'published', 'event_id', 'category_event_id', 'team_scoring_rules', 'drawName', 'team_draw_selection'],
            'team_ties' => ['id', 'draw_id', 'home_team_id', 'away_team_id', 'published_at', 'status'],
            'teams' => ['id', 'name', 'year', 'region_id', 'category_event_id'],
            'team_players' => ['id', 'team_id', 'player_id'],
            'team_fixture_players' => ['id', 'team_fixture_id', 'team1_id', 'team2_id', 'team1_no_profile_id', 'team2_no_profile_id', 'participant_snapshot'],
            'team_fixtures' => ['id', 'draw_id', 'team_tie_id', 'fixture_type', 'numSets', 'scheduled_at', 'region1', 'region2', 'rubber_code', 'match_status', 'age'],
            'event_regions' => ['id', 'event_id', 'region_id'],
            'team_regions' => ['id', 'region_name'],
            'eventtypes' => ['id', 'name', 'code', 'type'],
            'category_events' => ['id', 'event_id', 'category_id'],
            'categories' => ['id', 'name'],
            'draw_settings' => ['id', 'draw_id', 'workflow', 'num_sets', 'score_format', 'require_full_sets'],
            'fixtures' => ['id', 'draw_id', 'registration1_id', 'registration2_id'],
            'category_event_registrations' => ['id', 'category_event_id', 'registration_id']] as $table => $columns) {
            foreach (DB::table($table)->select($columns)->orderBy('id')->lazyById(500) as $row) {
                hash_update($hash, $table.json_encode($row));
            }
        }
        foreach (DB::table('player_registrations')->select('registration_id', 'player_id')->orderBy('registration_id')->orderBy('player_id')->cursor() as $row) {
            hash_update($hash, 'player_registrations'.json_encode($row));
        }
        foreach (['fixture_results', 'team_fixture_results', 'category_results'] as $table) {
            foreach (DB::table($table)->orderBy('id')->lazyById(500) as $row) {
                hash_update($hash, $table.json_encode($row));
            }
        }
        foreach (['category_results', 'category_event_registrations', 'category_events', 'categories', 'fixtures', 'fixture_results', 'player_registrations', 'players', 'draw_settings', 'order_of_plays', 'team_fixture_results'] as $table) {
            $query = DB::table($table);
            $idColumn = $table === 'player_registrations' ? 'registration_id' : 'id';
            $stats = $query->selectRaw('COUNT(*) as row_count, MAX('.$idColumn.') as max_id')->first();
            $updated = Schema::hasColumn($table, 'updated_at') ? DB::table($table)->max('updated_at') : null;
            hash_update($hash, $table.json_encode($stats).$updated);
        }
        return hash_final($hash);
    }

    public function cohort(string $label): string
    {
        $label = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $label)));
        // Compact legacy age/division tokens have an exact age + A/B + gender grammar.
        $label = preg_replace('/\bu\s*\/?\s*(\d{1,2})\s*[ab](?=\s+(?:boys|girls)\b)/u', 'u$1', $label);
        $label = preg_replace('/\bu\s*\/?\s*(\d{1,2})\b/u', 'u$1', $label);
        $label = preg_replace('/\b(boy|girl)(?= |$)/u', '$1s', $label);
        $label = preg_replace('/^(masters · )?(boys|girls) (u\d{1,2})(?= |$)/u', '$1$3 $2', $label);
        return trim($label, " -–");
    }

    private function build(CarbonImmutable $asOf, ?CarbonImmutable $completedBefore = null, ?int $excludedEventId = null): array
    {
        $builtAt = CarbonImmutable::now('Africa/Johannesburg')->format('Y-m-d H:i:s').' SAST';
        $graphs = []; $ordinalFields = []; $trialBaselines = []; $names = []; $eventsSeen = 0; $edgeCount = 0; $scannedMatches = 0; $seenSources = [];
        $pilot = app(PlayerPerformancePilotService::class);
        $history = app(PlayerPerformanceHistoryService::class);
        $events = Event::query()->with('eventTypeModel')->whereDate('start_date', '<=', $asOf->toDateString())
            ->where(fn ($query) => $query->where('published', true)->orWhere('results_published', true));
        if ($completedBefore) { $events->whereNotNull('end_date')->whereColumn('end_date', '>=', 'start_date')->whereDate('end_date', '<', $completedBefore->toDateString()); }
        if ($excludedEventId !== null) { $events->where('id', '!=', $excludedEventId); }
        foreach ($events->lazyById(25) as $event) {
            if (++$eventsSeen > self::EVENT_CAP) { return $this->withheld($builtAt); }
            if (\App\Models\CategoryEvent::query()->where('event_id', $event->id)->count() > self::EVENT_FIELD_CAP) { return $this->withheld($builtAt); }
            if (CarbonImmutable::parse($event->end_date)->greaterThan($asOf)) { $event->end_date = $asOf->toDateString(); }
            $weight = max(PHP_FLOAT_MIN, pow(0.5, max(0, CarbonImmutable::parse($event->end_date)->diffInDays($asOf, false)) / 180));
            $eventPlayed = []; $eventOrdinal = []; $eventEdges = [];
            $definitionCounts = [];
            foreach (\App\Models\CategoryEvent::query()->with('category')->where('event_id', $event->id)->get() as $definition) {
                $division = $pilot->division($definition->category->name);
                $label = $division['cohort'];
                if ($event->frontend_type_view === 'masters') { $label = 'masters · '.$label; }
                $key = $this->cohort($label);
                $definitionCounts[$key] = ($definitionCounts[$key] ?? 0) + 1;
            }
            $fields = [];
            $preview = $pilot->preview([], 'singles', $asOf, 12, null, $event->id, true);
            foreach ($preview['evidence']->whereNull('reason')->whereNotNull('position') as $row) {
                if (count($row['players']) !== 1) { continue; }
                $cohort = $this->cohort($row['cohort']);
                $fieldKey = $row['event_id'].':'.$row['category_id'];
                $fields[$cohort][$fieldKey]['tier'] = $row['tier'];
                $fields[$cohort][$fieldKey]['rows'][] = $row;
            }
            foreach ($fields as $cohort => $cohortFields) {
                $orderedFields = [];
                foreach ($cohortFields as $field) {
                    $orderedFields[] = collect($field['rows'])->sortBy('position')->map(fn ($row) => $row['players'][0]['id'])->values()->all();
                }
                $a = collect($cohortFields)->where('tier', 'A'); $b = collect($cohortFields)->where('tier', 'B');
                $divisionBoundary = null;
                if ($a->count() === 1 && $b->count() === 1 && count($cohortFields) === 2 && ($definitionCounts[$cohort] ?? 0) === 2) {
                    $aIds = collect($a->first()['rows'])->sortBy('position')->map(fn ($row) => $row['players'][0]['id'])->values();
                    $bIds = collect($b->first()['rows'])->sortBy('position')->map(fn ($row) => $row['players'][0]['id'])->values();
                    if ($aIds->intersect($bIds)->isEmpty() && $aIds->count() + $bIds->count() > self::EVENT_FIELD_CAP) { return $this->withheld($builtAt); }
                    if ($aIds->intersect($bIds)->isEmpty()) {
                        $divisionBoundary = $aIds->count() - 1;
                        $orderedFields = [$aIds->merge($bIds)->all()];
                    }
                }
                foreach ($orderedFields as $fieldIndex => $ids) {
                    $source = 'ordinal:'.$event->id.':'.$cohort.':'.$fieldIndex;
                    $eventOrdinal[$cohort][] = ['players' => $ids, 'weight' => SharedAbilityModel::ORDINAL_WEIGHT * $this->decay(CarbonImmutable::parse($event->start_date)->toDateString(), $asOf), 'source_id' => $source];
                    for ($rank = 0; $rank < count($ids) - 1; $rank++) {
                        $edge = ['winner' => $ids[$rank], 'loser' => $ids[$rank + 1], 'kind' => $divisionBoundary === $rank ? 'division_order' : 'finish_order',
                            'weight' => $eventOrdinal[$cohort][array_key_last($eventOrdinal[$cohort])]['weight'], 'source_id' => $source.':'.$rank, 'topology_only' => true];
                        $this->addEdge($graphs, $cohort, $edge, $event);
                        if (++$edgeCount > self::EDGE_CAP) { return $this->withheld($builtAt); }
                    }
                }
            }
            try {
                foreach ($this->playedSources($history, $event, $pilot, $asOf) as $match) {
                    if (++$scannedMatches > self::EDGE_CAP) { return $this->withheld($builtAt); }
                    if ($match['reason'] !== null || $match['discipline'] !== 'singles') { continue; }
                    $source = ($match['source'] === 'Team match' ? 'team-match:' : 'match:').$match['fixture_id'];
                    if (isset($seenSources[$source])) { continue; }
                    $seenSources[$source] = true;
                    $cohort = $match['source'] === 'Team match' ? ($match['shared_cohort'] ?? '') : $this->cohort($match['cohort']);
                    if ($cohort === '') { continue; }
                    $winner = $match['winner_side'] === 1 ? $match['player1_id'] : $match['player2_id'];
                    $loser = $match['winner_side'] === 1 ? $match['player2_id'] : $match['player1_id'];
                    $eventEdges[$cohort][] = ['winner' => $winner, 'loser' => $loser, 'weight' => $this->decay($match['confidence_date'], $asOf), 'kind' => 'played', 'outcome_target' => $match['outcome_target'] ?? 1.0, 'source_id' => $source, 'event_id' => $event->id, 'confidence_date' => $match['confidence_date'], 'confidence_date_basis' => $match['confidence_date_basis']];
                    $eventPlayed[$cohort][$winner][$loser] = true; $eventPlayed[$cohort][$loser][$winner] = true;
                    if (++$edgeCount > self::EDGE_CAP) { return $this->withheld($builtAt); }
                }
            } catch (\OverflowException) { return $this->withheld($builtAt); }
            foreach ($eventEdges as $cohort => $edges) {
                $edges = app(TrialBaselinePolicy::class)->budget($edges);
                foreach ($edges as $edge) { $this->addEdge($graphs, $cohort, $edge, $event); }
                $eventStart = CarbonImmutable::parse($event->start_date)->toDateString();
                $originalEnd = $event->getRawOriginal('end_date');
                if (app(TrialBaselinePolicy::class)->eligible($event) && $originalEnd && CarbonImmutable::parse($originalEnd)->lessThanOrEqualTo($asOf)
                    && (!isset($trialBaselines[$cohort]) || [$eventStart, $event->id] > [$trialBaselines[$cohort]['metadata']['date'], $trialBaselines[$cohort]['metadata']['event_id']])) {
                    $baseline = app(TrialBaselinePolicy::class)->baseline($event, $cohort, $edges, $asOf);
                    if ($baseline['anchors']) { $trialBaselines[$cohort] = $baseline; }
                }
            }
            foreach ($eventOrdinal as $cohort => $eventFields) {
                foreach ($eventFields as $field) {
                    if (!$this->coveredByPlayed($field['players'], $eventPlayed[$cohort] ?? [])) { $ordinalFields[$cohort][] = $field; }
                }
            }
            $uniqueIds = [];
            foreach ($graphs as $graph) { foreach (array_keys($graph['players']) as $id) { $uniqueIds[$id] = true; } }
            if (count($uniqueIds) > self::PLAYER_CAP) { return $this->withheld($builtAt); }
        }
        $playerIds = collect($graphs)->flatMap(fn ($graph) => array_keys($graph['players']))->unique()->all();
        foreach (array_chunk($playerIds, 500) as $chunk) {
            foreach (Player::query()->whereIn('id', $chunk)->get(['id', 'name', 'surname']) as $player) { $names[$player->id] = trim($player->name.' '.$player->surname); }
        }
        $cohorts = [];
        foreach ($graphs as $cohort => $graph) {
            $baseline = $trialBaselines[$cohort] ?? ['anchors' => [], 'metadata' => null];
            $edges = array_map(function ($edge) use ($baseline) {
                if ($edge['kind'] === 'played' && ($edge['event_id'] ?? null) === ($baseline['metadata']['event_id'] ?? null)
                    && isset($baseline['anchors'][$edge['winner']], $baseline['anchors'][$edge['loser']])) { $edge['topology_only'] = true; }
                return $edge;
            }, $graph['edges']);
            $cohorts[$cohort] = app(SharedAbilityModel::class)->fit($edges, $cohort, $ordinalFields[$cohort] ?? [], $baseline['anchors'])
                + ['players' => $graph['players'], 'baseline' => $baseline];
        }
        return ['cohorts' => $cohorts, 'names' => $names, 'reason' => null, 'built_at' => $builtAt,
            'source_events' => collect($graphs)->flatMap(fn ($g) => array_column($g['edges'], 'event_id'))->unique()->values()->all(),
            'source_matches' => array_keys($seenSources)];
    }

    private function reference(array $data, int $id, string $component): array
    {
        $linked = $data['components'][$component]['trial_anchored'];
        $direct = isset($data['baseline']['anchors'][$id]);
        return ['baseline_status' => $direct ? 'Direct main-trial baseline' : ($linked ? 'Linked to main-trial baseline' : 'No connected main-trial baseline'),
            'baseline_source' => $linked ? $data['baseline']['metadata'] : null];
    }

    private function decay(string $date, CarbonImmutable $asOf): float
    {
        return max(PHP_FLOAT_MIN, pow(0.5, max(0, CarbonImmutable::parse($date)->diffInDays($asOf, false)) / 180));
    }

    private function playedSources(PlayerPerformanceHistoryService $history, Event $event, PlayerPerformancePilotService $pilot, CarbonImmutable $asOf): \Generator
    {
        yield from $history->individualMatches($event, null, $pilot, $asOf, true);
        yield from $history->teamMatches($event, null, $asOf);
    }

    private function coveredByPlayed(array $ids, array $adjacency): bool
    {
        $seen = []; $queue = [$ids[0]];
        while ($queue) {
            $id = array_pop($queue);
            if (isset($seen[$id])) { continue; }
            $seen[$id] = true;
            foreach (array_keys($adjacency[$id] ?? []) as $other) { if (!isset($seen[$other])) { $queue[] = $other; } }
        }
        return count(array_intersect($ids, array_keys($seen))) === count($ids);
    }

    private function addEdge(array &$graphs, string $cohort, array $edge, Event $event): void
    {
        if (!$edge['winner'] || !$edge['loser'] || $edge['winner'] === $edge['loser'] || $cohort === '' || !is_finite($edge['weight']) || $edge['weight'] <= 0) { return; }
        $edge['event_id'] ??= $event->id;
        $graphs[$cohort]['edges'][] = $edge;
        foreach ([$edge['winner'], $edge['loser']] as $id) {
            $node = &$graphs[$cohort]['players'][$id];
            $date = CarbonImmutable::parse($event->end_date)->toDateString();
            $node ??= ['played' => 0, 'inferred' => 0, 'last_played' => $date, 'events' => []];
            if ($edge['kind'] === 'played') {
                $node['confidence_matches'][] = ['date' => $edge['confidence_date'], 'basis' => $edge['confidence_date_basis'],
                    'event' => $event->id, 'opponent' => $id === $edge['winner'] ? $edge['loser'] : $edge['winner']];
            } else {
                // Finish evidence has no played timestamp; fixed start is deliberately conservative.
                $node['last_finish'] = max($node['last_finish'] ?? '', CarbonImmutable::parse($event->start_date)->toDateString());
            }
            $node[$edge['kind'] === 'played' ? 'played' : 'inferred']++;
            $node['last_played'] = max($node['last_played'], $date);
            $node['events'][$event->id] = ['id' => $event->id, 'name' => $event->name, 'date' => $date];
            // Provenance samples remain bounded; graph evidence still includes every eligible event.
            $node['events'] = collect($node['events'])->sortByDesc('date')->take(5)->all();
            unset($node);
        }
    }

    private function withheld(string $builtAt): array
    {
        return ['cohorts' => [], 'names' => [], 'reason' => 'Shared calibration exceeds its safe local processing limit (1,000 events, 256 fields per event and 256 entries per ordinal field/team roster, 5,000 players, or 50,000 comparisons/scored match records, or 5,000 team source rosters per event). No partial rating is shown.', 'built_at' => $builtAt];
    }
}
