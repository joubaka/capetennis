<?php

namespace Tests\Unit;

use App\Services\Performance\{CohortTransitionAbilityModel, CohortTransitionEvidenceService, CohortTransitionValidationService};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CohortTransitionAbilityModelTest extends TestCase
{
    private function rows(): array
    {
        $rows = [];
        foreach ([2, 3, 4] as $id) {
            $rows[] = ['source_id' => 'match:'.$id, 'event_id' => $id, 'cohort' => 'u10 boys', 'winner' => 1,
                'loser' => $id, 'date' => '2026-01-0'.$id, 'date_basis' => 'scheduled match date',
                'outcome_target' => 1.0, 'context_verified' => true];
        }
        return $rows;
    }

    private function native(): array
    {
        return ['ratings' => [2 => ['strength' => -0.4, 'score' => 40.0, 'component' => 'target'],
            3 => ['strength' => 0.2, 'score' => 55.0, 'component' => 'target'],
            4 => ['strength' => 0.6, 'score' => 65.0, 'component' => 'target']],
            'players' => [2 => ['played' => 5], 3 => ['played' => 5], 4 => ['played' => 5]]];
    }

    public function test_raw_outcomes_are_deterministic_deduplicated_and_old_scores_are_not_inputs(): void
    {
        $model = new CohortTransitionAbilityModel;
        $date = CarbonImmutable::parse('2026-04-01');
        $first = $model->estimate(1, 'u11 boys', $this->rows(), $this->native(), $date);
        $rows = array_reverse($this->rows());
        $second = $model->estimate(1, 'u11 boys', [...$rows, $rows[0]], $this->native(), $date);
        $this->assertTrue($first['available']);
        $this->assertSame($first, $second);
        $rows = array_map(fn ($r) => $r + ['old_score' => 999, 'old_strength' => -999], $this->rows());
        $this->assertSame($first, $model->estimate(1, 'u11 boys', $rows, $this->native(), $date));
        $this->assertSame(3, $first['support']['historical_matches']);
        $this->assertSame(0, $first['support']['current_played_added']);
        $this->assertSame(0, $first['sensitivity']['leave_peer_out_abstentions']);
        $this->assertSame(0, $first['sensitivity']['leave_event_out_abstentions']);
    }

    public function test_wrong_identity_context_gender_future_and_old_dates_do_not_create_evidence(): void
    {
        $date = CarbonImmutable::parse('2026-04-01');
        $r = $this->rows()[0];
        foreach ([['cohort' => 'u10 girls'], ['cohort' => 'red ball'], ['cohort' => 'u12 boys'], ['date' => '2026-04-02'],
            ['date' => '2020-01-01'], ['winner' => 0], ['loser' => 1], ['context_verified' => false]] as $change) {
            $this->assertFalse(CohortTransitionEvidenceService::eligible(array_replace($r, $change), 'u11 boys', $date, false));
        }
        $this->assertFalse((new CohortTransitionAbilityModel)->estimate(99, 'u11 boys', $this->rows(), $this->native(), $date)['available']);
        $this->assertTrue(CohortTransitionEvidenceService::eligible(array_replace($r, ['context_verified' => false]), 'u11 boys', $date, true));
    }

    public function test_disconnected_and_multiple_target_components_are_withheld(): void
    {
        $model = new CohortTransitionAbilityModel;
        $date = CarbonImmutable::parse('2026-04-01');
        $this->assertSame('no_target_reference', $model->estimate(1, 'u11 boys', $this->rows(), [], $date)['reason']);
        $native = $this->native(); $native['ratings'][4]['component'] = 'other';
        $this->assertSame('multiple_target_components', $model->estimate(1, 'u11 boys', $this->rows(), $native, $date)['reason']);
    }

    public function test_single_event_or_bridge_is_exploratory_and_leave_out_rebuilds_connectivity(): void
    {
        $rows = array_map(fn ($r) => array_replace($r, ['event_id' => 1]), $this->rows());
        $result = (new CohortTransitionAbilityModel)->estimate(1, 'u11 boys', $rows, $this->native(), CarbonImmutable::parse('2026-04-01'));
        $this->assertTrue($result['available']);
        $this->assertFalse($result['robust']);
        $this->assertSame(1, $result['sensitivity']['leave_event_out_abstentions']);
        $rows = array_slice($this->rows(), 0, 2);
        $result = (new CohortTransitionAbilityModel)->estimate(1, 'u11 boys', $rows, $this->native(), CarbonImmutable::parse('2026-04-01'));
        $this->assertSame(2, $result['sensitivity']['leave_peer_out_abstentions']);
    }

    public function test_target_time_state_responds_to_gap_and_failed_convergence_withholds(): void
    {
        $model = new CohortTransitionAbilityModel;
        $near = $model->estimate(1, 'u11 boys', $this->rows(), $this->native(), CarbonImmutable::parse('2026-02-01'));
        $far = $model->estimate(1, 'u11 boys', $this->rows(), $this->native(), CarbonImmutable::parse('2027-02-01'));
        $this->assertNotEqualsWithDelta($near['strength'], $far['strength'], 0.0001);
        $this->assertGreaterThan($near['support']['gap_days'], $far['support']['gap_days']);
        $failed = new class extends CohortTransitionAbilityModel { protected const ITERATIONS = 0; };
        $this->assertSame('nonconverged', $failed->estimate(1, 'u11 boys', $this->rows(), $this->native(), CarbonImmutable::parse('2026-04-01'))['reason']);
    }

    public function test_removing_a_reference_peer_removes_its_hidden_raw_match_bridge(): void
    {
        $template = $this->rows()[0];
        $rows = [array_replace($template, ['source_id' => 'bridge', 'winner' => 1, 'loser' => 2, 'event_id' => 1]),
            array_replace($template, ['source_id' => 'reference3', 'winner' => 2, 'loser' => 3, 'event_id' => 2]),
            array_replace($template, ['source_id' => 'reference4', 'winner' => 2, 'loser' => 4, 'event_id' => 3])];
        $report = (new CohortTransitionAbilityModel)->estimate(1, 'u11 boys', $rows, $this->native(), CarbonImmutable::parse('2026-04-01'));
        $this->assertTrue($report['available']);
        $this->assertSame(1, $report['sensitivity']['leave_peer_out_abstentions']);
        $this->assertFalse($report['robust']);
    }

    public function test_removing_an_event_without_focal_matches_can_remove_the_only_calibration_bridge(): void
    {
        $template = $this->rows()[0];
        $rows = [array_replace($template, ['source_id' => 'own1', 'winner' => 1, 'loser' => 5, 'event_id' => 1]),
            array_replace($template, ['source_id' => 'own2', 'winner' => 1, 'loser' => 5, 'event_id' => 2])];
        foreach ([2, 3, 4] as $id) { $rows[] = array_replace($template, ['source_id' => 'calibration'.$id, 'winner' => 5, 'loser' => $id, 'event_id' => 9]); }
        $report = (new CohortTransitionAbilityModel)->estimate(1, 'u11 boys', $rows, $this->native(), CarbonImmutable::parse('2026-04-01'));
        $this->assertTrue($report['available']);
        $this->assertSame(2, $report['support']['historical_events']);
        $this->assertSame(1, $report['sensitivity']['leave_event_out_abstentions']);
        $this->assertFalse($report['robust']);
    }

    public function test_an_unrated_intermediary_cannot_hide_a_single_player_bridge_from_robustness_checks(): void
    {
        $rows = [];
        foreach ([1, 2, 3] as $event) {
            foreach ([[1, 5], [5, 1], [5, 2], [2, 5], [5, 3], [3, 5], [5, 4], [4, 5]] as $i => [$winner, $loser]) {
                $rows[] = ['source_id' => $event.':'.$i, 'event_id' => $event, 'cohort' => 'u10 boys',
                    'winner' => $winner, 'loser' => $loser, 'date' => '2026-01-0'.$event,
                    'date_basis' => 'scheduled match date', 'outcome_target' => 0.75, 'context_verified' => true];
            }
        }
        $native = $this->native();
        foreach ($native['ratings'] as &$rating) { $rating['strength'] = 0.0; $rating['score'] = 50.0; }
        unset($rating);
        $report = (new CohortTransitionAbilityModel)->estimate(1, 'u11 boys', $rows, $native, CarbonImmutable::parse('2026-04-01'));
        $this->assertTrue($report['available']);
        $this->assertFalse($report['robust']);
        $this->assertSame(4, $report['sensitivity']['supporting_players_tested']);
        $this->assertSame(1, $report['sensitivity']['leave_nonreference_player_out_abstentions']);
        $this->assertSame(1, $report['sensitivity']['leave_player_out_abstentions']);
        $this->assertSame(0, $report['sensitivity']['leave_peer_out_abstentions']);
    }

    public function test_sensitivity_processing_cap_withholds_instead_of_claiming_partially_checked_robustness(): void
    {
        $template = $this->rows()[0]; $rows = [];
        foreach (range(2, 203) as $other) {
            $rows[] = array_replace($template, ['source_id' => 'source'.$other, 'event_id' => 1, 'winner' => 1, 'loser' => $other]);
        }
        $report = (new CohortTransitionAbilityModel)->estimate(1, 'u11 boys', $rows, $this->native(), CarbonImmutable::parse('2026-04-01'));
        $this->assertFalse($report['available']);
        $this->assertFalse($report['robust']);
        $this->assertSame('sensitivity_processing_limit', $report['reason']);
    }

    public function test_existing_pairs_remain_identical_and_new_pairs_have_separate_denominators(): void
    {
        $native = $this->native();
        $before = $native;
        $rows = [];
        foreach ([[2, 3], [1, 2]] as $i => [$a, $b]) { $rows[] = ['source' => 'Team match', 'fixture_id' => $i + 1, 'reason' => null, 'discipline' => 'singles', 'player1_id' => $a, 'player2_id' => $b, 'winner_side' => 1]; }
        $prediction = ['available' => true, 'robust' => false, 'strength' => 0.3, 'component' => 'target', 'reason' => 'fragile_historical_support'];
        $report = (new CohortTransitionValidationService)->evaluate($native, $rows, [1 => $prediction]);
        $this->assertSame($before, $native);
        $this->assertSame($report['native'], $report['same_pairs_experiment']);
        $this->assertSame(1, $report['native']['evaluated_matches']);
        $this->assertSame(0, $report['robust']['new_pairs']['evaluated_matches']);
        $this->assertSame(1, $report['exploratory_only']['new_pairs']['evaluated_matches']);
        $this->assertSame(2, $report['exploratory_only']['combined']['evaluated_matches']);
    }
}
