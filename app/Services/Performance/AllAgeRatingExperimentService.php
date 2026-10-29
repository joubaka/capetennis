<?php

namespace App\Services\Performance;

use App\Models\Event;
use Carbon\CarbonImmutable;

/** Read-only chronological experiments. Never writes estimates or changes a production policy. */
class AllAgeRatingExperimentService
{
    public const PREVIOUS_REGULARIZATION = 0.25;
    public const MAX_EVENTS = 20;
    public const MAX_MATCHES = 50000;

    /** Call inside PlayerAbilityConsistentRead. Extract canonical source rows once, without PII. */
    public function sources(CarbonImmutable $asOf, ?callable $progress = null): array
    {
        $events = Event::query()->with('eventTypeModel')->where(fn ($q) => $q->where('published', true)->orWhere('results_published', true))
            ->whereNotNull('start_date')->whereNotNull('end_date')->whereColumn('end_date', '>=', 'start_date')
            ->whereDate('end_date', '<=', $asOf->toDateString())->orderBy('start_date')->orderBy('id')->limit(1001)->get();
        if ($events->count() > 1000) { throw new \OverflowException('Event extraction limit exceeded.'); }
        $history = app(PlayerPerformanceHistoryService::class);
        $formats = new AllAgeMatchFormatResolver;
        $policy = new AllAgeRatingModel;
        $metadata = []; $matches = []; $seen = []; $scanned = 0;
        foreach ($events as $event) {
            $id = (int) $event->id;
            $metadata[$id] = ['id' => $id, 'name' => $event->name, 'start' => $event->getRawOriginal('start_date'),
                'end' => $event->getRawOriginal('end_date'), 'eligible_singles' => 0, 'excluded' => []];
            $end = CarbonImmutable::parse($metadata[$id]['end'])->startOfDay();
            $rows = (function () use ($history, $event, $end) {
                yield from $history->individualMatches($event, null, app(PlayerPerformancePilotService::class), $end, true);
                yield from $history->allAgeTeamMatches($event, $end);
            })();
            foreach ($rows as $row) {
                if (++$scanned > self::MAX_MATCHES) { throw new \OverflowException('Canonical match extraction limit exceeded.'); }
                $source = ($row['source'] === 'Team match' ? 'team-match:' : 'match:').$row['fixture_id'];
                if (isset($seen[$source])) { continue; }
                $seen[$source] = true;
                $resolved = $formats->resolve($id, $row);
                $format = $policy->format($row['category'] ?? '', $resolved['ball_type'] ?? null);
                $reason = $row['reason'] ?? (($row['discipline'] ?? '') !== 'singles' ? 'Non-singles discipline' : null)
                    ?? $resolved['format_reason'] ?? $format['reason'];
                $winnerSide = $row['winner_side'] ?? null;
                $winner = (int) ($winnerSide === 1 ? ($row['player1_id'] ?? 0) : ($row['player2_id'] ?? 0));
                $loser = (int) ($winnerSide === 1 ? ($row['player2_id'] ?? 0) : ($row['player1_id'] ?? 0));
                if (!in_array($winnerSide, [1, 2], true) || $winner < 1 || $loser < 1 || $winner === $loser) { $reason ??= 'Invalid winner or identity'; }
                $date = $row['confidence_date'] ?? null;
                if (!$date || $date > $metadata[$id]['end'] || $date < $metadata[$id]['start']) { $reason ??= 'Match date outside original event'; }
                if ($reason !== null) {
                    $metadata[$id]['excluded'][$reason] = ($metadata[$id]['excluded'][$reason] ?? 0) + 1;
                    continue;
                }
                $cohort = $row['all_age_cohort'] ?? $row['shared_cohort'] ?? $row['cohort'];
                $matches[] = ['source_id' => $source, 'event_id' => $id, 'event_name' => $event->name,
                    'source' => $row['source'], 'winner' => $winner, 'loser' => $loser,
                    'category' => $row['category'], 'cohort' => $cohort, 'age_groups' => $row['all_age_contexts'] ?? [$cohort],
                    'confidence_date' => $date, 'confidence_date_basis' => $row['confidence_date_basis'],
                    'outcome_target' => $row['outcome_target'] ?? 1.0] + $resolved;
                $metadata[$id]['eligible_singles']++;
            }
            if ($progress) { $progress('extracted', $id, $metadata[$id]['eligible_singles']); }
        }
        return ['events' => $metadata, 'matches' => $matches, 'scanned_records' => $scanned];
    }

