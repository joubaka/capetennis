<?php

namespace Tests\Unit;

use App\Services\Performance\{AbilityConfidenceDisplay, AllAgeRatingModel};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AllAgeRatingModelTest extends TestCase
{
    private function match(int $winner, int $loser, string $cohort, string $source, string $date = '2026-10-01', ?string $ball = 'yellow'): array
    {
        return ['winner' => $winner, 'loser' => $loser, 'cohort' => $cohort, 'category' => $cohort, 'source_id' => $source,
            'event_id' => 1, 'event_name' => 'Played tennis', 'confidence_date' => $date,
            'confidence_date_basis' => 'scheduled match date', 'ball_type' => $ball, 'outcome_target' => 1.0];
    }

    public function test_actual_shared_opponents_connect_age_groups_on_one_scale_without_age_offsets(): void
    {
        $model = new AllAgeRatingModel;
        $matches = [$this->match(1, 2, 'u10 boys', 'a'), $this->match(2, 3, 'u13 boys', 'b'), $this->match(3, 4, 'u18 boys', 'c')];
        $fit = $model->fit($matches, CarbonImmutable::parse('2026-10-02'));
        $this->assertCount(1, $fit['components']);
        $this->assertGreaterThan($fit['ratings'][4]['score'], $fit['ratings'][1]['score']);
        $this->assertSame($fit['ratings'][1]['component'], $fit['ratings'][4]['component']);
        $this->assertSame(['u10 boys', 'u13 boys', 'u18 boys'], $fit['ratings'][1]['component_age_groups']);
        foreach ($matches as &$match) { $match['cohort'] = $match['category'] = 'u18 girls'; } unset($match);
        $relabeled = $model->fit($matches, CarbonImmutable::parse('2026-10-02'));
        $this->assertSame(array_column($fit['ratings'], 'score'), array_column($relabeled['ratings'], 'score'));
    }

    public function test_disconnected_results_never_receive_a_falsely_comparable_shared_score(): void
    {
        $fit = (new AllAgeRatingModel)->fit([$this->match(1, 2, 'u13 boys', 'a'), $this->match(2, 3, 'u18 boys', 'b'), $this->match(10, 11, 'u10 girls', 'c')], CarbonImmutable::parse('2026-10-02'));
        $this->assertTrue($fit['ratings'][1]['comparable']);
        $this->assertFalse($fit['ratings'][10]['comparable']);
        $this->assertNull($fit['ratings'][10]['score']);
        $this->assertNotNull($fit['ratings'][10]['local_score']);
        $this->assertSame('disconnected', $fit['ratings'][10]['rating_status']);
    }

    public function test_ancient_bridge_cannot_make_recent_disconnected_groups_comparable(): void
    {
        $fit = (new AllAgeRatingModel)->fit([$this->match(1, 2, 'u13 boys', 'a'), $this->match(3, 4, 'u18 boys', 'b'), $this->match(2, 3, 'u15 boys', 'old', '2024-10-01')], CarbonImmutable::parse('2026-10-02'));
        $this->assertCount(2, $fit['components']);
        $this->assertSame(1, $fit['excluded']['Outside the supported played-match window']);
        $this->assertNull($fit['ratings'][3]['score']);
    }

    public function test_modified_ball_and_unknown_young_formats_never_join_standard_graph(): void
    {
        $model = new AllAgeRatingModel;
        foreach (['red', 'orange', 'green', 'unrecognized'] as $ball) { $this->assertFalse($model->format('U13 boys', $ball)['eligible']); }
        foreach (['U7 boys', 'U9 girls', 'U10A boys', 'Open', 'Unknown'] as $category) { $this->assertFalse($model->format($category, null)['eligible']); }
        $this->assertTrue($model->format('U10 boys', 'yellow')['eligible']);
        $this->assertFalse($model->format('U13 green ball', 'yellow')['eligible']);
        $this->assertFalse($model->format('U13 short court', 'standard')['eligible']);
        $this->assertTrue($model->format('U13B boys', null)['assumed']);
        $fit = $model->fit([$this->match(1, 2, 'u13 boys', 'a'), $this->match(2, 3, 'u10 boys', 'unknown', ball: null), $this->match(2, 4, 'u18 boys', 'green', ball: 'green')], CarbonImmutable::parse('2026-10-02'));
        $this->assertSame([1, 2], array_keys($fit['ratings']));
        $this->assertSame(2, array_sum($fit['excluded']));
    }

    public function test_duplicate_source_and_input_order_do_not_change_ratings_or_counts(): void
    {
        $model = new AllAgeRatingModel;
        $matches = [$this->match(1, 2, 'u13 boys', 'a'), $this->match(2, 3, 'u18 boys', 'b')];
        $fit = $model->fit($matches, CarbonImmutable::parse('2026-10-02'));
        $this->assertSame($fit, $model->fit(array_reverse([...$matches, $matches[0]]), CarbonImmutable::parse('2026-10-02')));
        $this->assertSame(2, $fit['ratings'][2]['played']);
        $this->assertSame(2, $fit['components'][$fit['reference_component']]['played']);
    }

    public function test_recent_results_have_more_weight_and_future_results_are_excluded(): void
    {
        $fit = (new AllAgeRatingModel)->fit([$this->match(1, 2, 'u13 boys', 'old', '2026-01-01'), $this->match(2, 1, 'u18 boys', 'new'), $this->match(1, 3, 'u18 boys', 'future', '2026-10-03')], CarbonImmutable::parse('2026-10-02'));
        $this->assertGreaterThan($fit['ratings'][1]['score'], $fit['ratings'][2]['score']);
        $this->assertArrayNotHasKey(3, $fit['ratings']);
    }

    public function test_legacy_assumptions_remain_visible_and_never_receive_high_evidence_label(): void
    {
        $fit = (new AllAgeRatingModel)->fit([$this->match(1, 2, 'u13 boys', 'legacy', ball: null)], CarbonImmutable::parse('2026-10-02'));
        $this->assertSame(1, $fit['ratings'][1]['standard_assumed_matches']);
        $this->assertStringContainsString('assume standard', $fit['ratings'][1]['confidence_explanation']);
        $context = ['rating_scope' => 'all_age', 'comparable' => true, 'confidence_index' => 99, 'played' => 20, 'component_inferred' => 0, 'bridge_count' => 0];
        $this->assertSame('High', AbilityConfidenceDisplay::normalize($context)['confidence_label']);
        $this->assertSame('Medium', AbilityConfidenceDisplay::normalize($context + ['standard_assumed_matches' => 1])['confidence_label']);
        $this->assertStringNotContainsString('main-trial', AbilityConfidenceDisplay::normalize($context)['confidence_explanation']);
    }
}
