<?php

namespace App\Services\Performance;

use App\Models\{Event, Player};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache, DB, Schema};

/** Private v3 shared-opponent estimates. No saved financial/player/ranking state. */
class PlayerSharedAbilityService
{
    public const VERSION = 3;
    private const EVENT_CAP = 1000;
    private const PLAYER_CAP = 5000;
    private const EDGE_CAP = 50000;
    private const EVENT_FIELD_CAP = 256;

    /** Compact data for name badges; caller enforces the private audience. */
    public function badgeSnapshot(): array
    {
        if (!auth()->user()?->hasRole('super-user')) { return []; }
        $asOf = CarbonImmutable::today();
        $fingerprint = $this->fingerprint();
        $key = 'player-shared-ability:v'.self::VERSION.':confidence'.SharedAbilityConfidencePolicy::VERSION.':'.$asOf->toDateString().':'.$fingerprint;
        $snapshot = Cache::store(app()->environment('testing') ? 'array' : 'file')->remember($key, 300, fn () => $this->build($asOf));
        if ($fingerprint !== $this->fingerprint() || $snapshot['reason']) { return []; }
        $players = [];
        foreach ($snapshot['cohorts'] as $cohort => $data) {
            foreach ($data['ratings'] as $id => $rating) {
                if ($rating['score'] === null || !is_finite($rating['score'])) { continue; }
                $players[$id][] = ['score' => $rating['score'], 'cohort' => $cohort,
                    'component' => $rating['component'], 'last_played' => $data['players'][$id]['last_played']]
                    + app(SharedAbilityConfidencePolicy::class)->evaluate($data['players'][$id], $data['components'][$rating['component']], $asOf);
            }
        }
        return $players;
    }

