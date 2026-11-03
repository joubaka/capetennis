<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Services\DivisionCapacityService;
use Tests\TestCase;

class DivisionCapacityPublicDisplayTest extends TestCase
{
    public function test_information_automatically_lists_only_configured_division_limits(): void
    {
        $event = new Event(['information' => '<p>Bring your racquet.</p>']);
        $event->setRelation('eventCategories', collect([
            $this->division(1, 'Orange under 9', 16),
            $this->division(2, 'Yellow under 14', null),
        ]));

        $html = view('frontend.event.partials.event-information', compact('event'))->render();

        $this->assertStringContainsString('Orange under 9: maximum 16 players', $html);
        $this->assertStringNotContainsString('Yellow under 14', $html);
        $this->assertStringContainsString('Bring your racquet.', $html);
    }

    public function test_uncapped_event_has_no_limit_notice(): void
    {
        $event = new Event();
        $event->setRelation('eventCategories', collect([$this->division(1, 'Yellow', null)]));

        $html = view('frontend.event.partials.event-information', compact('event'))->render();

        $this->assertStringNotContainsString('data-division-entry-limits', $html);
    }

    public function test_entry_options_show_remaining_places_and_disable_full_divisions(): void
    {
        $divisions = collect([
            $this->division(1, 'Orange', 16),
            $this->division(2, 'Red', 8),
            $this->division(3, 'Yellow', null),
        ]);
        $service = $this->mock(DivisionCapacityService::class);
        $service->shouldReceive('remaining')->once()->with($divisions[0])->andReturn(1);
        $service->shouldReceive('remaining')->once()->with($divisions[1])->andReturn(0);
        $service->shouldReceive('remaining')->once()->with($divisions[2])->andReturn(null);

        $html = view('frontend.event.partials.division-entry-options', ['eventCats' => $divisions])->render();

        $this->assertStringContainsString('1 spot left', $html);
        $this->assertStringContainsString('Full', $html);
        $this->assertSame(1, substr_count($html, 'disabled'));
        $this->assertStringContainsString('data-name="Orange"', $html);
        $this->assertStringContainsString('data-price="100"', $html);
    }

    public function test_specialist_registration_notice_shows_aggregate_availability(): void
    {
        $division = $this->division(1, 'Trials', 16);
        $this->mock(DivisionCapacityService::class)->shouldReceive('remaining')
            ->once()->with($division)->andReturn(3);

        $html = view('frontend.event.partials.division-availability', compact('division'))->render();

        $this->assertStringContainsString('3 spots left', $html);
        $this->assertStringContainsString('Maximum 16 players', $html);
        $this->assertStringContainsString('Checkouts in progress hold places', $html);
    }

    private function division(int $id, string $name, ?int $limit): CategoryEvent
    {
        $division = new CategoryEvent();
        $division->forceFill(['id' => $id, 'entry_limit' => $limit, 'entry_fee' => 100]);
        $division->setRelation('category', new Category(['name' => $name]));

        return $division;
    }
}
