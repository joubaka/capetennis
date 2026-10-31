<?php

namespace Tests\Unit;

use App\Services\Draw\{FlexibleMonradCompiler, FlexibleMonradProgression};
use PHPUnit\Framework\TestCase;

class FlexibleMonradRollingChallengesTest extends TestCase
{
    private function graph(array $history = []): array
    {
        $graph = ['nodes' => [], 'positions' => [], 'players' => range(1, 6)];
        foreach (range(1, 6) as $rank) $graph['positions'][$rank] = ['type' => 'player', 'id' => $rank];
        foreach ($history as $key => $pair) $graph['nodes'][$key] = [
            'sources' => array_map(fn ($id) => ['type' => 'player', 'id' => $id], $pair),
        ];
        return FlexibleMonradCompiler::withRollingChallenges($graph);
    }

    private function fixtures(array $graph): array
    {
        return array_map(fn ($node) => ['players' => [null, null], 'winner' => null, 'played' => false], $graph['nodes']);
    }

    private function finish(array $graph, array &$fixtures, string $key, int $winnerSlot): array
    {
        $before = (new FlexibleMonradProgression)->resolve($graph, $fixtures, []);
        $match = $before['matches'][$key];
        $this->assertSame('ready', $match['challenge_status']);
        $fixtures[$key] = ['players' => $match['players'], 'winner' => $match['players'][$winnerSlot], 'played' => true];
        return (new FlexibleMonradProgression)->resolve($graph, $fixtures, []);
    }

    public function test_successive_upsets_follow_current_ranks_even_with_json_sorted_keys(): void
    {
        $graph = $this->graph();
        ksort($graph['nodes']);
        $fixtures = $this->fixtures($graph);
        $pending = (new FlexibleMonradProgression)->resolve($graph, $fixtures, []);
        $this->assertSame('pending', $pending['matches']['challenge_3']['challenge_status']);
        $this->assertSame([null, null], $pending['matches']['challenge_2_repeat']['players']);
        $this->finish($graph, $fixtures, 'challenge_2', 1);
        $middle = $this->finish($graph, $fixtures, 'challenge_4', 1);
        $this->assertSame([2, 5], $middle['matches']['challenge_3']['players']);
        $next = $this->finish($graph, $fixtures, 'challenge_3', 1);
        $this->assertSame([3, 5], $next['matches']['challenge_2_repeat']['players']);
        $final = $this->finish($graph, $fixtures, 'challenge_2_repeat', 1);
        $this->assertSame([1, 5, 3, 2, 4, 6], array_column($final['positions'], 'player'));
        $this->assertTrue($final['positions_complete']);
        $this->assertSame(range(1, 6), array_column($final['base_positions'], 'player'));
        $this->assertSame($final, (new FlexibleMonradProgression)->resolve($graph, $fixtures, []));
    }

    public function test_unchanged_third_skips_repeat_and_prior_meeting_skips_new_pair(): void
    {
        $graph = $this->graph();
        $fixtures = $this->fixtures($graph);
        $this->finish($graph, $fixtures, 'challenge_2', 0);
        $this->finish($graph, $fixtures, 'challenge_4', 0);
        $final = $this->finish($graph, $fixtures, 'challenge_3', 0);
        $this->assertSame('skipped', $final['matches']['challenge_2_repeat']['challenge_status']);
        $this->assertStringContainsString('did not change', $final['matches']['challenge_2_repeat']['skip_reason']);

        $graph = $this->graph(['earlier' => [5, 3]]);
        $fixtures = $this->fixtures($graph);
        $fixtures['earlier'] = ['players' => [5, 3], 'winner' => 3, 'played' => true];
        $this->finish($graph, $fixtures, 'challenge_2', 1);
        $this->finish($graph, $fixtures, 'challenge_4', 1);
        $final = $this->finish($graph, $fixtures, 'challenge_3', 1);
        $this->assertSame('skipped', $final['matches']['challenge_2_repeat']['challenge_status']);
        $this->assertStringContainsString('already played', $final['matches']['challenge_2_repeat']['skip_reason']);
    }

    public function test_unrelated_lower_placement_does_not_block_top_five_but_relevant_base_match_does(): void
    {
        $graph = $this->graph();
        $graph['nodes']['top'] = ['sources' => [['type' => 'player', 'id' => 2], ['type' => 'player', 'id' => 3]]];
        $graph['positions'][2] = ['type' => 'winner', 'match' => 'top'];
        $graph['positions'][3] = ['type' => 'loser', 'match' => 'top'];
        $graph['nodes']['lower'] = ['sources' => [['type' => 'player', 'id' => 6], ['type' => 'player', 'id' => 7]]];
        $graph = FlexibleMonradCompiler::withRollingChallenges($graph);
        $fixtures = $this->fixtures($graph);
        $before = (new FlexibleMonradProgression)->resolve($graph, $fixtures, []);
        $this->assertSame('pending', $before['matches']['challenge_4']['challenge_status']);
        $fixtures['top'] = ['players' => [2, 3], 'winner' => 2, 'played' => true];
        $after = (new FlexibleMonradProgression)->resolve($graph, $fixtures, []);
        $this->assertSame('skipped', $after['matches']['challenge_2']['challenge_status']);
        $this->assertSame('ready', $after['matches']['challenge_4']['challenge_status']);
        $this->assertFalse($after['positions_complete']);
        $this->assertNotContains('lower', FlexibleMonradCompiler::dependencies($graph['nodes']['challenge_4']));
    }

    public function test_scheduler_reserves_every_possible_current_rank_player_until_the_challenges_resolve(): void
    {
        $graph = $this->graph();
        $fixtures = $this->fixtures($graph);
        $state = (new FlexibleMonradProgression)->resolve($graph, $fixtures, []);
        $matches = [];
        foreach ($graph['nodes'] as $key => $node) $matches[$key] = $node + $state['matches'][$key];
        $pools = \App\Domain\Draws\Services\ScheduleAvailability::participants($matches);
        $this->assertEqualsCanonicalizing([2, 3, 4, 5], $pools['challenge_3']);
        $this->assertEqualsCanonicalizing([2, 3, 4, 5], $pools['challenge_2_repeat']);
        $this->finish($graph, $fixtures, 'challenge_2', 1);
        $state = $this->finish($graph, $fixtures, 'challenge_4', 1);
        foreach ($graph['nodes'] as $key => $node) $matches[$key] = $node + $state['matches'][$key];
        $pools = \App\Domain\Draws\Services\ScheduleAvailability::participants($matches);
        $this->assertSame([2, 5], $pools['challenge_3']);
    }
}
