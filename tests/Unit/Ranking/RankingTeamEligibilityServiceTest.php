<?php

namespace Tests\Unit\Ranking;

use App\Domain\Ranking\Services\RankingTeamEligibilityService;
use App\Models\Series;
use App\Models\SeriesRanking;
use PHPUnit\Framework\TestCase;

class RankingTeamEligibilityServiceTest extends TestCase
{
    public function test_one_actual_event_is_ineligible_when_two_are_required(): void
    {
        $series = new Series(['minimum_events_for_team_selection' => 2]);
        $ranking = new SeriesRanking(['meta_json' => [
            'events_played' => 1,
            'counting_legs' => [['category_event_id' => 101, 'position' => 1, 'synthetic' => false]],
        ]]);

        $assessment = (new RankingTeamEligibilityService())->assess($ranking, $series);

        $this->assertFalse($assessment['eligible']);
        $this->assertSame(1, $assessment['events_played']);
        $this->assertSame(2, $assessment['minimum_events']);
    }

    public function test_two_actual_events_are_eligible(): void
    {
        $series = new Series(['minimum_events_for_team_selection' => 2]);
        $ranking = new SeriesRanking(['meta_json' => ['events_played' => 2]]);

        $this->assertTrue((new RankingTeamEligibilityService())->assess($ranking, $series)['eligible']);
    }

    public function test_synthetic_award_does_not_count_in_legacy_metadata(): void
    {
        $series = new Series(['minimum_events_for_team_selection' => 2]);
        $ranking = new SeriesRanking(['meta_json' => [
            'counting_legs' => [
                ['category_event_id' => 101, 'position' => 1, 'synthetic' => false],
                ['category_event_id' => 102, 'position' => 1, 'synthetic' => true],
            ],
            'dropped_legs' => [],
        ]]);

        $assessment = (new RankingTeamEligibilityService())->assess($ranking, $series);

        $this->assertSame(1, $assessment['events_played']);
        $this->assertFalse($assessment['eligible']);
    }
}