    /** Evenly spaced chronological eligible events, independent of model performance. */
    public function selectEvents(array $events, int $limit = self::MAX_EVENTS): array
    {
        if ($limit < 1 || $limit > self::MAX_EVENTS) { throw new \InvalidArgumentException('Choose one to twenty events.'); }
        $events = array_values(array_filter($events, fn ($e) => ($e['eligible_singles'] ?? 0) > 0));
        usort($events, fn ($a, $b) => [$a['start'], $a['id']] <=> [$b['start'], $b['id']]);
        if (count($events) <= $limit) { return array_column($events, 'id'); }
        if ($limit === 1) { return [$events[count($events) - 1]['id']]; }
        $selected = [];
        for ($i = 0; $i < $limit; $i++) { $selected[] = $events[(int) round($i * (count($events) - 1) / ($limit - 1))]['id']; }
        return $selected;
    }

    public function coverageSummary(array $matches, CarbonImmutable $asOf): array
    {
        $summary = [];
        foreach ([365, 730] as $window) {
            $graph = (new AllAgeRatingModel)->fit($matches, $asOf, $window);
            $this->converged($graph);
            $counts = ['all' => ['players' => 0, 'recent_evidence_players' => 0, 'shared_scored_players' => 0, 'within_component_players' => 0]];
            $componentCounts = [];
            foreach ($graph['ratings'] as $id => $rating) {
                $labels = implode(' ', $graph['players'][$id]['age_groups']);
                $girls = (bool) preg_match('/\bgirls\b/iu', $labels); $boys = (bool) preg_match('/\bboys\b/iu', $labels);
                $gender = $girls ? ($boys ? 'mixed' : 'girls') : ($boys ? 'boys' : 'unclassified');
                $recent = CarbonImmutable::parse($rating['last_played'])->diffInDays($asOf->startOfDay(), false) <= 365;
                foreach (['all', $gender] as $key) {
                    $counts[$key] ??= ['players' => 0, 'recent_evidence_players' => 0, 'shared_scored_players' => 0, 'within_component_players' => 0];
                    $counts[$key]['players']++;
                    $counts[$key]['recent_evidence_players'] += (int) $recent;
                    $counts[$key]['shared_scored_players'] += (int) is_numeric($rating['score'] ?? null);
                    $counts[$key]['within_component_players'] += (int) is_numeric($rating['local_score'] ?? null);
                }
                $key = $rating['component'];
                $componentCounts[$key] ??= ['players' => 0, 'recent_evidence_players' => 0, 'reference' => $key === $graph['reference_component'], 'category_gender_counts' => []];
                $componentCounts[$key]['players']++;
                $componentCounts[$key]['recent_evidence_players'] += (int) $recent;
                $componentCounts[$key]['category_gender_counts'][$gender] = ($componentCounts[$key]['category_gender_counts'][$gender] ?? 0) + 1;
            }
            uasort($componentCounts, fn ($a, $b) => $b['players'] <=> $a['players']);
            $summary[$window] = ['counts_by_recorded_category' => $counts, 'component_count' => count($componentCounts),
                'components' => array_values($componentCounts), 'excluded' => $graph['excluded'], 'policy' => $graph['policy']];
        }
        return ['interpretation' => 'Gender groups come from recorded match categories, not verified player demographics. Within-component counts are locally estimable players, not globally comparable scores.', 'windows' => $summary];
    }

