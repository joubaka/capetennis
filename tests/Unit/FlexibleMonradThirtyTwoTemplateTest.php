<?php

namespace Tests\Unit;

use App\Services\Draw\FlexibleMonradCompiler;
use PHPUnit\Framework\TestCase;

class FlexibleMonradThirtyTwoTemplateTest extends TestCase
{
    private function draft(array $occupied): array
    {
        $slots = [];
        foreach (range(0, 31) as $index) {
            $path = strtr(str_pad(decbin($index), 5, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = in_array($index, $occupied, true)
                ? ['type' => 'player', 'id' => 101 + $index * 7] : ['type' => 'bye'];
        }
        return ['size' => 32, 'mode' => 'feed_in_monrad', 'slots' => $slots];
    }

    public function test_sparse_template_keeps_byes_at_their_branches_and_separates_direct_advances(): void
    {
        // Fictional 22-player field: opening matches A2,A6,A7,A10,A11,A15.
        $draft = $this->draft([0, 2, 3, 4, 7, 8, 10, 11, 12, 13, 15, 16, 18, 19, 20, 21, 23, 24, 27, 28, 29, 31]);
        $graph = (new FlexibleMonradCompiler)->compile($draft);
        $this->assertCount(55, $graph['nodes']);
        $numbers = array_flip(array_keys($graph['nodes']));
        $feed = array_values(array_filter($graph['nodes'], fn ($node) => str_contains($node['section'], 'Feed-in')));
        $actual = array_map(fn ($node) => array_map(fn ($source) =>
            ($source['type'] === 'loser' ? 'L' : 'W').($numbers[$source['match']] + 1), $node['sources']), $feed);
        $this->assertSame([
            ['L18', 'L1'], ['L14', 'L5'], ['L12', 'L7'],
            ['L8', 'L11'], ['L6', 'L13'], ['L2', 'L17'],
            ['W23', 'L16'], ['W24', 'W25'], ['W26', 'W27'], ['L3', 'W28'],
            ['L4', 'W29'], ['L9', 'W30'], ['L15', 'W31'], ['L19', 'W32'],
            ['W33', 'W34'], ['W35', 'W36'], ['W37', 'W38'],
        ], $actual);
        $this->assertSame([1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 5], array_column($feed, 'round'));
        // Slot input order cannot determine branch order or move a bye.
        $draft['slots'] = array_reverse($draft['slots'], true);
        $reordered = (new FlexibleMonradCompiler)->compile($draft);
        $this->assertSame($graph['nodes'], $reordered['nodes']);
        $this->assertSame($graph['positions'], $reordered['positions']);
    }

    public function test_every_field_size_in_a_fixed_thirty_two_template_ranks_all_players_once(): void
    {
        foreach (range(2, 32) as $count) {
            foreach ([false, true] as $spread) {
                $occupied = array_values(array_filter(range(0, 31), fn ($index) =>
                    $spread ? ((($index + 1) * $count) % 32 < $count) : $index < $count));
                $draft = $this->draft($occupied);
                $graph = (new FlexibleMonradCompiler)->compile($draft);
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
