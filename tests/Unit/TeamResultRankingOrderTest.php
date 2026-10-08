<?php

namespace Tests\Unit;

use App\Services\TeamResultRankingService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TeamResultRankingOrderTest extends TestCase
{
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
