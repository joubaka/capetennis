<?php

namespace App\Services\Performance;

use App\Models\Event;
use Carbon\CarbonImmutable;

/** Aggregate retrospective holdout evaluation; no snapshot/cache writes or player-level output. */
class PlayerAbilityValidationService
{
    /** Bound operator-selected holdouts; each event keeps its own report. */
    public static function eventIds(array $options): array
    {
        if (isset($options['event']) === isset($options['events'])) { throw new \InvalidArgumentException('Supply either event or events.'); }
        $raw = $options['event'] ?? $options['events'];
        if (!is_string($raw)) { throw new \InvalidArgumentException('Invalid event list.'); }
        $ids = explode(',', $raw);
        if (count($ids) > 10 || (isset($options['event']) && count($ids) !== 1)) { throw new \InvalidArgumentException('Choose at most ten events.'); }
        foreach ($ids as $id) {
            if (!ctype_digit($id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) { throw new \InvalidArgumentException('Invalid event ID.'); }
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function run(Event $event, bool $compare = false): array
    {
        $start = $event->getRawOriginal('start_date');
        $end = $event->getRawOriginal('end_date');
        if (!$start || !$end || !$event->published || CarbonImmutable::parse($end)->lessThan(CarbonImmutable::parse($start)) || CarbonImmutable::parse($end)->greaterThan(CarbonImmutable::today('Africa/Johannesburg'))) {
            throw new \InvalidArgumentException('Choose a completed event with recorded start/end dates.');
        }
        $shared = app(PlayerSharedAbilityService::class);
        $before = $shared->fingerprint();
        $training = $shared->validationSnapshot(CarbonImmutable::parse($start), $event->id);
        $experiments = [];
        if ($compare) {
            foreach (['current_information' => 1.0, 'trial_weight_half' => 0.5, 'trial_weight_double' => 2.0] as $name => $scale) {
                $experiments[$name] = $shared->validationSnapshot(CarbonImmutable::parse($start), $event->id,
                    ['anchor_weight_scale' => $scale, 'diagnostics' => true]);
                if ($experiments[$name]['reason']) { throw new \RuntimeException('Experimental fit withheld.'); }
                foreach ($experiments[$name]['cohorts'] as $data) {
                    foreach ($data['components'] as $component) { if (!$component['converged']) { throw new \RuntimeException('Experimental fit did not converge.'); } }
                }
            }
        }
        if ($training['reason']) { throw new \RuntimeException('Training calculation was withheld.'); }
        foreach ($training['cohorts'] as $data) {
            foreach ($data['components'] as $component) {
                if (!$component['converged']) { throw new \RuntimeException('Training model did not converge.'); }
            }
        }
        $history = app(PlayerPerformanceHistoryService::class);
        $asOf = CarbonImmutable::parse($end);
        $matches = (function () use ($history, $event, $asOf) {
            yield from $history->individualMatches($event, null, app(PlayerPerformancePilotService::class), $asOf, true);
            yield from $history->teamMatches($event, null, $asOf);
        })();
        $report = $this->evaluate($training, $matches, CarbonImmutable::parse($start)->subDay(), $experiments);
        // Raw counts explain an empty stream without weakening canonical eligibility.
        $report['source_candidates'] = $this->sourceCandidates($event);
        $report['source_scope_note'] = 'Candidate counts include all event fixtures, including doubles and unplayed/unpublished fixtures. Source diagnostics cover only records yielded by canonical eligibility readers; prefiltered records have no attributed rejection reason.';
        if ($before !== $shared->fingerprint()) { throw new \RuntimeException('Sources changed during validation; report withheld.'); }
        return ['holdout_event_id' => $event->id, 'training_completed_before' => CarbonImmutable::parse($start)->toDateString(),
            'caveat' => 'Retrospective evaluation uses current publication/identity records and date proxies; historical availability is not reconstructed. Model-derived probabilities are uncalibrated.'] + $report;
    }

    private function sourceCandidates(Event $event): array
    {
        $counts = [];
        foreach (['Individual match' => \App\Models\Fixture::class, 'Team match' => \App\Models\TeamFixture::class] as $source => $model) {
            $query = $model::query()->withoutEagerLoads()->whereHas('draw', fn ($draw) => $draw->where('event_id', $event->id));
            $counts[$source]['all_event_fixtures'] = $query->count();
            $query->whereHas('draw', fn ($draw) => $draw->where('published', true));
            $counts[$source]['published_draw'] = $query->count();
            if ($source === 'Team match') {
                $query->publishedTeamTies();
                $counts[$source]['published_tie_or_legacy_without_tie'] = $query->count();
                $query->whereHas('teamResults');
                $counts[$source]['with_recorded_scores'] = $query->count();
                $query->where(fn ($types) => $types->whereIn('fixture_type', [0, 1, 4])->orWhereNull('fixture_type')->orWhereIn('rubber_code', ['singles', 'reverse_singles']));
            } else {
                $query->whereHas('fixtureResults');
                $counts[$source]['with_recorded_scores'] = $query->count();
                $query->whereIn('match_status', [0, 1, 2, 3]);
            }
            $counts[$source]['canonical_reader_candidates'] = $query->count();
        }
        return $counts;
    }

    public function evaluate(array $training, iterable $matches, CarbonImmutable $asOf, array $experiments = []): array
    {
        $comparisons = []; $alternativeGroups = []; $information = []; $informationGroups = [];
        $eligible = 0; $scanned = 0; $diagnostics = []; $sources = []; $seen = []; $skips = []; $groups = []; $current = []; $reference = []; $paired = [];
        foreach ($matches as $match) {
            if (++$scanned > 50000) { throw new \OverflowException('Validation source limit exceeded.'); }
            $key = $match['source'].':'.$match['fixture_id'];
            $source = &$sources[$match['source']];
            $source ??= ['yielded' => 0, 'duplicates' => 0, 'eligible' => 0, 'evaluated' => 0, 'exclusion_reasons' => []];
            $source['yielded']++;
            if (isset($seen[$key])) { $source['duplicates']++; continue; } $seen[$key] = true;
            if ($match['reason'] !== null || $match['discipline'] !== 'singles') {
                $reason = $match['reason'] ?? 'Non-singles discipline';
                $source['exclusion_reasons'][$reason] = ($source['exclusion_reasons'][$reason] ?? 0) + 1;
                $skips['ineligible_source'] = ($skips['ineligible_source'] ?? 0) + 1; continue;
            }
            $eligible++;
            $cohort = $match['source'] === 'Team match' ? ($match['shared_cohort'] ?? '') : app(PlayerSharedAbilityService::class)->cohort($match['cohort']);
            $data = $training['cohorts'][$cohort] ?? [];
            $winner = $match['winner_side'] === 1 ? $match['player1_id'] : $match['player2_id'];
            $loser = $match['winner_side'] === 1 ? $match['player2_id'] : $match['player1_id'];
            if (!$winner || !$loser || $winner === $loser) { $skips['invalid_identity'] = ($skips['invalid_identity'] ?? 0) + 1; $source['exclusion_reasons']['invalid_identity'] = ($source['exclusion_reasons']['invalid_identity'] ?? 0) + 1; $eligible--; continue; }
            $source['eligible']++;
            $diagnostics[$cohort] ??= ['eligible_matches' => 0, 'training_players' => count($data['ratings'] ?? []), 'missing_both' => 0, 'missing_one' => 0, 'compatible_pairs' => 0];
            $diagnostics[$cohort]['eligible_matches']++;
            $a = $data['ratings'][$winner] ?? null; $b = $data['ratings'][$loser] ?? null;
            if (!$a || !$b || $a['score'] === null || $b['score'] === null || !is_finite($a['strength']) || !is_finite($b['strength'])) {
                if (!$a && !$b) { $diagnostics[$cohort]['missing_both']++; } elseif (!$a || !$b) { $diagnostics[$cohort]['missing_one']++; }
                $skips['missing_pre_event_rating'] = ($skips['missing_pre_event_rating'] ?? 0) + 1;
                $source['exclusion_reasons']['missing_pre_event_rating'] = ($source['exclusion_reasons']['missing_pre_event_rating'] ?? 0) + 1; continue;
            }
            if ($a['component'] !== $b['component']) { $skips['different_comparison_group'] = ($skips['different_comparison_group'] ?? 0) + 1; $source['exclusion_reasons']['different_comparison_group'] = ($source['exclusion_reasons']['different_comparison_group'] ?? 0) + 1; continue; }
            $probability = $this->probability($a['strength'] - $b['strength']);
            $current[] = $probability;
            $source['evaluated']++;
            $diagnostics[$cohort]['compatible_pairs']++;
            $labels = [];
            foreach ([$winner, $loser] as $id) {
                $component = $data['components'][$data['ratings'][$id]['component']];
                $own = $data['players'][$id];
                $context = app(SharedAbilityConfidencePolicy::class)->evaluate($own, $component, $asOf)
                    + ['played' => $own['played'], 'component_inferred' => $component['inferred'], 'bridge_count' => $component['bridge_count'],
                        'baseline_status' => isset($data['baseline']['anchors'][$id]) ? 'Direct main-trial baseline' : 'Indirect or unavailable'];
                $labels[] = AbilityConfidenceDisplay::label($context['confidence_index'], $context);
            }
            $band = in_array('Low', $labels, true) ? 'Low' : (in_array('Medium', $labels, true) ? 'Medium' : 'High');
            $groups[$band][] = $probability;
            foreach ($experiments as $name => $snapshot) {
                $candidate = $snapshot['cohorts'][$cohort] ?? [];
                $ea = $candidate['ratings'][$winner] ?? null; $eb = $candidate['ratings'][$loser] ?? null;
                if (!$ea || !$eb || $ea['score'] === null || $eb['score'] === null || $ea['component'] !== $eb['component']
                    || !is_finite($ea['strength']) || !is_finite($eb['strength'])) { continue; }
                $ep = $this->probability($ea['strength'] - $eb['strength']);
                $comparisons[$name][$cohort]['current'][] = $probability;
                $comparisons[$name][$cohort]['alternative'][] = $ep;
                if ($name === 'current_information') {
                    $experimentalLabels = [];
                    foreach ([$winner, $loser] as $id) {
                        $context = app(ExperimentalConfidencePolicy::class)->evaluate($data['players'][$id], $data['components'][$data['ratings'][$id]['component']], $asOf)
                            + ['played' => $data['players'][$id]['played'], 'component_inferred' => $component['inferred'], 'bridge_count' => $component['bridge_count'],
                                'baseline_status' => isset($data['baseline']['anchors'][$id]) ? 'Direct main-trial baseline' : 'Indirect or unavailable'];
                        $experimentalLabels[] = AbilityConfidenceDisplay::label($context['confidence_index'], $context);
                    }
                    $alternativeBand = in_array('Low', $experimentalLabels, true) ? 'Low' : (in_array('Medium', $experimentalLabels, true) ? 'Medium' : 'High');
                    $alternativeGroups[$alternativeBand][] = $probability;
                    $seA = $ea['conditional_strength_se'] ?? null; $seB = $eb['conditional_strength_se'] ?? null;
                    if ($seA !== null && $seB !== null && is_finite($seA) && is_finite($seB)) {
                        $se = sqrt($seA ** 2 + $seB ** 2);
                        $information[] = $se;
                        $informationGroups[$se <= 1 ? 'conditional_se_at_most_1' : ($se <= 2 ? 'conditional_se_1_to_2' : 'conditional_se_above_2')][] = $probability;
                    }
                }
            }
            $anchors = $data['baseline']['anchors'] ?? [];
            if (isset($anchors[$winner], $anchors[$loser])) {
                $reference[] = $this->probability($anchors[$winner]['target'] - $anchors[$loser]['target']);
                $paired[] = $probability;
            }
        }
        unset($source);
        $comparisonReport = [];
        foreach ($comparisons as $name => $cohorts) {
            foreach ($cohorts as $cohort => $pair) {
                $base = $this->metrics($pair['current']); $alternative = $this->metrics($pair['alternative']);
                $comparisonReport[$name][$cohort] = ['current_same_pairs' => $base, 'alternative_same_pairs' => $alternative,
                    'brier_change' => $alternative['brier'] - $base['brier'], 'log_loss_change' => $alternative['log_loss'] - $base['log_loss']];
            }
        }
        $experimentalReport = $experiments ? ['experiments' => ['paired_by_cohort' => $comparisonReport,
            'confidence_single_decay' => ['current_groups' => array_map($this->metrics(...), $groups), 'alternative_groups' => array_map($this->metrics(...), $alternativeGroups),
                'prediction_policy' => 'Identical current predictions and pairs; confidence changes stratification only.'],
            'conditional_information' => ['evaluated_pairs' => count($information), 'mean_conditional_difference_se' => $information ? array_sum($information) / count($information) : null,
                'sensitivity_groups' => array_map($this->metrics(...), $informationGroups),
                'caveat' => 'Regularized diagonal conditional information, holding opponent strengths fixed. Difference approximation sums conditional variances, omits covariance and source dependence, and is not a calibrated interval or accuracy probability.'],
            'policy' => 'Read-only experiments. No saved ratings or confidence labels changed. Negative loss changes favor the alternative on these same pairs only. No automatic promotion.']]
            : [];
        return $experimentalReport + ['status' => !$eligible ? 'no_evidence' : (!$current ? 'insufficient_coverage' : (count($current) < $eligible ? 'partial_coverage' : 'evaluated')),
            'interpretation' => 'Coverage describes compatible pre-event ratings, not accuracy. Small or selected samples cannot establish predictive validity; probabilities and confidence labels are uncalibrated.',
            'source_diagnostics' => $sources, 'chance_reference' => $this->metrics(array_fill(0, count($current), 0.5)),
            'training_event_count' => count($training['source_events'] ?? []), 'cohort_diagnostics' => $diagnostics, 'eligible_matches' => $eligible, 'coverage' => $eligible ? count($current) / $eligible : null,
            'current' => $this->metrics($current), 'confidence_groups' => array_map($this->metrics(...), $groups), 'excluded' => $skips,
            'direct_trial_reference' => $this->metrics($reference), 'current_on_trial_reference_pairs' => $this->metrics($paired)];
    }

    private function probability(float $difference): float { return 1 / (1 + exp(-max(-50, min(50, $difference)))); }
    private function metrics(array $probabilities): array
    {
        $count = count($probabilities); $wins = 0; $ties = 0; $brier = 0; $loss = 0;
        foreach ($probabilities as $p) {
            if (abs($p - 0.5) < 1e-12) { $ties++; } elseif ($p > 0.5) { $wins++; }
            $brier += (1 - $p) ** 2; $loss -= log(max(1e-15, min(1 - 1e-15, $p)));
        }
        return ['evaluated_matches' => $count, 'equal_rating_pairs' => $ties,
            'decisive_pairs' => $count - $ties, 'sample_status' => $count < 30 || $count - $ties < 20 ? 'small_sample' : 'descriptive_only',
            'higher_rated_win_rate' => $count > $ties ? $wins / ($count - $ties) : null,
            'brier' => $count ? $brier / $count : null, 'log_loss' => $count ? $loss / $count : null];
    }
}
