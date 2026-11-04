<?php

namespace Tests\Unit;

use App\Services\Draw\{FlexibleMonradCompiler, FlexibleMonradFeedInDisplay, FlexibleMonradPdfLayout};
use PHPUnit\Framework\TestCase;

class FlexibleMonradFeedInDisplayTest extends TestCase
{
    private function state(int $size = 16, array $byes = [0]): array
    {
        $slots = [];
        for ($i = 0; $i < $size; $i++) {
            $path = strtr(str_pad(decbin($i), (int) log($size, 2), '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = in_array($i, $byes, true) ? ['type' => 'bye'] : ['type' => 'player', 'id' => $i + 1];
        }
        $draft = ['size' => $size, 'mode' => 'feed_in_monrad', 'slots' => $slots];
        $graph = (new FlexibleMonradCompiler)->compile($draft);
        $numbers = array_flip(FlexibleMonradCompiler::fixtureOrder($graph));
        $matches = [];
        foreach ($graph['nodes'] as $key => $node) $matches[$key] = $node + ['number' => $numbers[$key] + 1, 'players' => [null, null], 'winner' => null, 'sets' => []];
        return ['draft' => $draft, 'matches' => $matches, 'players' => [], 'positions' => []];
    }

    public function test_pending_feed_in_bye_is_visible_connected_and_unnumbered_without_mutating_state(): void
    {
        foreach ([16, 32] as $size) {
            $state = $this->state($size);
            $before = $state;
            $display = FlexibleMonradFeedInDisplay::expand($state['matches'], $state['draft']);
            $bye = $display['display_feed_bye_1_0'];
            $this->assertSame([true, false], $bye['byes']);
            $this->assertSame([null, null], $bye['players']);
            $this->assertArrayNotHasKey('number', $bye);
            $this->assertArrayNotHasKey('id', $bye);
            $connected = array_filter($display, fn ($match) => in_array('display_feed_bye_1_0', array_column($match['sources'], 'match'), true));
            $this->assertCount(1, $connected);
            $layout = (new FlexibleMonradPdfLayout)->build($state);
            $feed = collect($layout['boards'])->first(fn ($board) => str_ends_with($board['section'], ' · Feed-in'));
            $card = collect($feed['cards'])->firstWhere('key', 'display_feed_bye_1_0');
            $this->assertCount(1, $feed['endpoints'], 'Only the actual feed-in final has a finishing-position endpoint.');
            $this->assertNull($card['number']);
            $this->assertSame('Bye - no match played', $card['note']);
            $this->assertSame('Bye', $card['participants'][0]['label']);
            $this->assertStringStartsWith('Loser of Match ', $card['participants'][1]['label']);
            foreach ($state['matches'] as $key => $match) $this->assertSame($match['number'], $display[$key]['number']);
            $this->assertSame($before, $state);
        }
    }

    public function test_resolved_loser_appears_in_bye_card_without_inventing_a_match(): void
    {
        $state = $this->state();
        $state['matches']['main_aab']['players'] = [3, 4];
        $state['matches']['main_aab']['winner'] = 3;
        $display = FlexibleMonradFeedInDisplay::expand($state['matches'], $state['draft']);
        $this->assertSame([null, 4], $display['display_feed_bye_1_0']['players']);
        $this->assertSame(4, $display['display_feed_bye_1_0']['winner']);
    }

    public function test_sparse_paths_and_incompatible_history_are_safe(): void
    {
        $state = $this->state(16, [0, 1, 2, 3, 8, 9]);
        $display = FlexibleMonradFeedInDisplay::expand($state['matches'], $state['draft']);
        $this->assertSame([true, true], $display['display_feed_bye_1_0']['byes']);
        $feedKey = array_key_first(array_filter($state['matches'], fn ($match) => str_ends_with($match['section'], ' · Feed-in')));
        $state['matches'][$feedKey]['sources'] = [['type' => 'player', 'id' => 99], ['type' => 'player', 'id' => 100]];
        $this->assertSame($state['matches'], FlexibleMonradFeedInDisplay::expand($state['matches'], $state['draft']));
    }
}
