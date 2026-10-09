<?php

namespace Tests\Unit;

use App\Services\TeamResultRankingService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TeamResultRankingOrderTest extends TestCase
{
    public function test_equal_points_teammates_follow_roster_before_sets_and_other_teams_use_sets(): void
    {
        $simeon = array_replace($this->row(10, 70, [], 'Simeon'), ['rank' => 3, 'ranks' => [3], 'source_team_ids' => [1], 'set_difference' => 3]);
        $jean = array_replace($this->row(11, 70, [], 'Jean'), ['rank' => 4, 'ranks' => [4], 'source_team_ids' => [1], 'set_difference' => 4]);
        $other = array_replace($this->row(12, 70, [], 'Other'), ['rank' => 3, 'ranks' => [3], 'source_team_ids' => [2], 'set_difference' => 5]);
        $service = new TeamResultRankingService;
        foreach ([[$jean, $simeon, $other], [$simeon, $other, $jean], [$other, $jean, $simeon], [$jean, $other, $simeon], [$simeon, $jean, $other], [$other, $simeon, $jean]] as $rows) {
            $ranking = $service->orderRanking(new Collection($rows));
            $this->assertSame([12, 10, 11], $ranking->pluck('id')->all());
            $this->assertTrue($ranking[1]['same_team_tiebreak']);
        }
        $leaders = new Collection(array_map(fn ($id) => $this->row($id, 1000 - $id, []), range(1, 9)));
        $cutoff = $service->orderRanking($leaders->concat([$jean, $simeon]));
        $this->assertSame([10, 11], $cutoff->slice(9)->pluck('id')->all());
        $this->assertSame([10, 11], $cutoff->slice(9)->pluck('position')->all());
        $this->assertCount(0, $cutoff->where('cutoff_tie', true));
        $this->assertTrue($cutoff[9]['suggested']);
    }

    public function test_mixed_team_cycle_uses_highest_remaining_roster_candidates(): void
    {
        $higher = array_replace($this->row(1, 70, []), ['source_team_ids' => [1], 'set_difference' => 1]);
        $lower = array_replace($this->row(2, 70, []), ['source_team_ids' => [1], 'rank' => 2, 'ranks' => [2], 'set_difference' => 3]);
        $other = array_replace($this->row(3, 70, []), ['set_difference' => 2]);
        $service = new TeamResultRankingService;
        $ranking = $service->orderRanking(new Collection([$lower, $higher, $other]));
        $this->assertSame([3, 1, 2], $ranking->pluck('id')->all());
        $this->assertSame($ranking->all(), $service->orderRanking(new Collection([$other, $lower, $higher]))->all());
    }

    public function test_separated_cross_team_tie_spanning_cutoff_still_requires_review(): void
    {
        $leaders = new Collection(array_map(fn ($id) => $this->row($id, 1000 - $id, []), range(1, 8)));
        $higher = array_replace($this->row(9, 70, []), ['source_team_ids' => [20], 'set_difference' => 1]);
        $lower = array_replace($this->row(10, 70, []), ['source_team_ids' => [20], 'rank' => 2, 'ranks' => [2], 'set_difference' => 3]);
        $other = array_replace($this->row(11, 70, []), ['set_difference' => 3]);
        $ranking = (new TeamResultRankingService)->orderRanking($leaders->concat([$lower, $higher, $other]));
        $this->assertSame([11, 9, 10], $ranking->slice(8)->pluck('id')->all());
        $this->assertSame([11, 10], $ranking->where('cutoff_tie', true)->pluck('id')->all());
        $this->assertFalse($ranking[8]['suggested']);
        $this->assertTrue($ranking[9]['suggested']);
        $this->assertFalse($ranking[10]['suggested']);
    }

    public function test_zero_win_higher_band_stays_before_winning_lower_band_and_cross_band_cutoff_is_not_a_tie(): void
    {
        $service = new TeamResultRankingService;
        $higher = $this->row(10, 0, []);
        $lower = array_replace($this->row(11, 350, []), ['rank' => 3, 'ranks' => [3]]);
        $ranking = $service->orderRanking(new Collection([$lower, $higher]));
        $this->assertSame([10, 11], $ranking->pluck('id')->all());
        $leaders = new Collection(array_map(fn ($id) => $this->row($id, 1000 - $id, []), range(1, 9)));
        $lower['points'] = 0;
        $cutoff = $service->orderRanking($leaders->concat([$lower, $higher]));
        $this->assertSame([10, 11], $cutoff->slice(9)->pluck('id')->all());
        $this->assertSame([10, 11], $cutoff->slice(9)->pluck('position')->all());
        $this->assertCount(0, $cutoff->where('cutoff_tie', true));
        $this->assertTrue($cutoff[9]['suggested']);
        $this->assertFalse($cutoff[10]['suggested']);
    }

    public function test_direct_winner_resolves_tenth_place_but_split_results_require_review(): void
    {
        $service = new TeamResultRankingService;
        $leaders = new Collection(array_map(fn ($id) => $this->row($id, 1000 - $id, []), range(1, 9)));
        $winner = $this->row(10, 100, [['opponent_id' => 11, 'won' => true]], 'Zulu');
        $loser = $this->row(11, 100, [['opponent_id' => 10, 'won' => false]], 'Aaron');
        $resolved = $service->orderRanking($leaders->concat([$loser, $winner]));
        $this->assertSame([10, 11], $resolved->slice(9)->pluck('id')->all());
        $this->assertSame([10, 11], $resolved->slice(9)->pluck('position')->all());
        $this->assertTrue($resolved[9]['suggested']);
        $this->assertFalse($resolved[10]['suggested']);
        $this->assertCount(0, $resolved->where('cutoff_tie', true));
        $winner['matches'][] = ['opponent_id' => 11, 'won' => false];
        $loser['matches'][] = ['opponent_id' => 10, 'won' => true];
        $split = $service->orderRanking($leaders->concat([$loser, $winner]));
        $this->assertSame([10, 10], $split->slice(9)->pluck('position')->all());
        $this->assertCount(2, $split->where('cutoff_tie', true));
        $this->assertCount(9, $split->where('suggested', true));
    }

    public function test_unbalanced_three_player_direct_results_remain_shared_in_any_input_order(): void
    {
        $a = $this->row(1, 100, [['opponent_id' => 2, 'won' => true], ['opponent_id' => 2, 'won' => true], ['opponent_id' => 3, 'won' => true]], 'Zulu');
        $b = $this->row(2, 100, [['opponent_id' => 1, 'won' => false], ['opponent_id' => 1, 'won' => false], ['opponent_id' => 3, 'won' => true]], 'Aaron');
        $c = $this->row(3, 100, [['opponent_id' => 1, 'won' => false], ['opponent_id' => 2, 'won' => false]], 'Middle');
        $service = new TeamResultRankingService;
        $ranking = $service->orderRanking(new Collection([$a, $b, $c]));
        $this->assertSame([1, 1, 1], $ranking->pluck('position')->all());
        $this->assertTrue($ranking->every(fn ($row) => ! $row['head_to_head']['applied']));
        $this->assertSame($ranking->all(), $service->orderRanking(new Collection([$c, $a, $b]))->all());
    }

    private function row(int $id, int $points, array $matches, string $name = 'Player'): array
    {
        return ['id' => $id, 'name' => $name, 'points' => $points, 'set_difference' => 0,
            'source_team_ids' => [$id], 'ranks' => [1], 'rank' => 1, 'matches' => $matches];
    }
}