    /** Pure evaluator over already validated canonical singles. No player-level output. */
    public function evaluate(array $events, array $matches, array $ids, ?callable $progress = null): array
    {
        if (!$ids || count($ids) > self::MAX_EVENTS || count(array_unique($ids)) !== count($ids) || count($matches) > self::MAX_MATCHES) {
            throw new \InvalidArgumentException('Invalid bounded event or match list.');
        }
        $events = array_column($events, null, 'id');
        $deduplicated = [];
        foreach ($matches as $match) { $deduplicated[$match['source_id']] ??= $match; }
        $matches = array_values($deduplicated);
        $reports = [];
        foreach ($ids as $id) {
            $event = $events[$id] ?? throw new \InvalidArgumentException('Unknown holdout event.');
            if ($event['end'] < $event['start']) { throw new \InvalidArgumentException('Invalid event dates.'); }
            $asOf = CarbonImmutable::parse($event['start'])->startOfDay()->subDay();
            $training = array_values(array_filter($matches, fn ($m) => $m['event_id'] !== $id
                && isset($events[$m['event_id']]) && $events[$m['event_id']]['end'] < $event['start']
                && $events[$m['event_id']]['end'] >= $events[$m['event_id']]['start']
                && $m['confidence_date'] <= $asOf->toDateString()));
            $held = array_values(array_filter($matches, fn ($m) => $m['event_id'] === $id));
            $current = (new AllAgeRatingModel)->fit($training, $asOf);
            $recentRows = array_values(array_filter($training, fn ($m) => CarbonImmutable::parse($m['confidence_date'])->diffInDays($asOf, false) <= 365));
            $recent = (new AllAgeRatingModel)->fit($recentRows, $asOf, 365);
            $this->converged($current); $this->converged($recent);
            $variants = ['current' => $current, 'previous_shrinkage' => $this->variant($current, 1.0, regularization: self::PREVIOUS_REGULARIZATION), 'half_evidence' => $this->variant($current, 0.5),
                'double_evidence' => $this->variant($current, 2.0), 'outcome_only' => $this->variant($current, 1.0, 1.0)];
            $report = ['event_id' => $id, 'event_name' => $event['name'] ?? 'Event '.$id, 'start' => $event['start'], 'end' => $event['end'],
                'training_completed_before' => $event['start'], 'training_events' => count(array_unique(array_column($training, 'event_id'))),
                'training_eligible_singles' => count($training), 'current_policy' => $current['policy'],
                'coverage' => ['shared' => $this->score($current, $held, true), 'within_component' => $this->score($current, $held, false)],
                'recent_365_coverage' => ['shared' => $this->score($recent, $held, true), 'within_component' => $this->score($recent, $held, false)],
                'experiments' => []];
            foreach (['shared' => true, 'within_component' => false] as $scope => $sharedOnly) {
                $sameRows = array_values(array_filter($held, fn ($m) => $this->pair($current, $m, $sharedOnly) && $this->pair($recent, $m, $sharedOnly)));
                $report['window_same_pairs'][$scope] = ['recent_365' => $this->score($recent, $sameRows, $sharedOnly), 'historical_730' => $this->score($current, $sameRows, $sharedOnly)];
                foreach ($variants as $name => $graph) {
                    if ($graph === null) {
                        $report['experiments'][$scope][$name] = ['status' => 'withheld', 'reason' => 'candidate_fit_did_not_converge'];
                        continue;
                    }
                    // Positive weight changes retain topology; explicitly reject any pair-set change.
                    foreach ($held as $match) {
                        if ($this->pair($current, $match, $sharedOnly) !== $this->pair($graph, $match, $sharedOnly)) { throw new \RuntimeException('Experimental pair coverage changed.'); }
                    }
                    $report['experiments'][$scope][$name] = $this->score($graph, $held, $sharedOnly);
                }
            }
            $reports[] = $report;
            if ($progress) { $progress('evaluated', $id, $report['coverage']['within_component']['evaluated_matches']); }
        }
        return ['experiment_policies' => [
                'current' => ['regularization' => AllAgeRatingModel::REGULARIZATION],
                'previous_shrinkage' => ['regularization' => self::PREVIOUS_REGULARIZATION],
                'half_evidence' => ['effective_regularization' => AllAgeRatingModel::REGULARIZATION / 0.5],
                'double_evidence' => ['effective_regularization' => AllAgeRatingModel::REGULARIZATION / 2.0],
                'outcome_only' => ['regularization' => AllAgeRatingModel::REGULARIZATION, 'outcome_target' => 1.0]],
            'selection' => 'Chronologically evenly spaced events with canonical eligible singles, or an explicit operator list; never selected by predictive performance.',
            'interpretation' => 'Shared coverage requires the recent reference group. Within-component predictions use only pairs in the same fitted component, including separate girls groups; they do not establish cross-group equivalence. Events are reported separately. All alternatives are exploratory and no saved rating is changed.',
            'caveats' => ['Current publication, identity and corrected score records are used; historical data availability is not reconstructed.',
                'Match observations share players and events. Losses and confidence labels are uncalibrated; small or selected samples do not establish predictive validity.',
                'Keep untouched future events for confirmation before promoting an experimental policy.'], 'reports' => $reports];
    }

    private function variant(array $base, float $scale, ?float $target = null, float $regularization = AllAgeRatingModel::REGULARIZATION): ?array
    {
        $edges = array_map(function ($edge) use ($scale, $target) { $edge['weight'] *= $scale; if ($target !== null) { $edge['outcome_target'] = $target; } return $edge; }, $base['history_evidence']['matches']);
        $fit = (new SharedAbilityModel)->fit($edges, AllAgeRatingModel::COHORT, regularization: $regularization);
        foreach ($fit['components'] as $component) { if (!$component['converged']) { return null; } }
        foreach ($fit['ratings'] as $id => $rating) {
            if ($rating['component'] !== $base['ratings'][$id]['component']) { throw new \RuntimeException('Experimental topology changed.'); }
            $fit['ratings'][$id] = array_replace($base['ratings'][$id], $rating, ['local_score' => $rating['score'],
                'score' => $base['ratings'][$id]['comparable'] ? $rating['score'] : null]);
        }
        return array_replace($base, ['ratings' => $fit['ratings'], 'components' => $fit['components']]);
    }

