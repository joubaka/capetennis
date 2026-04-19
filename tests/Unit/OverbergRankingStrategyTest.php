<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Services\Ranking\Strategies\OverbergRankingStrategy;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OverbergRankingStrategyTest extends TestCase
{
    private OverbergRankingStrategy $strategy;
    private Series $series;

    /** Simple points map: position 1 = 10pts, 2 = 8pts, 3 = 6pts, 4 = 4pts */
    private array $pointsMap = [1 => 10, 2 => 8, 3 => 6, 4 => 4];

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new OverbergRankingStrategy();
        $this->series = new Series();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function placement(int $playerId, int $eventId, int $position): object
    {
        return (object) [
            'player_id' => $playerId,
            'event_id' => $eventId,
            'position' => $position,
        ];
    }

    private function placements(array $rows): Collection
    {
        return collect(array_map(
            fn($r) => $this->placement($r[0], $r[1], $r[2]),
            $rows
        ));
    }

    // -----------------------------------------------------------------------
    // Basic ranking
    // -----------------------------------------------------------------------

    public function test_players_are_ranked_by_best_two_events(): void
    {
        // Player 1: 1st (10) + 2nd (8) = 18
        // Player 2: 2nd (8) + 3rd (6) = 14
        $placements = $this->placements([
            [1, 1, 1], // player 1, event 1, pos 1
            [1, 2, 2], // player 1, event 2, pos 2
            [2, 1, 2], // player 2, event 1, pos 2
            [2, 2, 3], // player 2, event 2, pos 3
        ]);

        $result = $this->strategy->rank($placements, $this->pointsMap, $this->series);

        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['player_id']);
        $this->assertEquals(18, $result[0]['total']);
        $this->assertEquals(2, $result[1]['player_id']);
        $this->assertEquals(14, $result[1]['total']);
    }

    public function test_only_best_two_scores_count_when_three_events_played(): void
    {
        // Player 1: events 1(10), 2(8), 3(6) → best two = 18
        $placements = $this->placements([
            [1, 1, 1],
            [1, 2, 2],
            [1, 3, 3],
        ]);

        $result = $this->strategy->rank($placements, $this->pointsMap, $this->series);

        $this->assertCount(1, $result);
        $this->assertEquals(18, $result[0]['total']);
        $this->assertEquals(6, $result[0]['third']); // third event's pts kept for tiebreak
    }

    public function test_tiebreak_uses_third_best_score(): void
    {
        // Player 1: 10+8 = 18, third = 6
        // Player 2: 10+8 = 18, third = 4
        $placements = $this->placements([
            [1, 1, 1],
            [1, 2, 2],
            [1, 3, 3], // 6 pts third
            [2, 1, 1],
            [2, 2, 2],
            [2, 3, 4], // 4 pts third
        ]);

        $result = $this->strategy->rank($placements, $this->pointsMap, $this->series);

        $this->assertEquals(1, $result[0]['player_id']); // wins tiebreak
        $this->assertEquals(2, $result[1]['player_id']);
    }

    public function test_player_with_zero_points_is_excluded(): void
    {
        // Player points map has no entry for position 5, so zero → excluded
        $placements = $this->placements([
            [99, 1, 5], // position 5 → 0 pts
        ]);

        $result = $this->strategy->rank($placements, $this->pointsMap, $this->series);

        $this->assertCount(0, $result);
    }

    public function test_empty_placements_returns_empty_array(): void
    {
        $result = $this->strategy->rank(collect(), $this->pointsMap, $this->series);

        $this->assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // Auto-award logic
    // -----------------------------------------------------------------------

    public function test_auto_award_granted_to_player_who_won_both_played_legs(): void
    {
        // 3-event series; player 1 wins events 1 & 2, misses event 3
        // → auto-awarded position 1 in event 3
        $placements = $this->placements([
            [1, 1, 1], // player 1 wins event 1
            [1, 2, 1], // player 1 wins event 2
            [2, 1, 2], // player 2, 2nd in event 1
            [2, 2, 2], // player 2, 2nd in event 2
            [2, 3, 1], // player 2, 1st in event 3
            [3, 3, 2], // player 3, 2nd in event 3
        ]);

        $result = $this->strategy->rank($placements, $this->pointsMap, $this->series);

        $player1 = collect($result)->firstWhere('player_id', 1);
        $this->assertNotNull($player1);
        $this->assertTrue($player1['meta']['auto_award']);

        // Player 1 should have 3 legs after auto-award injection
        $this->assertCount(3, $player1['meta']['legs']);
    }

    public function test_auto_award_not_granted_when_player_did_not_win_both_legs(): void
    {
        // Player 1 played 2 events but came 2nd in one → no auto-award
        $placements = $this->placements([
            [1, 1, 1], // player 1 wins event 1
            [1, 2, 2], // player 1 2nd in event 2
            [2, 3, 1], // player 2, event 3
        ]);

        $result = $this->strategy->rank($placements, $this->pointsMap, $this->series);

        $player1 = collect($result)->firstWhere('player_id', 1);
        if ($player1) {
            $this->assertFalse($player1['meta']['auto_award']);
        }
    }

    // -----------------------------------------------------------------------
    // Leg metadata
    // -----------------------------------------------------------------------

    public function test_leg_metadata_marks_counted_and_dropped_correctly(): void
    {
        $placements = $this->placements([
            [1, 1, 1], // best → counted
            [1, 2, 2], // 2nd best → counted
            [1, 3, 3], // worst → dropped
        ]);

        $result = $this->strategy->rank($placements, $this->pointsMap, $this->series);
        $legs = $result[0]['meta']['legs'];

        $counted = collect($legs)->where('status', 'counted')->count();
        $dropped = collect($legs)->where('status', 'dropped')->count();

        $this->assertEquals(2, $counted);
        $this->assertEquals(1, $dropped);
    }

    public function test_result_contains_required_keys(): void
    {
        $placements = $this->placements([[1, 1, 1]]);

        $result = $this->strategy->rank($placements, $this->pointsMap, $this->series);

        $this->assertArrayHasKey('player_id', $result[0]);
        $this->assertArrayHasKey('total', $result[0]);
        $this->assertArrayHasKey('third', $result[0]);
        $this->assertArrayHasKey('meta', $result[0]);
        $this->assertArrayHasKey('legs', $result[0]['meta']);
        $this->assertArrayHasKey('auto_award', $result[0]['meta']);
    }
}