    public function forPlayer(Player $player, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::today();
        $fingerprint = $this->fingerprint();
        $key = 'player-shared-ability:v'.self::VERSION.':confidence'.SharedAbilityConfidencePolicy::VERSION.':'.$asOf->toDateString().':'.$fingerprint;
        $snapshot = Cache::store(app()->environment('testing') ? 'array' : 'file')->remember($key, 300, fn () => $this->build($asOf));
        if ($fingerprint !== $this->fingerprint()) {
            return ['headline' => null, 'cohorts' => collect(), 'reason' => 'Published source data changed during calculation. Reload to calculate a fresh snapshot.', 'built_at' => $snapshot['built_at']];
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
                + app(SharedAbilityConfidencePolicy::class)->evaluate($own, $component, $asOf));
        }
        $cohorts = $cohorts->sort(fn ($a, $b) => strcmp($b['last_played'], $a['last_played']) ?: strcmp($a['cohort'], $b['cohort']))->values();
        return ['headline' => $cohorts->first(), 'cohorts' => $cohorts, 'reason' => $snapshot['reason'], 'built_at' => $snapshot['built_at']];
    }

    /** Exact publication flags are hashed even if changed without updated_at. Other source changes expire within five minutes. */
    public function fingerprint(): string
    {
        $hash = hash_init('sha256');
        foreach (['events' => ['id', 'published', 'results_published', 'start_date', 'end_date', 'eventType'],
            'draws' => ['id', 'published', 'event_id', 'category_event_id']] as $table => $columns) {
            foreach (DB::table($table)->select($columns)->orderBy('id')->lazyById(500) as $row) {
                hash_update($hash, $table.json_encode($row));
            }
        }
        foreach (['category_results', 'category_event_registrations', 'category_events', 'categories', 'fixtures', 'fixture_results', 'player_registrations', 'players', 'draw_settings', 'order_of_plays'] as $table) {
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
        return trim($label, " -–");
    }

    private function build(CarbonImmutable $asOf): array
    {
        $builtAt = CarbonImmutable::now('Africa/Johannesburg')->format('Y-m-d H:i:s').' SAST';
        $graphs = []; $names = []; $eventsSeen = 0; $edgeCount = 0; $scannedMatches = 0; $seenSources = [];
        $pilot = app(PlayerPerformancePilotService::class);
        $history = app(PlayerPerformanceHistoryService::class);
        $events = Event::query()->with('eventTypeModel')->whereDate('start_date', '<=', $asOf->toDateString())
            ->where(fn ($query) => $query->where('published', true)->orWhere('results_published', true));
        foreach ($events->lazyById(25) as $event) {
            if (++$eventsSeen > self::EVENT_CAP) { return $this->withheld($builtAt); }
            if (\App\Models\CategoryEvent::query()->where('event_id', $event->id)->count() > self::EVENT_FIELD_CAP) { return $this->withheld($builtAt); }
            if (CarbonImmutable::parse($event->end_date)->greaterThan($asOf)) { $event->end_date = $asOf->toDateString(); }
            $weight = max(PHP_FLOAT_MIN, pow(0.5, max(0, CarbonImmutable::parse($event->end_date)->diffInDays($asOf, false)) / 180));
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
                $inferences = [];
                foreach ($cohortFields as $field) {
                    $ordered = collect($field['rows'])->sortBy('position')->values();
                    for ($index = 0; $index < $ordered->count() - 1; $index++) {
                        $inferences[] = ['winner' => $ordered[$index]['players'][0]['id'], 'loser' => $ordered[$index+1]['players'][0]['id'], 'kind' => 'finish_order'];
                    }
                }
                $a = collect($cohortFields)->where('tier', 'A');
                $b = collect($cohortFields)->where('tier', 'B');
                if ($a->count() === 1 && $b->count() === 1 && count($cohortFields) === 2 && ($definitionCounts[$cohort] ?? 0) === 2) {
                    $lastA = collect($a->first()['rows'])->sortBy('position')->last();
                    $firstB = collect($b->first()['rows'])->sortBy('position')->first();
                    $aIds = collect($a->first()['rows'])->map(fn ($row) => $row['players'][0]['id']);
                    $bIds = collect($b->first()['rows'])->map(fn ($row) => $row['players'][0]['id']);
                    if ($aIds->intersect($bIds)->isEmpty()) {
                        $inferences[] = ['winner' => $lastA['players'][0]['id'], 'loser' => $firstB['players'][0]['id'], 'kind' => 'division_order'];
                    }
                }
                foreach ($inferences as $index => $edge) {
                    $edge += ['weight' => 0.2 * $weight / count($inferences), 'source_id' => 'finish:'.$event->id.':'.$cohort.':'.$index];
                    $this->addEdge($graphs, $cohort, $edge, $event);
                    if (++$edgeCount > self::EDGE_CAP) { return $this->withheld($builtAt); }
                }
            }
            foreach ($history->individualMatches($event, null, $pilot, $asOf) as $match) {
                if (++$scannedMatches > self::EDGE_CAP) { return $this->withheld($builtAt); }
                if ($match['reason'] !== null || $match['discipline'] !== 'singles') { continue; }
                $source = 'match:'.$match['fixture_id'];
                if (isset($seenSources[$source])) { continue; }
                $seenSources[$source] = true;
                $cohort = $this->cohort($match['cohort']);
                $winner = $match['winner_side'] === 1 ? $match['player1_id'] : $match['player2_id'];
                $loser = $match['winner_side'] === 1 ? $match['player2_id'] : $match['player1_id'];
                $this->addEdge($graphs, $cohort, ['winner' => $winner, 'loser' => $loser, 'weight' => $weight, 'kind' => 'played', 'source_id' => $source, 'confidence_date' => $match['confidence_date'] <= $asOf->toDateString() ? $match['confidence_date'] : CarbonImmutable::parse($event->start_date)->toDateString(), 'confidence_date_basis' => $match['confidence_date_basis']], $event);
                if (++$edgeCount > self::EDGE_CAP) { return $this->withheld($builtAt); }
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
            $cohorts[$cohort] = app(SharedAbilityModel::class)->fit($graph['edges'], $cohort) + ['players' => $graph['players']];
        }
        return ['cohorts' => $cohorts, 'names' => $names, 'reason' => null, 'built_at' => $builtAt];
    }

    private function addEdge(array &$graphs, string $cohort, array $edge, Event $event): void
    {
        if (!$edge['winner'] || !$edge['loser'] || $edge['winner'] === $edge['loser'] || $cohort === '' || !is_finite($edge['weight']) || $edge['weight'] <= 0) { return; }
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
        return ['cohorts' => [], 'names' => [], 'reason' => 'Shared calibration exceeds its safe local processing limit (1,000 events, 256 fields per event, 5,000 players, or 50,000 comparisons/scored match records). No partial rating is shown.', 'built_at' => $builtAt];
    }
}
