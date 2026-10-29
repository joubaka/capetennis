<?php

namespace Tests\Unit;

use App\Services\Performance\PlayerAbilityValidationService;
use PHPUnit\Framework\TestCase;

class AllAgeHistoricalValidationTest extends TestCase
{
    private function snapshot(bool $historical): array
    {
        $rating = fn ($strength) => ['score' => 50, 'strength' => $strength, 'comparable' => true, 'component' => 'ref'];
        $ratings = [1 => $rating(1.0), 2 => $rating(-1.0)];
        if ($historical) { $ratings[3] = $rating(0.0) + ['historical_connection' => true]; }
        return ['all_age' => ['reference_component' => 'ref', 'ratings' => $ratings]];
    }

    public function test_comparison_reports_coverage_gains_separately_from_identical_pair_losses(): void
    {
        $match = ['source' => 'Individual match', 'discipline' => 'singles', 'reason' => null,
            'fixture_id' => 1, 'player1_id' => 1, 'player2_id' => 2, 'winner_side' => 1,
            'cohort' => 'u13 boys', 'category' => 'U13 boys', 'ball_type' => 'yellow'];
        $report = (new PlayerAbilityValidationService)->compareAllAgeHistory($this->snapshot(false), $this->snapshot(true), [
            $match, array_replace($match, ['fixture_id' => 2, 'player2_id' => 3]),
        ]);
        $this->assertSame(2, $report['current']['evaluated_matches']);
        $this->assertSame(1, $report['historical_comparison']['recent_only']['current']['evaluated_matches']);
        $this->assertSame(1, $report['connection_groups']['historical_connection']['evaluated_matches']);
        $this->assertSame(1, $report['historical_comparison']['paired_recent_only']['evaluated_matches']);
        $this->assertSame(1, $report['historical_comparison']['paired_historical']['evaluated_matches']);
        $this->assertEquals(0, $report['historical_comparison']['paired_brier_change']);
        $this->assertEquals(0, $report['historical_comparison']['paired_log_loss_change']);
    }
}
