<?php

namespace Tests\Unit;

use App\Services\Draw\FlexibleMonradCompiler;
use App\Services\Draw\FlexibleMonradProgression;
use PHPUnit\Framework\TestCase;

/** Behavioral examples for docs/draw-knowledge; no database or real entrant data. */
class DrawKnowledgeRegressionTest extends TestCase
{
    private function draft(int $size, array $players, string $mode): array
    {
        $slots = [];
        for ($index = 0; $index < $size; $index++) {
            $path = strtr(str_pad(decbin($index), (int) log($size, 2), '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = isset($players[$index]) ? ['type' => 'player', 'id' => $players[$index]] : ['type' => 'bye'];
        }

        return ['size' => $size, 'slots' => $slots, 'mode' => $mode];
    }

    private function emptyFixtures(array $graph): array
    {
        return array_map(fn () => ['players' => [null, null], 'winner' => null, 'played' => false], $graph['nodes']);
    }

    public function test_reusing_a_compiler_for_another_field_does_not_leak_entrants_or_placement_matches(): void
    {
        $compiler = new FlexibleMonradCompiler;
        $compiler->compile($this->draft(16, range(101, 116), 'feed_in_monrad') + ['challenge_playoffs' => true]);
        $draft = $this->draft(8, [501, 907, 1301, 2011, 4099], 'custom_monrad');
        $actual = $compiler->compile($draft);

        $this->assertSame((new FlexibleMonradCompiler)->compile($draft), $actual);
        $this->assertSame([501, 907, 1301, 2011, 4099], $actual['players']);
        $this->assertSame(range(1, 5), array_keys($actual['positions']));
        $this->assertArrayNotHasKey('challenge_2', $actual['nodes']);
        $this->assertArrayNotHasKey('base_positions', $actual);
    }

    public function test_json_reordered_graph_keeps_a_pending_qualifier_distinct_from_a_withdrawal_walkover(): void
    {
        $graph = (new FlexibleMonradCompiler)->compile($this->draft(4, [101, 307, 509, 701], 'custom_monrad'));
        $fixtures = $this->emptyFixtures($graph);
        $fixtures['main_a'] = ['players' => [101, 307], 'winner' => 101, 'played' => true];
        $resolver = new FlexibleMonradProgression;
        $before = $resolver->resolve($graph, $fixtures, [101]);
        $serialized = json_decode(json_encode($graph, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        ksort($serialized['nodes']);
        $actual = $resolver->resolve($serialized, $fixtures, [101]);

        $this->assertSame($before, $actual);
        $this->assertSame([101, 307], $actual['matches']['main_a']['players']);
        $this->assertSame(101, $actual['matches']['main_a']['winner']);
        $this->assertFalse($actual['matches']['main_final']['resolved']);
        $this->assertNull($actual['matches']['main_final']['automatic']);
        $this->assertSame([true, false], $actual['matches']['main_final']['vacant']);
        $this->assertSame([101, null], $actual['matches']['main_final']['withdrawn_players']);

        $fixtures['main_b'] = ['players' => [509, 701], 'winner' => 701, 'played' => true];
        $completed = $resolver->resolve($serialized, $fixtures, [101]);
        $this->assertSame('walkover', $completed['matches']['main_final']['automatic']);
        $this->assertSame(701, $completed['matches']['main_final']['winner']);
        $this->assertSame($completed, $resolver->resolve($serialized, $fixtures, [101]));
    }

    public function test_a_prior_walkover_does_not_suppress_a_challenge_as_a_played_rematch(): void
    {
        $graph = ['players' => [101, 307, 509], 'positions' => [], 'nodes' => []];
        foreach ([101, 307, 509] as $index => $player) {
            $graph['positions'][$index + 1] = ['type' => 'player', 'id' => $player];
        }
        $graph['nodes']['earlier'] = ['sources' => [$graph['positions'][2], $graph['positions'][3]]];
        $graph['nodes']['challenge_2'] = [
            'sources' => [$graph['positions'][2], $graph['positions'][3]],
            'challenge_positions' => [2, 3], 'prerequisites' => ['earlier'],
        ];
        $fixtures = $this->emptyFixtures($graph);
        // Reverse the recorded sides: pair identity must ignore side order.
        $fixtures['earlier'] = ['players' => [509, 307], 'winner' => 307, 'played' => true, 'walkover' => true];
        $resolver = new FlexibleMonradProgression;
        $walkover = $resolver->resolve($graph, $fixtures, []);
        $this->assertSame('ready', $walkover['matches']['challenge_2']['challenge_status']);
        $this->assertSame([307, 509], $walkover['matches']['challenge_2']['players']);

        $fixtures['earlier']['walkover'] = false;
        $played = $resolver->resolve($graph, $fixtures, []);
        $this->assertSame('skipped', $played['matches']['challenge_2']['challenge_status']);
        $this->assertStringContainsString('already played', $played['matches']['challenge_2']['skip_reason']);
        $this->assertSame($walkover['positions'], $played['positions']);
        $this->assertNull($played['matches']['challenge_2']['winner']);
    }
}
