<?php

namespace Tests\Unit;

use App\Services\Draw\FlexibleMonradCompiler;
use PHPUnit\Framework\TestCase;

class FlexibleMonradCompilerTest extends TestCase
{
    public static function mixedDraft(): array
    {
        $slots = ['aaa' => ['type' => 'player', 'id' => 1], 'bbb' => ['type' => 'player', 'id' => 2]];
        $id = 3;
        foreach (['aaba', 'abba', 'baaa', 'baba'] as $path) $slots[$path] = ['type' => 'player', 'id' => $id++];
        $fill = function ($path) use (&$fill, &$slots, &$id) {
            if (isset($slots[$path])) return;
            if (strlen($path) === 5) { $slots[$path] = ['type' => 'player', 'id' => $id++]; return; }
            $fill($path.'a'); $fill($path.'b');
        };
        $fill('a'); $fill('b');
        return ['size' => 32, 'slots' => $slots];
    }

    public function test_mixed_entry_rounds_produce_exact_positions_and_no_duplicate_players_in_matches(): void
    {
        $graph = (new FlexibleMonradCompiler)->compile(self::mixedDraft());
        $main = array_filter($graph['nodes'], fn ($n) => $n['section'] === 'Main draw');
        $this->assertCount(21, $main);
        $this->assertCount(6, array_filter($main, fn ($n) => $n['label'] === 'Round of 16'));
        $this->assertCount(4, array_filter($main, fn ($n) => $n['label'] === 'Quarterfinals'));
        $this->assertSame(range(1, 22), array_keys($graph['positions']));
        foreach (range(0, 7) as $scenario) {
            $outcomes = [];
            foreach ($graph['nodes'] as $key => $node) {
                $players = array_map(function ($source) use ($outcomes) {
                    return $source['type'] === 'player' ? $source['id'] : $outcomes[$source['match']][$source['type']];
                }, $node['sources']);
                $this->assertNotSame($players[0], $players[1]);
                $flip = (count($outcomes) + $scenario) % 2;
                $outcomes[$key] = ['winner' => $players[$flip], 'loser' => $players[1 - $flip]];
            }
            $positions = array_map(fn ($s) => $s['type'] === 'player' ? $s['id'] : $outcomes[$s['match']][$s['type']], $graph['positions']);
            $this->assertCount(22, array_unique($positions));
            $this->assertLessThanOrEqual(8, array_search(1, $positions));
            $this->assertLessThanOrEqual(8, array_search(2, $positions));
        }
    }

    public function test_every_field_size_two_through_sixty_four_has_a_complete_acyclic_placement_graph(): void
    {
        foreach (range(2, 64) as $count) {
            $size = max(4, 2 ** (int) ceil(log($count, 2)));
            $slots = [];
            for ($i = 0; $i < $size; $i++) {
                $path = strtr(str_pad(decbin($i), (int) log($size, 2), '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
                $slots[$path] = $i < $count ? ['type' => 'player', 'id' => $i + 1] : ['type' => 'bye'];
            }
            $graph = (new FlexibleMonradCompiler)->compile(['size' => $size, 'slots' => $slots]);
            $outcomes = [];
            foreach ($graph['nodes'] as $key => $node) {
                $players = array_map(fn ($s) => $s['type'] === 'player' ? $s['id'] : $outcomes[$s['match']][$s['type']], $node['sources']);
                $this->assertNotSame($players[0], $players[1]);
                $outcomes[$key] = ['winner' => min($players), 'loser' => max($players)];
            }
            $positions = array_map(fn ($s) => $s['type'] === 'player' ? $s['id'] : $outcomes[$s['match']][$s['type']], $graph['positions']);
            $this->assertCount($count, array_unique($positions));
            $this->assertSame(range(1, $count), array_keys($positions));
        }
    }
}
