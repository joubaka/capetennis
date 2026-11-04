<?php

namespace Tests\Unit;

use App\Services\Draw\FlexibleMonradCompiler;
use PHPUnit\Framework\TestCase;

class FlexibleMonradSixteenTemplateTest extends TestCase
{
    private function draft(array $occupied): array
    {
        $slots = [];
        foreach (range(0, 15) as $index) {
            $path = strtr(str_pad(decbin($index), 4, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = in_array($index, $occupied, true)
                ? ['type' => 'player', 'id' => 107 + $index * 11] : ['type' => 'bye'];
        }
        return ['size' => 16, 'mode' => 'feed_in_monrad', 'slots' => $slots];
    }

    public function test_sparse_sixteen_keeps_direct_advances_in_their_own_template_branches(): void
    {
        // Ten fictional entrants: only A2 and A7 are played opening matches.
        $draft = $this->draft([0, 2, 3, 4, 7, 8, 11, 12, 13, 15]);
        $graph = (new FlexibleMonradCompiler)->compile($draft);
        $this->assertCount(17, $graph['nodes']);
        $feed = array_filter($graph['nodes'], fn ($node) => str_contains($node['section'], 'Feed-in'));
        $keys = array_keys($feed);
        $feed = array_values($feed);
        $loser = fn ($key) => ['type' => 'loser', 'match' => $key];
        $winner = fn ($key) => ['type' => 'winner', 'match' => $key];
        $this->assertSame([
            [$loser('main_bb'), $loser('main_aab')],
            [$loser('main_aa'), $loser('main_bba')],
            [$winner($keys[0]), $loser('main_ba')],
            [$loser('main_ab'), $winner($keys[1])],
            [$winner($keys[2]), $winner($keys[3])],
        ], array_column($feed, 'sources'));
        $this->assertSame([1, 1, 2, 2, 3], array_column($feed, 'round'));
        $this->assertSame([
            $loser('main_a'), $loser('main_b'),
        ], $graph['nodes']['place_9']['sources']);
        $draft['slots'] = array_reverse($draft['slots'], true);
        $reordered = (new FlexibleMonradCompiler)->compile($draft);
        $this->assertSame($graph['nodes'], $reordered['nodes']);
        $this->assertSame($graph['positions'], $reordered['positions']);
    }

    public function test_fixed_sixteen_with_byes_ranks_every_entrant_once_for_all_field_sizes(): void
    {
        foreach (range(2, 16) as $count) {
            foreach ([1, 3, 5, 7] as $stride) {
                $occupied = array_map(fn ($index) => ($index * $stride + 3) % 16, range(0, $count - 1));
                $graph = (new FlexibleMonradCompiler)->compile($this->draft($occupied));
                foreach (range(0, 3) as $scenario) {
                    $outcomes = [];
                    foreach ($graph['nodes'] as $key => $node) {
                        $players = array_map(fn ($source) => $source['type'] === 'player' ? $source['id']
                            : $outcomes[$source['match']][$source['type']], $node['sources']);
                        $this->assertNotSame($players[0], $players[1]);
                        $flip = (count($outcomes) + $scenario) % 2;
                        $outcomes[$key] = ['winner' => $players[$flip], 'loser' => $players[1 - $flip]];
                    }
                    $positions = array_map(fn ($source) => $source['type'] === 'player' ? $source['id']
                        : $outcomes[$source['match']][$source['type']], $graph['positions']);
                    $this->assertSame(range(1, $count), array_keys($positions));
                    $this->assertEqualsCanonicalizing($graph['players'], array_values($positions));
                }
            }
        }
    }
}
