<?php

namespace App\Services\Performance;

use App\Models\Event;
use Carbon\CarbonImmutable;

/** Aggregate retrospective holdout evaluation; no snapshot/cache writes or player-level output. */
class PlayerAbilityValidationService
{
    public function run(Event $event): array
    {
        $start = $event->getRawOriginal('start_date');
        $end = $event->getRawOriginal('end_date');
        if (!$start || !$end || !$event->published || CarbonImmutable::parse($end)->lessThan(CarbonImmutable::parse($start)) || CarbonImmutable::parse($end)->greaterThan(CarbonImmutable::today('Africa/Johannesburg'))) {
            throw new \InvalidArgumentException('Choose a completed event with recorded start/end dates.');
        }
        $shared = app(PlayerSharedAbilityService::class);
        $before = $shared->fingerprint();
        $training = $shared->validationSnapshot(CarbonImmutable::parse($start), $event->id);
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
        $report = $this->evaluate($training, $matches, CarbonImmutable::parse($start)->subDay());
        if ($before !== $shared->fingerprint()) { throw new \RuntimeException('Sources changed during validation; report withheld.'); }
        return ['holdout_event_id' => $event->id, 'training_completed_before' => CarbonImmutable::parse($start)->toDateString(),
            'caveat' => 'Retrospective evaluation uses current publication/identity records and date proxies; historical availability is not reconstructed. Model-derived probabilities are uncalibrated.'] + $report;
    }

    public function evaluate(array $training, iterable $matches, CarbonImmutable $asOf): array
    {
        $eligible = 0; $scanned = 0; $diagnostics = []; $seen = []; $skips = []; $groups = []; $current = []; $reference = []; $paired = [];
        foreach ($matches as $match) {
            if (++$scanned > 50000) { throw new \OverflowException('Validation source limit exceeded.'); }
            $key = $match['source'].':'.$match['fixture_id'];
            if (isset($seen[$key])) { continue; } $seen[$key] = true;
            if ($match['reason'] !== null || $match['discipline'] !== 'singles') { $skips['ineligible_source'] = ($skips['ineligible_source'] ?? 0) + 1; continue; }
            $eligible++;
            $cohort = $match['source'] === 'Team match' ? ($match['shared_cohort'] ?? '') : app(PlayerSharedAbilityService::class)->cohort($match['cohort']);
            $data = $training['cohorts'][$cohort] ?? [];
            $winner = $match['winner_side'] === 1 ? $match['player1_id'] : $match['player2_id'];
            $loser = $match['winner_side'] === 1 ? $match['player2_id'] : $match['player1_id'];
            if (!$winner || !$loser || $winner === $loser) { $skips['invalid_identity'] = ($skips['invalid_identity'] ?? 0) + 1; $eligible--; continue; }
            $diagnostics[$cohort] ??= ['eligible_matches' => 0, 'training_players' => count($data['ratings'] ?? []), 'missing_both' => 0, 'missing_one' => 0, 'compatible_pairs' => 0];
            $diagnostics[$cohort]['eligible_matches']++;
            $a = $data['ratings'][$winner] ?? null; $b = $data['ratings'][$loser] ?? null;
            if (!$a || !$b || $a['score'] === null || $b['score'] === null || !is_finite($a['strength']) || !is_finite($b['strength'])) {
                if (!$a && !$b) { $diagnostics[$cohort]['missing_both']++; } elseif (!$a || !$b) { $diagnostics[$cohort]['missing_one']++; }
                $skips['missing_pre_event_rating'] = ($skips['missing_pre_event_rating'] ?? 0) + 1; continue;
            }
            if ($a['component'] !== $b['component']) { $skips['different_comparison_group'] = ($skips['different_comparison_group'] ?? 0) + 1; continue; }
            $probability = $this->probability($a['strength'] - $b['strength']);
            $current[] = $probability;
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
            $anchors = $data['baseline']['anchors'] ?? [];
            if (isset($anchors[$winner], $anchors[$loser])) {
                $reference[] = $this->probability($anchors[$winner]['target'] - $anchors[$loser]['target']);
                $paired[] = $probability;
            }
        }
        return ['training_event_count' => count($training['source_events'] ?? []), 'cohort_diagnostics' => $diagnostics, 'eligible_matches' => $eligible, 'coverage' => $eligible ? count($current) / $eligible : null,
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
            'higher_rated_win_rate' => $count > $ties ? $wins / ($count - $ties) : null,
            'brier' => $count ? $brier / $count : null, 'log_loss' => $count ? $loss / $count : null];
    }
}
