<?php

namespace Tests\Unit;

use App\Services\Draw\{FlexibleMonradCompiler, FlexibleMonradProgression};
use PHPUnit\Framework\TestCase;

class FlexibleMonradChallengesTest extends TestCase
{
    private function compile(bool $enabled = true): array
    {
        $slots = [];
        for ($i = 0; $i < 16; $i++) {
            $path = strtr(str_pad(decbin($i), 4, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = ['type' => 'player', 'id' => $i + 1];
        }
        return (new FlexibleMonradCompiler)->compile(['size' => 16, 'slots' => $slots,
            'mode' => 'feed_in_monrad', 'challenge_playoffs' => $enabled]);
    }

    private function completedBase(array $graph): array
    {
        $fixtures = [];
        $outcomes = [];
        foreach ($graph['nodes'] as $key => $node) {
            if (isset($node['challenge_positions'])) {
                $fixtures[$key] = ['players' => [null, null], 'winner' => null, 'played' => false];
                continue;
            }
            $players = array_map(fn ($source) => $source['type'] === 'player' ? $source['id'] : $outcomes[$source['match']][$source['type']], $node['sources']);
            $outcomes[$key] = ['winner' => min($players), 'loser' => max($players)];
            $fixtures[$key] = ['players' => $players, 'winner' => min($players), 'played' => true];
        }
        return $fixtures;
    }

    public function test_optional_fixed_base_pairs_preserve_base_graph_and_depend_on_every_base_match(): void
    {
        $off = $this->compile(false);
        $on = $this->compile();
        $this->assertCount(36, $off['nodes']);
        $this->assertCount(43, $on['nodes']);
        $this->assertSame($off['positions'], $on['base_positions']);
        foreach (range(2, 14, 2) as $upper) {
            $node = $on['nodes']['challenge_'.$upper];
            $this->assertSame([$upper, $upper + 1], $node['challenge_positions']);
            $this->assertEqualsCanonicalizing(array_keys($off['nodes']), FlexibleMonradCompiler::dependencies($node));
            $this->assertSame($off['positions'][$upper], array_diff_key($node['sources'][0], ['base_position' => true]));
        }
    }

    public function test_explicit_pair_selection_is_distinct_from_legacy_all_and_ignores_ranks_outside_field(): void
    {
        $legacy = $this->compile();
        $this->assertSame(range(2, 14, 2), $legacy['challenge_pairs']);
        // Recompile its immutable starting players with a selected subset.
        $slots = [];
        for ($i = 0; $i < 16; $i++) $slots[strtr(str_pad(decbin($i), 4, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b'])] = ['type' => 'player', 'id' => $i + 1];
        $draft = ['size' => 16, 'slots' => $slots, 'mode' => 'feed_in_monrad', 'challenge_playoffs' => true];
        $selected = (new FlexibleMonradCompiler)->compile($draft + ['challenge_pairs' => [2, 6, 60]]);
        $this->assertSame([2, 6], $selected['challenge_pairs']);
        $this->assertCount(38, $selected['nodes']);
        $none = (new FlexibleMonradCompiler)->compile($draft + ['challenge_pairs' => []]);
        $this->assertCount(36, $none['nodes']);
        $this->assertSame([], $none['challenge_pairs']);
    }

    public function test_all_base_results_gate_challenges_and_upsets_only_swap_their_fixed_pair(): void
    {
        $graph = $this->compile();
        $fixtures = $this->completedBase($graph);
        $resolver = new FlexibleMonradProgression;
        $missing = array_key_first($fixtures);
        $fixtures[$missing]['played'] = false;
        $fixtures[$missing]['winner'] = null;
        $pending = $resolver->resolve($graph, $fixtures, []);
        foreach ($pending['matches'] as $key => $match) {
            if (str_starts_with($key, 'challenge_')) {
                $this->assertSame('pending', $match['challenge_status']);
                $this->assertSame([null, null], $match['players']);
            }
        }
        $fixtures = $this->completedBase($graph);
        $before = $resolver->resolve($graph, $fixtures, []);
        $base = array_column($before['base_positions'], 'player', 'position');
        $expected = $base;
        $ready = 0;
        foreach ($before['matches'] as $key => $match) {
            if (($match['challenge_status'] ?? null) !== 'ready') continue;
            $ready++;
            [$upper, $lower] = $graph['nodes'][$key]['challenge_positions'];
            $this->assertSame([$base[$upper], $base[$lower]], $match['players']);
            $fixtures[$key] = ['players' => $match['players'], 'winner' => $match['players'][1], 'played' => true];
            [$expected[$upper], $expected[$lower]] = [$base[$lower], $base[$upper]];
        }
        $this->assertGreaterThan(0, $ready);
        $after = $resolver->resolve($graph, $fixtures, []);
        $this->assertSame($expected, array_column($after['positions'], 'player', 'position'));
        $this->assertSame($base, array_column($after['base_positions'], 'player', 'position'));
        $this->assertTrue($after['positions_complete']);
        $this->assertCount(16, array_unique(array_column($after['positions'], 'player')));
        // A later withdrawal leaves the played upset's other player in its
        // earned position; it does not promote them back to their base rank.
        foreach ($before['matches'] as $key => $match) {
            if (($match['challenge_status'] ?? null) !== 'ready') continue;
            [$upper, $lower] = $graph['nodes'][$key]['challenge_positions'];
            foreach ($match['players'] as $withdrawn) {
                $withdrawal = $resolver->resolve($graph, $fixtures, [$withdrawn]);
                $this->assertSame('completed', $withdrawal['matches'][$key]['challenge_status']);
                $this->assertNull($withdrawal['matches'][$key]['skip_reason']);
                $positions = array_column($withdrawal['positions'], 'player', 'position');
                $this->assertSame($expected[$upper] === $withdrawn ? null : $expected[$upper], $positions[$upper]);
                $this->assertSame($expected[$lower] === $withdrawn ? null : $expected[$lower], $positions[$lower]);
            }
            break;
        }
    }

    public function test_prior_main_and_plate_meetings_in_either_order_skip_without_reordering(): void
    {
        foreach ([false, true] as $reverse) {
            $graph = ['nodes' => [], 'positions' => [], 'players' => range(1, 6)];
            foreach (range(1, 6) as $rank) $graph['positions'][$rank] = ['type' => 'player', 'id' => $rank];
            $fixtures = [];
            foreach (['main' => [2, 3], 'plate' => [4, 5]] as $key => $pair) {
                $graph['nodes'][$key] = ['sources' => array_map(fn ($id) => ['type' => 'player', 'id' => $id], $pair)];
                $fixtures[$key] = ['players' => $reverse ? array_reverse($pair) : $pair, 'winner' => $pair[0], 'played' => true];
            }
            foreach ([2, 4] as $upper) {
                $key = 'challenge_'.$upper;
                $graph['nodes'][$key] = ['sources' => [$graph['positions'][$upper], $graph['positions'][$upper + 1]],
                    'challenge_positions' => [$upper, $upper + 1], 'prerequisites' => ['main', 'plate']];
                $fixtures[$key] = ['players' => [null, null], 'winner' => null, 'played' => false];
            }
            $state = (new FlexibleMonradProgression)->resolve($graph, $fixtures, []);
            foreach ([2, 4] as $upper) {
                $this->assertSame('skipped', $state['matches']['challenge_'.$upper]['challenge_status']);
                $this->assertNull($state['matches']['challenge_'.$upper]['winner']);
            }
            $this->assertSame(range(1, 6), array_column($state['positions'], 'player'));
        }
    }
}
