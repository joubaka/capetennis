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

    private function feedInDraft(int $count, bool $spreadByes = false): array
    {
        $size = max(4, 2 ** (int) ceil(log($count, 2)));
        $slots = [];
        $id = 1;
        for ($i = 0; $i < $size; $i++) {
            $path = strtr(str_pad(decbin($i), (int) log($size, 2), '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $occupied = $spreadByes ? ((($i + 1) * $count) % $size < $count) : $i < $count;
            $slots[$path] = $occupied ? ['type' => 'player', 'id' => $id++] : ['type' => 'bye'];
        }
        return ['size' => $size, 'mode' => 'feed_in_monrad', 'slots' => $slots];
    }

    public function test_feed_in_sixteen_matches_the_interpro_template_crossovers_and_placement_bands(): void
    {
        $graph = (new FlexibleMonradCompiler)->compile($this->feedInDraft(16));
        $this->assertCount(36, $graph['nodes']);
        $feed = array_filter($graph['nodes'], fn ($node) => str_contains($node['section'], 'Feed-in'));
        $keys = array_keys($feed);
        $feed = array_values($feed);
        // Template A1–A8 are adjacent opening matches. B1–B4 take their losers.
        foreach (['aaa', 'aba', 'baa', 'bba'] as $index => $path) {
            $other = substr($path, 0, -1).'b';
            $this->assertSame([['type' => 'loser', 'match' => 'main_'.$path],
                ['type' => 'loser', 'match' => 'main_'.$other]], $feed[$index]['sources']);
        }
        // B5..B8 top=A12,A11,A10,A9 losers, bottom=B1..B4 winners.
        foreach (['bb', 'ba', 'ab', 'aa'] as $index => $path) {
            $this->assertSame([['type' => 'loser', 'match' => 'main_'.$path],
                ['type' => 'winner', 'match' => $keys[$index]]], $feed[$index + 4]['sources']);
        }
        $this->assertSame([['type' => 'winner', 'match' => $keys[4]], ['type' => 'winner', 'match' => $keys[5]]], $feed[8]['sources']);
        $this->assertSame([['type' => 'winner', 'match' => $keys[6]], ['type' => 'winner', 'match' => $keys[7]]], $feed[9]['sources']);
        $this->assertSame([['type' => 'winner', 'match' => $keys[8]], ['type' => 'winner', 'match' => $keys[9]]], $feed[10]['sources']);
        $sections = array_count_values(array_column($graph['nodes'], 'section'));
        $this->assertSame(15, $sections['Main draw']);
        $this->assertSame(1, $sections['Positions 3–4']);
        $this->assertSame(11, $sections['Positions 5–16 · Feed-in']);
        $this->assertSame(1, $sections['Positions 7–8']);
        $this->assertSame(3, $sections['Positions 9–12']);
        $this->assertSame(1, $sections['Positions 11–12']);
        $this->assertSame(3, $sections['Positions 13–16']);
        $this->assertSame(1, $sections['Positions 15–16']);
        $this->assertSame(range(1, 16), array_keys($graph['positions']));
    }

    public function test_feed_in_thirty_two_preserves_historical_alternating_crossovers(): void
    {
        $graph = (new FlexibleMonradCompiler)->compile($this->feedInDraft(32));
        $this->assertCount(92, $graph['nodes']);
        $feed = array_values(array_filter($graph['nodes'], fn ($node) => str_contains($node['section'], 'Feed-in')));
        foreach (['bbb', 'bba', 'bab', 'baa', 'abb', 'aba', 'aab', 'aaa'] as $index => $path) {
            $this->assertSame(['type' => 'loser', 'match' => 'main_'.$path], $feed[8 + $index]['sources'][0]);
        }
        foreach (['aa', 'ab', 'ba', 'bb'] as $index => $path) {
            $this->assertSame(['type' => 'loser', 'match' => 'main_'.$path], $feed[20 + $index]['sources'][0]);
        }
    }

    public function test_feed_in_every_field_size_with_byes_is_acyclic_and_ranks_every_player_once(): void
    {
        foreach (range(2, 64) as $count) {
            foreach ([false, true] as $spreadByes) {
                $graph = (new FlexibleMonradCompiler)->compile($this->feedInDraft($count, $spreadByes));
                foreach ([0, 1, 2] as $scenario) {
                    $outcomes = [];
                    foreach ($graph['nodes'] as $key => $node) {
                        $players = array_map(fn ($source) => $source['type'] === 'player' ? $source['id'] : $outcomes[$source['match']][$source['type']], $node['sources']);
                        $this->assertNotSame($players[0], $players[1]);
                        $flip = (count($outcomes) + $scenario) % 2;
                        $outcomes[$key] = ['winner' => $players[$flip], 'loser' => $players[1 - $flip]];
                    }
                    $positions = array_map(fn ($source) => $source['type'] === 'player' ? $source['id'] : $outcomes[$source['match']][$source['type']], $graph['positions']);
                    $this->assertSame(range(1, $count), array_keys($positions));
                    $this->assertEqualsCanonicalizing(range(1, $count), array_values($positions));
                }
            }
        }
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
