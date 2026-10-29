<?php

namespace Tests\Unit;

use App\Services\Performance\PlayerAbilityValidationService;
use PHPUnit\Framework\TestCase;

class AllAgeRatingValidationTest extends TestCase
{
    private function training(): array
    {
        return ['all_age' => ['reference_component' => 'shared', 'ratings' => [
            1 => ['score' => 70, 'strength' => 1.0, 'component' => 'shared', 'comparable' => true],
            2 => ['score' => 30, 'strength' => -1.0, 'component' => 'shared', 'comparable' => true],
            3 => ['score' => null, 'strength' => 0.0, 'component' => 'separate', 'comparable' => false],
        ]]];
    }

    private function match(array $changes = []): array
    {
        return array_replace(['source' => 'Individual match', 'fixture_id' => 1, 'reason' => null,
            'discipline' => 'singles', 'category' => 'U10 Boys', 'cohort' => 'u10 boys', 'ball_type' => 'yellow',
            'player1_id' => 1, 'player2_id' => 2, 'winner_side' => 1], $changes);
    }

    public function test_cross_age_holdouts_use_one_scale_and_report_category_coverage(): void
    {
        $report = (new PlayerAbilityValidationService)->evaluateAllAge($this->training(), [
            $this->match(), $this->match(['fixture_id' => 2, 'category' => 'U18 Boys', 'cohort' => 'u18 boys']),
        ]);
        $p = 1 / (1 + exp(-2));
        $this->assertSame('evaluated', $report['status']);
        $this->assertSame(2, $report['current']['evaluated_matches']);
        $this->assertEqualsWithDelta((1 - $p) ** 2, $report['current']['brier'], 1e-10);
        $this->assertEqualsWithDelta(-log($p), $report['current']['log_loss'], 1e-10);
        $this->assertEquals(1, $report['age_category_groups']['u18 boys']['coverage']);
        $this->assertEquals(0.25, $report['chance_reference']['brier']);
    }

    public function test_disconnected_and_modified_ball_matches_cannot_create_coverage(): void
    {
        $report = (new PlayerAbilityValidationService)->evaluateAllAge($this->training(), [
            $this->match(['player2_id' => 3]),
            $this->match(['fixture_id' => 2, 'ball_type' => 'green']),
            $this->match(['fixture_id' => 3, 'ball_type' => null]),
        ]);
        $this->assertSame('insufficient_coverage', $report['status']);
        $this->assertSame(1, $report['eligible_matches']);
        $this->assertNull($report['current']['brier']);
        $this->assertSame(1, $report['excluded']['no_shared_pre_event_reference']);
    }

    public function test_duplicates_and_invalid_identity_do_not_inflate_results_and_upsets_score_correctly(): void
    {
        $upset = $this->match(['winner_side' => 2]);
        $report = (new PlayerAbilityValidationService)->evaluateAllAge($this->training(), [
            $upset, $upset, $this->match(['fixture_id' => 2, 'player2_id' => 1]),
            $this->match(['fixture_id' => 3, 'discipline' => 'doubles']),
        ]);
        $this->assertSame(1, $report['eligible_matches']);
        $this->assertSame(1, $report['current']['evaluated_matches']);
        $this->assertGreaterThan(0.7, $report['current']['brier']);
        $this->assertSame(1, $report['excluded']['invalid_identity']);
    }
}
