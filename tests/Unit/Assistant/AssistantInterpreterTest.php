<?php

namespace Tests\Unit\Assistant;

use App\Services\Assistant\AssistantInterpreter;
use PHPUnit\Framework\TestCase;

class AssistantInterpreterTest extends TestCase
{
    public function test_unsupported_match_moves_and_exclusions_are_not_partially_interpreted(): void
    {
        foreach (['Keep court 2 free until 10', 'Move the final to Sunday', 'Schedule Saturday except court 2', 'Schedule only round 2', 'Schedule Saturday without court 2', 'Court 2 is unavailable', 'Use courts 1 to 3 and 5'] as $question) {
            $result = (new AssistantInterpreter)->interpret($question, $this->context(), ['draw_ids' => [7], 'date' => '2026-11-07']);
            $this->assertSame('help', $result['action']);
            $this->assertSame('unsupported_constraint', $result['reason_code']);
            $this->assertNotEmpty($result['clarification']);
            $this->assertArrayNotHasKey('options', $result);
        }
    }

    public function test_explicit_court_lists_are_preserved_instead_of_defaulting_to_all_courts(): void
    {
        foreach (['courts 1 and 3', 'courts 1, 3'] as $courts) {
            $result = (new AssistantInterpreter)->interpret('Schedule Under 14 Boys Saturday using '.$courts, $this->context());
            $this->assertSame(['1', '3'], $result['options']['courts']);
        }
    }

    private function context(): array
    {
        return ['dates' => ['2026-11-07', '2026-11-08'], 'draws' => [['id' => 7, 'name' => 'U/14 Boys Draw'], ['id' => 9, 'name' => 'U/16 Girls Draw']], 'venues' => [['id' => 3, 'name' => 'Main courts']]];
    }

    public function test_local_request_resolves_date_draw_courts_and_rest_without_misreading_court_range_as_end_time(): void
    {
        $result = (new AssistantInterpreter)->interpret('Schedule under-14 boys on Saturday from 8 am using courts 1 to 4 with an hour between matches', $this->context());
        $this->assertSame('schedule', $result['action']);
        $this->assertSame('2026-11-07', $result['options']['date']);
        $this->assertSame([7], $result['options']['draw_ids']);
        $this->assertSame('08:00', $result['options']['start_time']);
        $this->assertSame(['1', '2', '3', '4'], $result['options']['courts']);
        $this->assertSame(60, $result['options']['player_rest']);
        $this->assertArrayNotHasKey('end_time', $result['options']);
    }

    public function test_follow_up_keeps_prior_selection_and_changes_only_requested_time(): void
    {
        $prior = ['date' => '2026-11-07', 'draw_ids' => [7], 'start_time' => '08:00'];
        $result = (new AssistantInterpreter)->interpret('Start at 9 instead', $this->context(), $prior);
        $this->assertSame($prior + [], array_replace($result['options'], ['start_time' => '08:00']));
        $this->assertSame('09:00', $result['options']['start_time']);
    }

    public function test_unknown_draw_does_not_silently_reuse_prior_draw(): void
    {
        $result = (new AssistantInterpreter)->interpret('Schedule under 18 boys on Saturday', $this->context(), ['draw_ids' => [7]]);
        $this->assertNotEmpty($result['clarification']);
    }

    public function test_ambiguous_weekday_requires_exact_date(): void
    {
        $context = $this->context();
        $context['dates'][] = '2026-11-14';
        $result = (new AssistantInterpreter)->interpret('Schedule Saturday', $context);
        $this->assertNotEmpty($result['clarification']);
    }

    public function test_draw_draft_is_explicit_and_destructive_requests_are_not_actions(): void
    {
        $parser = new AssistantInterpreter;
        $this->assertSame('draw_draft', $parser->interpret('Create draw named New Singles', $this->context())['action']);
        $this->assertSame('help', $parser->interpret('Delete all draws and refund players', $this->context())['action']);
        $this->assertSame('business_action_not_supported', $parser->interpret('Delete draws without changing fees', $this->context())['reason_code']);
        $this->assertSame('unsupported_request', $parser->interpret('Translate the tournament guide', $this->context())['reason_code']);
        $this->assertArrayNotHasKey('reason_code', $parser->interpret('Create a draw', $this->context()));
    }
}