    private function converged(array $graph): void
    {
        foreach ($graph['components'] ?? [] as $component) { if (!$component['converged']) { throw new \RuntimeException('Rating fit did not converge.'); } }
    }

    private function pair(array $graph, array $match, bool $sharedOnly): bool
    {
        $a = $graph['ratings'][$match['winner']] ?? null; $b = $graph['ratings'][$match['loser']] ?? null;
        if (!$a || !$b || $a['component'] !== $b['component'] || !is_finite((float) ($a['strength'] ?? NAN)) || !is_finite((float) ($b['strength'] ?? NAN))) { return false; }
        $key = $sharedOnly ? 'score' : 'local_score';
        if (!is_numeric($a[$key] ?? null) || !is_numeric($b[$key] ?? null)) { return false; }
        return !$sharedOnly || (!empty($a['comparable']) && !empty($b['comparable']) && $a['component'] === ($graph['reference_component'] ?? null));
    }

    public function score(array $graph, array $matches, bool $sharedOnly): array
    {
        $probabilities = []; $groups = []; $excluded = [];
        foreach ($matches as $match) {
            $cohort = $match['cohort'];
            $gender = preg_match('/\bmixed\b/iu', $cohort) ? 'mixed' : (preg_match('/\bgirls\b/iu', $cohort) ? (preg_match('/\bboys\b/iu', $cohort) ? 'mixed' : 'girls') : (preg_match('/\bboys\b/iu', $cohort) ? 'boys' : 'unclassified'));
            $keys = ['gender:'.$gender, 'category:'.$cohort, 'source:'.($match['source'] ?? 'Unspecified')];
            foreach ($keys as $key) { $groups[$key] ??= ['eligible' => 0, 'p' => []]; $groups[$key]['eligible']++; }
            if (!$this->pair($graph, $match, $sharedOnly)) { $excluded['no_compatible_pre_event_reference'] = ($excluded['no_compatible_pre_event_reference'] ?? 0) + 1; continue; }
            $a = $graph['ratings'][$match['winner']]; $b = $graph['ratings'][$match['loser']];
            $p = 1 / (1 + exp(-max(-40, min(40, $a['strength'] - $b['strength']))));
            $probabilities[] = $p;
            foreach ($keys as $key) { $groups[$key]['p'][] = $p; }
            $confidence = in_array('Low', [$a['confidence_label'] ?? 'Low', $b['confidence_label'] ?? 'Low'], true) ? 'Low'
                : (in_array('Medium', [$a['confidence_label'], $b['confidence_label']], true) ? 'Medium' : 'High');
            $key = 'confidence:'.$confidence; $groups[$key] ??= ['eligible' => 0, 'p' => []]; $groups[$key]['eligible']++; $groups[$key]['p'][] = $p;
        }
        $subgroups = [];
        foreach ($groups as $key => $group) { $subgroups[$key] = ['eligible_matches' => $group['eligible'], 'coverage' => count($group['p']) / $group['eligible']] + $this->metrics($group['p']); }
        ksort($subgroups);
        return ['eligible_matches' => count($matches), 'coverage' => $matches ? count($probabilities) / count($matches) : null]
            + $this->metrics($probabilities) + ['chance_brier' => $probabilities ? 0.25 : null, 'chance_log_loss' => $probabilities ? log(2) : null,
                'subgroups' => $subgroups, 'excluded' => $excluded];
    }

    private function metrics(array $p): array
    {
        $n = count($p); $wins = 0; $ties = 0; $brier = 0; $loss = 0;
        foreach ($p as $value) { if (abs($value - 0.5) < 1e-12) { $ties++; } elseif ($value > 0.5) { $wins++; } $brier += (1 - $value) ** 2; $loss -= log(max(1e-15, min(1 - 1e-15, $value))); }
        return ['evaluated_matches' => $n, 'correct_favourites' => $wins, 'equal_rating_pairs' => $ties, 'decisive_pairs' => $n - $ties,
            'higher_rated_win_rate' => $n > $ties ? $wins / ($n - $ties) : null, 'brier' => $n ? $brier / $n : null, 'log_loss' => $n ? $loss / $n : null,
            'sample_status' => $n < 30 || $n - $ties < 20 ? 'small_sample' : 'descriptive_only'];
    }
}
