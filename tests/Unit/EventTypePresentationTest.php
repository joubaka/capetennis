<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventType;
use App\Support\EventTypePresentation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventTypePresentationTest extends TestCase
{
    #[DataProvider('presentations')]
    public function test_existing_type_precedence_and_fixture_family_are_preserved(
        int $id, ?int $family, ?string $code, string $view, ?string $fixtures
    ): void {
        $event = $this->event($id, $family, $code);

        $this->assertSame($view, $event->frontend_type_view);
        $this->assertSame($view, EventTypePresentation::template($event));
        $this->assertSame($fixtures, EventTypePresentation::venueFixtureKind($event));
        $this->assertTrue(view()->exists('frontend.event.eventTypes.'.$view));
        $this->assertFalse($event->isInterprovincialTrials());
    }

    public static function presentations(): array
    {
        return [
            'individual legacy' => [1, null, null, 'individual', null],
            'individual alternate' => [6, 1, null, 'individual', null],
            'team legacy' => [3, null, null, 'team', 'team'],
            'team alternate' => [7, null, null, 'team', 'team'],
            'high school team' => [4, 2, 'team', 'team', 'team'],
            'platteland high schools' => [8, 2, 'team', 'team', 'team'],
            'persisted camp legacy zero family' => [2, 0, null, 'default', null],
            'interoro trials stays generic' => [12, 1, 'individual', 'default', null],
            'legacy event zero type' => [0, null, null, 'default', null],
            'team family arbitrary id' => [203, 2, null, 'team', 'team'],
            'interpro individual family' => [13, 1, null, 'interpro', 'individual'],
            'interpro legacy missing type' => [13, null, null, 'interpro', 'individual'],
            'interpro id team family wins' => [13, 2, null, 'team', 'team'],
            'interpro id camp family wins' => [13, 3, null, 'default', null],
            'masters legacy' => [14, null, null, 'masters', null],
            'masters code arbitrary id' => [214, 1, 'masters', 'masters', null],
            'masters code wins team family' => [3, 2, 'masters', 'masters', null],
            'masters id team family wins' => [14, 2, null, 'team', 'team'],
            'cavaliers trials' => [5, 1, null, 'cavaliers_trials', null],
            'parent child doubles' => [9, 1, null, 'parentChildDoubles', null],
            'camp arbitrary id' => [203, 3, null, 'default', null],
            'generic arbitrary id' => [203, null, null, 'default', null],
            'unknown individual family keeps fallback' => [203, 1, null, 'default', null],
        ];
    }

    public function test_trials_workflow_remains_distinct_from_interpro_and_family(): void
    {
        foreach (['interpro trials', 'Interprovincial Trials', 'Other name'] as $name) {
            $event = $this->event(13, 1, $name === 'Other name' ? EventType::INTERPROVINCIAL_TRIALS_CODE : null, $name);
            $this->assertTrue($event->isInterprovincialTrials());
            $this->assertSame('interpro', $event->frontend_type_view);
            $this->assertSame('interprovincial-trials', EventTypePresentation::template($event));
            $this->assertFalse($event->isMasters());
        }
        $this->assertFalse($this->event(13, 1, null, 'Interpro')->isInterprovincialTrials());
    }

    public function test_missing_type_records_keep_legacy_fallback_without_resolving_a_workflow(): void
    {
        foreach ([0 => 'default', 13 => 'interpro'] as $id => $view) {
            $event = (new Event)->forceFill(['eventType' => $id]);
            $event->setRelation('eventTypeModel', null);
            $this->assertSame($view, $event->frontend_type_view);
            $this->assertSame($view, EventTypePresentation::template($event));
            $this->assertFalse($event->isInterprovincialTrials());
        }
    }

    public function test_region_directory_scope_preserves_type_precedence_and_dedicated_trials(): void
    {
        foreach ([
            [3, EventType::TEAM, null, true],
            [13, EventType::INDIVIDUAL, 'individual', true],
            [13, null, null, true],
            [13, EventType::TEAM, null, true],
            [13, EventType::CAMP, null, false],
            [13, EventType::INDIVIDUAL, EventType::MASTERS_CODE, false],
            [13, EventType::INDIVIDUAL, EventType::INTERPROVINCIAL_TRIALS_CODE, false],
            [203, EventType::TEAM, EventType::INTERPROVINCIAL_TRIALS_CODE, false],
            [1, EventType::INDIVIDUAL, 'individual', false],
        ] as [$id, $family, $code, $expected]) {
            $this->assertSame($expected, $this->event($id, $family, $code)->requiresEventScopedRegionTeams());
        }
    }

    public function test_trials_fixture_preparation_keeps_legacy_behavior_without_inheriting_team_family(): void
    {
        foreach ([3 => 'team', 13 => 'individual', 203 => null] as $id => $fixtures) {
            $event = $this->event($id, EventType::TEAM, EventType::INTERPROVINCIAL_TRIALS_CODE);
            $this->assertSame('interprovincial-trials', EventTypePresentation::template($event));
            $this->assertSame($fixtures, EventTypePresentation::venueFixtureKind($event));
        }
    }

    public function test_document_shell_is_shared_while_individual_modal_and_single_delete_handler_remain(): void
    {
        foreach (['individual', 'team', 'interpro', 'masters', 'cavaliers_trials', 'parentChildDoubles', 'default'] as $view) {
            $source = file_get_contents(resource_path('views/frontend/event/eventTypes/'.$view.'.blade.php'));
            $this->assertStringContainsString("@include('frontend.event.partials.event-documents'", $source);
            $this->assertStringNotContainsString('deleteFileButton', $source);
        }
        $individual = file_get_contents(resource_path('views/frontend/event/eventTypes/individual.blade.php'));
        $this->assertStringContainsString("'documentUploadModal' => '#addFileModal'", $individual);
        $this->assertStringContainsString('id="addFileModal"', $individual);
        $shell = file_get_contents(resource_path('views/frontend/event/show.blade.php'));
        $this->assertSame(1, substr_count($shell, "on('click', '.deleteFileButton'"));
    }

    private function event(int $id, ?int $family, ?string $code, string $name = 'Type'): Event
    {
        $event = (new Event)->forceFill(['eventType' => $id]);
        $event->setRelation('eventTypeModel', (new EventType)->forceFill([
            'id' => $id, 'type' => $family, 'code' => $code, 'name' => $name,
        ]));

        return $event;
    }
}
