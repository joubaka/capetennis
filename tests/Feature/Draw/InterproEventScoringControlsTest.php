<?php

namespace Tests\Feature\Draw;

use Tests\TestCase;

class InterproEventScoringControlsTest extends TestCase
{
    public function test_interpro_event_exposes_venue_and_draw_scoring_entry_points(): void
    {
        $eventTemplate = file_get_contents(
            resource_path('views/frontend/event/eventTypes/interpro.blade.php')
        );
        $drawTemplate = file_get_contents(
            resource_path('views/frontend/event/partials/interpro-draws-desktop.blade.php')
        );

        $this->assertStringContainsString(
            "@include('frontend.event.partials._venue-scoring')",
            $eventTemplate
        );
        $this->assertStringContainsString("can('event.score', \$event)", $drawTemplate);
        $this->assertStringContainsString("route('frontend.scoring.workspace'", $drawTemplate);
    }
}
