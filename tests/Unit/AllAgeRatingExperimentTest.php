<?php

namespace Tests\Unit;

use App\Services\Performance\{AllAgeRatingExperimentService, AllAgeRatingModel};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AllAgeRatingExperimentTest extends TestCase
{
    private function event(int $id, string $start, string $end, int $eligible = 1): array
    {
        return ['id' => $id, 'name' => 'Synthetic event '.$id, 'start' => $start, 'end' => $end, 'eligible_singles' => $eligible];
    }

    private function match(int $event, int $winner, int $loser, string $date, string $category = 'u13 boys', string $suffix = ''): array
    {
        return ['source_id' => $event.':'.$winner.':'.$loser.':'.$suffix, 'event_id' => $event, 'event_name' => 'Synthetic event '.$event,
            'source' => 'Individual match', 'winner' => $winner, 'loser' => $loser, 'confidence_date' => $date,
            'confidence_date_basis' => 'scheduled match date', 'category' => $category, 'cohort' => $category,
            'ball_type' => 'yellow', 'outcome_target' => 0.9];
    }

    public function test_selection_is_chronological_evenly_spaced_and_ignores_empty_events(): void
    {
        $events = [$this->event(1, '2024-01-01', '2024-01-01'), $this->event(2, '2024-02-01', '2024-02-01', 0),
            $this->event(3, '2025-01-01', '2025-01-01'), $this->event(4, '2026-01-01', '2026-01-01')];
        $service = new AllAgeRatingExperimentService;
        $this->assertSame([1, 4], $service->selectEvents($events, 2));
        $this->assertSame([1, 3, 4], $service->selectEvents(array_reverse($events), 3));
    }

    public function test_chronological_fit_excludes_holdout_overlapping_and_future_event_results(): void
    {
        $events = [$this->event(1, '2026-01-01', '2026-01-02'), $this->event(2, '2026-02-01', '2026-02-01'),
            $this->event(3, '2026-01-20', '2026-02-02'), $this->event(4, '2026-03-01', '2026-03-01')];
        $train = $this->match(1, 1, 2, '2026-01-01');
        $holdout = $this->match(2, 1, 2, '2026-02-01');
        $service = new AllAgeRatingExperimentService;
        $baseline = $service->evaluate($events, [$train, $holdout], [2]);
        $contaminants = [$this->match(3, 2, 1, '2026-01-21'), $this->match(4, 2, 1, '2026-03-01')];
        $actual = $service->evaluate($events, [$train, $holdout, ...$contaminants], [2]);
        $this->assertSame($baseline, $actual);
        $this->assertSame(1, $actual['reports'][0]['training_events']);
    }

    public function test_disconnected_girls_have_within_component_predictions_but_no_false_shared_coverage(): void
    {
        $events = [$this->event(1, '2026-01-01', '2026-01-01'), $this->event(2, '2026-02-01', '2026-02-01')];
        $matches = [$this->match(1, 1, 2, '2026-01-01'), $this->match(1, 2, 3, '2026-01-01'),
            $this->match(1, 10, 11, '2026-01-01', 'u13 girls'), $this->match(2, 10, 11, '2026-02-01', 'u13 girls'),
            $this->match(2, 1, 10, '2026-02-01', 'mixed singles')];
        $r = (new AllAgeRatingExperimentService)->evaluate($events, $matches, [2])['reports'][0];
        $this->assertSame(0, $r['coverage']['shared']['evaluated_matches']);
        $this->assertSame(1, $r['coverage']['within_component']['evaluated_matches']);
        $this->assertSame(1, $r['coverage']['within_component']['subgroups']['gender:girls']['evaluated_matches']);
        $this->assertSame(0, $r['coverage']['within_component']['subgroups']['gender:mixed']['evaluated_matches']);
    }

    public function test_variants_replay_same_pairs_and_duplicates_do_not_change_metrics(): void
    {
        $events = [$this->event(1, '2026-01-01', '2026-01-01'), $this->event(2, '2026-02-01', '2026-02-01')];
        $matches = [$this->match(1, 1, 2, '2026-01-01'), $this->match(2, 1, 2, '2026-02-01')];
        $service = new AllAgeRatingExperimentService;
        $report = $service->evaluate($events, $matches, [2]);
        $this->assertSame($report, $service->evaluate($events, [...$matches, ...$matches], [2]));
        foreach ($report['reports'][0]['experiments'] as $variants) {
            foreach ($variants as $value) { $this->assertSame(1, $value['evaluated_matches']); }
            $this->assertLessThan($variants['current']['brier'], $variants['double_evidence']['brier']);
            $this->assertLessThan($variants['previous_shrinkage']['brier'], $variants['current']['brier']);
            $this->assertLessThan($variants['previous_shrinkage']['brier'], $variants['half_evidence']['brier']);
        }
        $this->assertSame(0.015625, $report['experiment_policies']['current']['regularization']);
        $this->assertSame(0.25, $report['experiment_policies']['previous_shrinkage']['regularization']);
    }

    public function test_brier_log_loss_ties_and_upset_are_computed_from_unrounded_strengths(): void
    {
        $graph = ['reference_component' => 'a', 'ratings' => [
            1 => ['score' => 70, 'local_score' => 70, 'strength' => log(3), 'component' => 'a', 'comparable' => true],
            2 => ['score' => 50, 'local_score' => 50, 'strength' => 0, 'component' => 'a', 'comparable' => true],
        ]];
        $service = new AllAgeRatingExperimentService;
        $r = $service->score($graph, [$this->match(1, 1, 2, '2026-01-01')], true);
        $this->assertEqualsWithDelta(0.0625, $r['brier'], 1e-12);
        $this->assertEqualsWithDelta(-log(0.75), $r['log_loss'], 1e-12);
        $upset = $service->score($graph, [$this->match(1, 2, 1, '2026-01-01')], true);
        $this->assertEqualsWithDelta(0.5625, $upset['brier'], 1e-12);
        $graph['ratings'][1]['strength'] = 0;
        $tie = $service->score($graph, [$this->match(1, 1, 2, '2026-01-01')], true);
        $this->assertSame(1, $tie['equal_rating_pairs']);
        $this->assertNull($tie['higher_rated_win_rate']);
    }

    public function test_nonconverged_optional_candidate_is_withheld_without_discarding_valid_girls_comparison(): void
    {
        $events = [$this->event(1, '2026-01-01', '2026-01-01'), $this->event(2, '2026-02-01', '2026-02-01')];
        $matches = [];
        for ($id = 1; $id < 10; $id++) {
            $matches[] = array_replace($this->match(1, $id, $id + 1, '2026-01-01', 'u13 girls'), ['outcome_target' => 1.0]);
        }
        $matches[] = $this->match(2, 1, 10, '2026-02-01', 'u13 girls');
        $report = (new AllAgeRatingExperimentService)->evaluate($events, $matches, [2])['reports'][0];
        $variants = $report['experiments']['within_component'];
        $this->assertSame(1, $variants['current']['evaluated_matches']);
        $this->assertSame(1, $variants['previous_shrinkage']['evaluated_matches']);
        $this->assertSame(['status' => 'withheld', 'reason' => 'candidate_fit_did_not_converge'], $variants['double_evidence']);
        $this->assertArrayNotHasKey('brier', $variants['double_evidence']);
    }

    public function test_coverage_summary_preserves_local_girls_counts_without_global_scores(): void
    {
        $matches = [$this->match(1, 1, 2, '2026-01-01'), $this->match(1, 2, 3, '2026-01-01'),
            $this->match(1, 10, 11, '2026-01-01', 'u13 girls')];
        $r = (new AllAgeRatingExperimentService)->coverageSummary($matches, CarbonImmutable::parse('2026-02-01'));
        foreach ($r['windows'] as $window) {
            $girls = $window['counts_by_recorded_category']['girls'];
            $this->assertSame(2, $girls['within_component_players']);
            $this->assertSame(0, $girls['shared_scored_players']);
        }
    }
}
