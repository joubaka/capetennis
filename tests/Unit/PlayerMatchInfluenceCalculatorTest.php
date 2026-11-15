<?php

namespace Tests\Unit;

use App\Services\Performance\{AllAgeRatingModel, PlayerMatchInfluenceCalculator};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PlayerMatchInfluenceCalculatorTest extends TestCase
{
    private function fixtureMatches(): array
    {
        $rows = [];
        foreach (['2026-06-14', '2026-07-12', '2026-08-09', '2026-09-13', '2026-10-11', '2026-11-08'] as $month => $date) {
            foreach ([[1, 2, in_array($month, [2, 3], true) ? .8 : 1], [3, 1, in_array($month, [1, 4, 5], true) ? .8 : 1]] as $index => [$winner, $loser, $target]) {
                $rows[] = ['winner' => $winner, 'loser' => $loser, 'source_id' => 'match:'.($month * 2 + $index + 1),
                    'event_id' => $month + 1, 'event_name' => 'Fictional event', 'cohort' => 'u10 boys', 'category' => 'u10 boys',
                    'confidence_date' => $date, 'confidence_date_basis' => 'scheduled match date', 'ball_type' => 'green', 'outcome_target' => $target];
            }
        }
        return $rows;
    }

    public function test_last_five_are_deterministic_and_delta_matches_an_independent_canonical_refit_without_mutating_inputs(): void
    {
        $model = new AllAgeRatingModel;
        $date = CarbonImmutable::parse('2026-11-15');
        $graph = $model->fit($this->fixtureMatches(), $date);
        $before = serialize($graph);
        $result = (new PlayerMatchInfluenceCalculator($model))->calculate(1, $graph, $date);
        $this->assertSame($before, serialize($graph));
        $this->assertSame($graph['ratings'][1]['score'], $result['baseline_score']);
        $this->assertSame(['match:11', 'match:12', 'match:10', 'match:9', 'match:7'], array_column($result['rows'], 'source_id'));
        foreach ($result['rows'] as $row) {
            $without = $model->fit(array_values(array_filter($this->fixtureMatches(), fn ($m) => $m['source_id'] !== $row['source_id'])), $date);
            if (($without['ratings'][1]['comparable'] ?? false) && ($without['components'][$without['ratings'][1]['component']]['converged'] ?? false)) {
                $this->assertSame(round($result['baseline_score'] - $without['ratings'][1]['score'], 1), $row['delta']);
            } else {
                $this->assertNull($row['delta']);
                $this->assertNotEmpty($row['reason']);
            }
            $this->assertNotSame(1, $row['opponent_id']);
        }
    }

    public function test_future_expired_and_duplicate_sources_are_not_selected_as_latest_eligible_matches(): void
    {
        $model = new AllAgeRatingModel;
        $date = CarbonImmutable::parse('2026-11-15');
        $graph = $model->fit($this->fixtureMatches(), $date);
        $extra = $graph['history_evidence']['matches'][0];
        $graph['history_evidence']['matches'][] = $extra;
        foreach (['future' => '2027-01-01', 'expired' => '2020-01-01'] as $id => $when) {
            $extra['source_id'] = $id; $extra['confidence_date'] = $when;
            $graph['history_evidence']['matches'][] = $extra;
        }
        $result = (new PlayerMatchInfluenceCalculator($model))->calculate(1, $graph, $date);
        $this->assertCount(5, $result['rows']);
        $this->assertSame(['match:11', 'match:12', 'match:10', 'match:9', 'match:7'], array_column($result['rows'], 'source_id'));
    }

    public function test_changed_component_reference_backbone_or_connection_withholds_numeric_influence(): void
    {
        $date = CarbonImmutable::parse('2026-11-15');
        $baseline = (new AllAgeRatingModel)->fit([$this->fixtureMatches()[0], $this->fixtureMatches()[1]], $date);
        foreach (['component', 'reference', 'backbone', 'connection', 'nonconverged'] as $change) {
            $changed = $baseline;
            $component = $changed['ratings'][1]['component'];
            match ($change) {
                'component' => $changed['components'][$component]['members'] = [1, 2],
                'reference' => $changed['reference_component'] = 'different',
                'backbone' => $changed['recent_backbone']['members'] = [1, 2],
                'connection' => $changed['ratings'][1]['connection_status'] = 'historical',
                'nonconverged' => $changed['components'][$component]['converged'] = false,
            };
            $model = $this->createStub(AllAgeRatingModel::class);
            $model->method('fit')->willReturnOnConsecutiveCalls($baseline, $changed, $changed);
            $result = (new PlayerMatchInfluenceCalculator($model))->calculate(1, $baseline, $date);
            foreach ($result['rows'] as $row) { $this->assertNull($row['delta']); $this->assertNotEmpty($row['reason']); }
        }
    }

    public function test_saved_baseline_must_reproduce_before_any_influence_can_be_returned(): void
    {
        $date = CarbonImmutable::parse('2026-11-15');
        $model = new AllAgeRatingModel;
        $graph = $model->fit($this->fixtureMatches(), $date);
        $graph['ratings'][1]['score'] += 1;
        $this->expectException(\RuntimeException::class);
        (new PlayerMatchInfluenceCalculator($model))->calculate(1, $graph, $date);
    }
}

