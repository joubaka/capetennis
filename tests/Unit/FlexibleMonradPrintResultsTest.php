<?php

namespace Tests\Unit;

use App\Services\Draw\FlexibleMonradPdfLayout;
use PHPUnit\Framework\TestCase;

class FlexibleMonradPrintResultsTest extends TestCase
{
    private function state(array $changes = []): array
    {
        return ['players' => [['id' => 1, 'name' => 'Alex Example'], ['id' => 2, 'name' => 'Sam Example']],
            'matches' => ['final' => array_replace([
                'section' => 'Main draw', 'round' => 2, 'label' => 'Final', 'number' => 7,
                'sources' => [['type' => 'winner', 'match' => 'qualifier'], ['type' => 'player']],
                'players' => [null, 2], 'sets' => [], 'winner' => null,
            ], $changes), 'qualifier' => [
                'section' => 'Main draw', 'round' => 1, 'label' => 'Semifinal', 'number' => 3,
                'sources' => [['type' => 'player'], ['type' => 'player']],
                'players' => [1, 2], 'sets' => [], 'winner' => null,
            ]], 'positions' => [['position' => 1, 'player' => null], ['position' => 2, 'player' => null]]];
    }

    public function test_unplayed_print_results_are_empty_but_feeder_labels_are_preserved(): void
    {
        $layout = (new FlexibleMonradPdfLayout)->build($this->state());
        $this->assertSame(['', ''], $layout['boards'][0]['cards'][0]['scores']);
        $this->assertSame('', $layout['boards'][0]['endpoints'][0]['name']);
        $this->assertSame(['', ''], array_column($layout['positions'], 'name'));
        $this->assertSame('Winner of Match 3', $layout['boards'][0]['cards'][0]['participants'][0]['label']);
        $this->assertSame('Sam Example', $layout['boards'][0]['cards'][0]['participants'][1]['label']);
    }

    public function test_entered_results_and_automatic_outcomes_remain_printable(): void
    {
        $state = $this->state(['players' => [1, 2], 'winner' => 1, 'sets' => [[6, 4], [6, 2]]]);
        $state['positions'] = [['position' => 1, 'player' => 1], ['position' => 2, 'player' => 2]];
        $layout = (new FlexibleMonradPdfLayout)->build($state);
        $this->assertSame(['6 6', '4 2'], $layout['boards'][0]['cards'][0]['scores']);
        $this->assertSame('Alex Example', $layout['boards'][0]['endpoints'][0]['name']);
        $this->assertSame(['Alex Example', 'Sam Example'], array_column($layout['positions'], 'name'));
        $walkover = (new FlexibleMonradPdfLayout)->build($this->state(['automatic' => 'walkover', 'winner' => 2]));
        $this->assertSame('Walkover', $walkover['boards'][0]['cards'][0]['note']);
        $this->assertSame('Sam Example', $walkover['boards'][0]['endpoints'][0]['name']);
        $skipped = (new FlexibleMonradPdfLayout)->build($this->state(['challenge_status' => 'skipped', 'skip_reason' => 'already played']));
        $this->assertSame('Skipped - positions unchanged', $skipped['boards'][0]['endpoints'][0]['name']);
        $this->assertSame('Skipped - already played', $skipped['boards'][0]['cards'][0]['note']);
    }

    public function test_pending_challenge_result_note_is_blank_without_hiding_byes_or_withdrawal_outcomes(): void
    {
        $state = $this->state(['challenge_status' => 'pending']);
        $state['positions'] = [['position' => 1, 'bye' => true], ['position' => 2, 'vacant' => true]];
        $layout = (new FlexibleMonradPdfLayout)->build($state);
        $this->assertNull($layout['boards'][0]['cards'][0]['note']);
        $this->assertSame(['Bye - no position awarded', 'Unassigned after withdrawal'], array_column($layout['positions'], 'name'));
    }

    public function test_disabled_challenge_branches_are_absent_but_selected_pending_and_skipped_matches_remain(): void
    {
        $state = $this->state();
        foreach (['challenge_2' => [2, 3], 'challenge_4' => [4, 5], 'challenge_6' => [6, 7], 'challenge_8' => [8, 9],
            'challenge_3' => [3, 4], 'challenge_2_repeat' => [2, 3]] as $key => $pair) {
            $state['matches'][$key] = ['section' => 'Challenge play-offs', 'round' => $key === 'challenge_3' ? 2 : ($key === 'challenge_2_repeat' ? 3 : 1),
                'label' => implode('–', $pair), 'number' => count($state['matches']) + 1, 'challenge_positions' => $pair,
                'sources' => array_map(fn ($rank) => ['type' => 'player', 'base_position' => $rank], $pair),
                'players' => [null, null], 'sets' => [], 'winner' => null,
                'challenge_status' => in_array($key, ['challenge_6', 'challenge_8'], true) ? 'disabled' : ($key === 'challenge_4' ? 'skipped' : 'pending')];
        }
        $before = $state;
        $layout = (new FlexibleMonradPdfLayout)->build($state);
        $challenge = collect($layout['boards'])->firstWhere('section', 'Challenge play-offs');
        $this->assertSame(['challenge_2', 'challenge_4', 'challenge_3', 'challenge_2_repeat'], array_column($challenge['cards'], 'key'));
        $this->assertCount(4, $challenge['endpoints']);
        $this->assertSame($before, $state);
        foreach ($state['matches'] as &$match) if (isset($match['challenge_positions'])) $match['challenge_status'] = 'disabled';
        unset($match);
        $off = (new FlexibleMonradPdfLayout)->build($state);
        $this->assertNotContains('Challenge play-offs', array_column($off['boards'], 'section'));
        $this->assertSame($layout['positions'], $off['positions']);
    }
}
