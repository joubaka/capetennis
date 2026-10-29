<?php

namespace Tests\Unit;

use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, SharedAbilityModel};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AllAgeReducedShrinkageTest extends TestCase
{
    private function evidenceRows(): array
    {
        return array_map(fn ($pair, $i) => ['winner' => $pair[0], 'loser' => $pair[1], 'source_id' => 'match:'.$i,
            'event_id' => 1, 'event_name' => 'Synthetic girls singles', 'category' => 'U13 girls', 'cohort' => 'u13 girls',
            'confidence_date' => '2026-10-01', 'confidence_date_basis' => 'scheduled match date', 'ball_type' => 'yellow', 'outcome_target' => 0.9],
            [[1, 2], [2, 3], [1, 3]], [1, 2, 3]);
    }

    public function test_active_all_age_fit_matches_previous_sixteenfold_evidence_without_changing_topology_or_event_cap(): void
    {
        $date = CarbonImmutable::parse('2026-10-02');
        $active = (new AllAgeRatingModel)->fit($this->evidenceRows(), $date);
        $edges = $active['history_evidence']['matches'];
        $old = (new SharedAbilityModel)->fit($edges, AllAgeRatingModel::COHORT, regularization: 0.25);
        $doubled = (new SharedAbilityModel)->fit(array_map(fn ($e) => array_replace($e, ['weight' => $e['weight'] * 16]), $edges), AllAgeRatingModel::COHORT, regularization: 0.25);
        foreach ($active['ratings'] as $id => $rating) {
            $this->assertEqualsWithDelta($doubled['ratings'][$id]['strength'], $rating['strength'], 1e-7);
            $this->assertSame($doubled['ratings'][$id]['component'], $rating['component']);
        }
        $this->assertGreaterThan($old['ratings'][1]['strength'], $active['ratings'][1]['strength']);
        $this->assertSame(0.015625, $active['policy']['regularization']);
        foreach ($edges as $edge) { $this->assertSame(0.5, $edge['event_weight']); }
        foreach ($old['components'] as $id => $component) {
            $this->assertSame($component, array_intersect_key($active['components'][$id], $component));
        }
    }

    public function test_legacy_default_stays_half_and_diagnostics_use_selected_regularization(): void
    {
        $edges = array_map(fn ($e) => $e + ['kind' => 'played', 'weight' => 1.0], $this->evidenceRows());
        $model = new SharedAbilityModel;
        $legacy = $model->fit($edges, 'legacy');
        $this->assertSame($legacy, $model->fit($edges, 'legacy', regularization: 0.5));
        $fit = $model->fit($edges, 'girls', experiment: ['diagnostics' => true], regularization: 0.015625);
        $p = fn ($difference) => 1 / (1 + exp(-$difference));
        $expected = 0.015625;
        foreach ([2, 3] as $other) {
            $probability = $p($fit['ratings'][1]['strength'] - $fit['ratings'][$other]['strength']);
            $expected += $probability * (1 - $probability);
        }
        $this->assertEqualsWithDelta($expected, $fit['ratings'][1]['conditional_information'], 1e-10);
        $rules = (new PlayerAbilitySnapshotStore)->rules();
        $this->assertSame(0.015625, $rules['all_age']['regularization']);
        $this->assertSame(0.5, $rules['unanchored_prior']);
    }

    public function test_invalid_regularization_is_rejected_even_without_evidence(): void
    {
        foreach ([0.0, -0.25, NAN, INF] as $value) {
            try { (new SharedAbilityModel)->fit([], regularization: $value); $this->fail('Invalid regularization accepted.'); }
            catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
    }
}

