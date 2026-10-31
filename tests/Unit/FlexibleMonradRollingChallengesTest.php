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
        $this->assertSame('ready', $before['matches']['challenge_4']['challenge_status']);
        $this->assertSame('pending', $before['matches']['challenge_2']['challenge_status']);
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

    public function test_every_valid_stage_combination_resolves_omissions_as_no_ops_without_changing_other_positions(): void
    {
        $tested = 0;
        for ($mask = 0; $mask < 16; $mask++) {
            $stages = array_values(array_filter(FlexibleMonradCompiler::ROLLING_STAGES,
                fn ($key) => (bool) ($mask & (1 << array_search($key, FlexibleMonradCompiler::ROLLING_STAGES, true)))));
            if (in_array('challenge_2_repeat', $stages, true) && ! in_array('challenge_3', $stages, true)) continue;
            $tested++;
            $graph = FlexibleMonradCompiler::withRollingChallenges($this->graph(), $stages);
            $fixtures = $this->fixtures($graph);
            foreach (['challenge_2', 'challenge_4', 'challenge_3', 'challenge_2_repeat'] as $key) {
                $state = (new FlexibleMonradProgression)->resolve($graph, $fixtures, []);
                $match = $state['matches'][$key];
                if (! in_array($key, $stages, true)) {
                    $this->assertSame('disabled', $match['challenge_status']);
                    $this->assertSame([null, null], $match['players']);
                    $this->assertNull($match['winner']);
                } elseif ($match['challenge_status'] === 'ready') {
                    $this->finish($graph, $fixtures, $key, 1);
                } else $this->assertSame('skipped', $match['challenge_status']);
            }
            $final = (new FlexibleMonradProgression)->resolve($graph, $fixtures, []);
            $this->assertTrue($final['positions_complete']);
            $this->assertEqualsCanonicalizing(range(1, 6), array_column($final['positions'], 'player'));
            $this->assertSame(1, $final['positions'][0]['player']);
            $this->assertSame(6, $final['positions'][5]['player']);
            $this->assertSame($stages, FlexibleMonradCompiler::selectedRollingStages($graph));
        }
        $this->assertSame(12, $tested);
        $none = FlexibleMonradCompiler::withRollingChallenges($this->graph(), []);
        $state = (new FlexibleMonradProgression)->resolve($none, $this->fixtures($none), []);
        $this->assertSame(range(1, 6), array_column($state['positions'], 'player'));
    }

    public function test_legacy_all_four_selection_and_independent_later_stage_ranks_are_preserved(): void
    {
        $graph = $this->graph();
        unset($graph['rolling_challenge_stages']);
        $this->assertSame(FlexibleMonradCompiler::ROLLING_STAGES, FlexibleMonradCompiler::selectedRollingStages($graph));
        $graph = FlexibleMonradCompiler::withRollingChallenges($graph, ['challenge_3', 'challenge_2_repeat']);
        $fixtures = $this->fixtures($graph);
        $state = (new FlexibleMonradProgression)->resolve($graph, $fixtures, []);
        $this->assertSame([3, 4], $state['matches']['challenge_3']['players']);
        $this->assertSame([], $graph['nodes']['challenge_3']['prerequisites']);
        $state = $this->finish($graph, $fixtures, 'challenge_3', 1);
        $this->assertSame([2, 4], $state['matches']['challenge_2_repeat']['players']);
        $this->assertSame(['challenge_3'], $graph['nodes']['challenge_2_repeat']['prerequisites']);
    }

    public function test_previous_four_only_configuration_retains_untouched_six_seven_and_eight_nine_pairs(): void
    {
        $graph = $this->graph();
        $graph['players'] = range(1, 16);
        foreach (range(7, 16) as $rank) $graph['positions'][$rank] = ['type' => 'player', 'id' => $rank];
        foreach ([6, 8] as $upper) $graph['nodes']['challenge_'.$upper] = [
            'sources' => [$graph['positions'][$upper] + ['base_position' => $upper], $graph['positions'][$upper + 1] + ['base_position' => $upper + 1]],
            'challenge_positions' => [$upper, $upper + 1], 'prerequisites' => [],
        ];
        $selected = FlexibleMonradCompiler::selectedRollingStages($graph);
        $this->assertContains('challenge_6', $selected);
        $this->assertContains('challenge_8', $selected);
        $updated = FlexibleMonradCompiler::withRollingChallenges($graph, array_values(array_diff($selected, ['challenge_8'])));
        $this->assertTrue($updated['nodes']['challenge_6']['challenge_enabled']);
        $this->assertFalse($updated['nodes']['challenge_8']['challenge_enabled']);
        foreach (FlexibleMonradCompiler::ROLLING_STAGES as $key) $this->assertTrue($updated['nodes'][$key]['challenge_enabled']);
        $off = FlexibleMonradCompiler::withRollingChallenges($updated, []);
        $this->assertSame([], FlexibleMonradCompiler::selectedRollingStages($off));
        $state = (new FlexibleMonradProgression)->resolve($off, $this->fixtures($off), []);
        $this->assertSame(range(1, 16), array_column($state['positions'], 'player'));
        $this->assertSame('disabled', $state['matches']['challenge_6']['challenge_status']);
        $this->assertSame('disabled', $state['matches']['challenge_8']['challenge_status']);
    }

    public function test_options_follow_actual_entrants_instead_of_bracket_capacity_and_higher_pairs_count_independently(): void
    {
        $graph = $this->graph();
        $graph['players'] = range(1, 12);
        foreach (range(7, 16) as $rank) $graph['positions'][$rank] = ['type' => 'player', 'id' => $rank];
        $options = FlexibleMonradCompiler::challengeOptions($graph);
        $this->assertArrayHasKey('challenge_10', $options['base']);
        $this->assertArrayNotHasKey('challenge_12', $options['base']);
        $this->assertArrayNotHasKey('challenge_14', $options['base']);
        $graph['players'] = range(1, 16);
        $this->assertArrayHasKey('challenge_14', FlexibleMonradCompiler::challengeOptions($graph)['base']);
        $graph = FlexibleMonradCompiler::withRollingChallenges($graph, ['challenge_6', 'challenge_8', 'challenge_14']);
        $projection = (new FlexibleMonradProgression)->resolve($graph, $this->fixtures($graph), []);
        $matches = []; $bookings = [];
        foreach ($graph['nodes'] as $key => $node) {
            $id = count($matches) + 1;
            $matches[$key] = $node + $projection['matches'][$key] + ['id' => $id];
            $bookings['individual:'.$id] = '2026-11-01';
        }
        $counts = \App\Services\Scheduling\DailyMatchLimit::pathCounts($matches, $bookings, '2026-11-01', []);
        foreach ([6, 7, 8, 9, 14, 15] as $player) $this->assertSame(1, $counts['registration:'.$player]);
        foreach ([1, 2, 3, 4, 5, 10, 11, 12, 13, 16] as $player) $this->assertArrayNotHasKey('registration:'.$player, $counts);
    }
}
