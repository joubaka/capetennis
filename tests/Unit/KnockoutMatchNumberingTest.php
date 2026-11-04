<?php

namespace Tests\Unit;

use App\Services\Draw\FlexibleMonradCompiler;
use PHPUnit\Framework\TestCase;

class KnockoutMatchNumberingTest extends TestCase
{
    private function graph(string $mode, int $count = 16): array
    {
        $slots = [];
        for ($i = 0; $i < 16; $i++) {
            $path = strtr(str_pad(decbin($i), 4, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = $i < $count ? ['type' => 'player', 'id' => $i + 1] : ['type' => 'bye'];
        }
        return (new FlexibleMonradCompiler)->compile(['mode' => $mode, 'size' => 16, 'slots' => $slots]);
    }

    public function test_full_knockout_numbers_each_round_top_to_bottom(): void
    {
        $graph = $this->graph('playoffs');
        $this->assertSame(['main_aaa', 'main_aab', 'main_aba', 'main_abb',
            'main_baa', 'main_bab', 'main_bba', 'main_bbb',
            'main_aa', 'main_ab', 'main_ba', 'main_bb', 'main_a', 'main_b', 'main_final'],
            FlexibleMonradCompiler::fixtureOrder($graph));
    }

    public function test_byes_leave_no_number_gaps_and_do_not_promote_later_rounds(): void
    {
        $graph = $this->graph('playoffs', 13);
        $order = FlexibleMonradCompiler::fixtureOrder($graph);
        $this->assertCount(12, $order);
        $rounds = array_map(fn ($key) => $graph['nodes'][$key]['round'], $order);
        $sorted = $rounds;
        sort($sorted);
        $this->assertSame($sorted, $rounds);
    }

    public function test_all_placement_formats_put_feeders_before_matches_and_preserve_graph(): void
    {
        foreach (['monrad', 'custom_monrad', 'feed_in_monrad'] as $mode) {
            foreach ([16, 13, 5, 2] as $count) {
                $graph = $this->graph($mode, $count);
                $before = $graph;
                $order = FlexibleMonradCompiler::fixtureOrder($graph);
                $numbers = array_flip($order);
                $this->assertCount(count($graph['nodes']), $order);
                $this->assertSame($before, $graph);
                foreach ($graph['nodes'] as $key => $node) {
                    foreach (FlexibleMonradCompiler::dependencies($node) as $dependency) {
                        $this->assertLessThan($numbers[$key], $numbers[$dependency], "$mode: $dependency feeds $key");
                    }
                }
                $opening = array_keys(array_filter($graph['nodes'], fn ($node) =>
                    $node['section'] === 'Main draw' && $node['round'] === 1));
                $this->assertSame($opening, array_slice($order, 0, count($opening)));
            }
        }
    }

    public function test_feed_in_sixteen_interleaves_main_and_placement_rounds(): void
    {
        $graph = $this->graph('feed_in_monrad');
        $order = FlexibleMonradCompiler::fixtureOrder($graph);
        $this->assertCount(36, $order);
        $sections = array_map(fn ($key) => $graph['nodes'][$key]['section'], $order);
        $this->assertSame(array_fill(0, 12, 'Main draw'), array_slice($sections, 0, 12));
        $this->assertSame(array_fill(0, 4, 'Positions 5–16 · Feed-in'), array_slice($sections, 12, 4));
        $this->assertSame(['Main draw', 'Main draw'], array_slice($sections, 16, 2));
        $this->assertSame('main_final', $order[24]);
    }

    public function test_direct_entrants_and_challenges_keep_every_prerequisite_before_its_match(): void
    {
        $graph = (new FlexibleMonradCompiler)->compile(FlexibleMonradCompilerTest::mixedDraft());
        $numbers = array_flip(FlexibleMonradCompiler::fixtureOrder($graph));
        foreach ($graph['nodes'] as $key => $node) {
            foreach (FlexibleMonradCompiler::dependencies($node) as $dependency) {
                $this->assertLessThan($numbers[$key], $numbers[$dependency]);
            }
        }
        $graph = FlexibleMonradCompiler::withRollingChallenges($this->graph('feed_in_monrad'));
        $numbers = array_flip(FlexibleMonradCompiler::fixtureOrder($graph));
        foreach ($graph['nodes'] as $key => $node) {
            foreach (FlexibleMonradCompiler::dependencies($node) as $dependency) {
                $this->assertLessThan($numbers[$key], $numbers[$dependency]);
            }
        }
    }
}
