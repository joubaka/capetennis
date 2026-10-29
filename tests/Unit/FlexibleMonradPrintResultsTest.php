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
}
