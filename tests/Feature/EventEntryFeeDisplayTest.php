<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventType;
use App\Services\DivisionCapacityService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EventEntryFeeDisplayTest extends TestCase
{
    public function test_it_displays_one_event_fee_when_all_effective_category_fees_match(): void
    {
        $event = $this->eventWithCategoryFees(285, [
            ['Boys under 12', null],
            ['Girls under 12', 285],
        ]);

        $html = $this->renderFeePartial($event);

        $this->assertStringContainsString('Entry fee:', $html);
        $this->assertStringContainsString('R285.00', $html);
        $this->assertStringNotContainsString('Boys under 12', $html);
        $this->assertStringNotContainsString('Entry fees:', $html);
    }

    public function test_it_displays_each_category_when_effective_fees_differ(): void
    {
        $event = $this->eventWithCategoryFees(285, [
            ['Boys under 12', null],
            ['Girls under 12', 250],
        ]);

        $html = $this->renderFeePartial($event);

        $this->assertStringContainsString('Entry fees:', $html);
        $this->assertStringContainsString('Boys under 12', $html);
        $this->assertStringContainsString('R285.00', $html);
        $this->assertStringContainsString('Girls under 12', $html);
        $this->assertStringContainsString('R250.00', $html);
    }

    public function test_team_event_uses_event_fee_instead_of_imported_zero_category_fees(): void
    {
        $event = $this->eventWithCategoryFees(490, [
            ['Overberg under 10 boys', 0],
            ['West Coast under 10 boys', 0],
        ]);
        $eventType = new EventType();
        $eventType->forceFill(['type' => EventType::TEAM]);
        $event->setRelation('eventTypeModel', $eventType);
        $event->eventCategories->each(fn ($category) => $category->entry_limit = 16);
        $this->mock(DivisionCapacityService::class)->shouldNotReceive('remaining');

        $html = $this->renderFeePartial($event);

        $this->assertStringContainsString('Entry fee:', $html);
        $this->assertStringContainsString('R490.00', $html);
        $this->assertStringNotContainsString('R0.00', $html);
        $this->assertStringNotContainsString('Entry fees:', $html);
        $this->assertStringNotContainsString('spots left', $html);
    }

    public function test_different_fees_show_remaining_spots_beside_capped_categories(): void
    {
        $event = $this->eventWithCategoryFees(285, [['Orange', 200], ['Green', null]]);
        $orange = $event->eventCategories[0];
        $orange->entry_limit = 16;
        $this->mock(DivisionCapacityService::class)->shouldReceive('remaining')->once()->with($orange)->andReturn(5);
        $html = $this->renderFeePartial($event);
        $this->assertStringContainsString('Orange', $html);
        $this->assertStringContainsString('R200.00', $html);
        $this->assertStringContainsString('5 spots left', $html);
        $this->assertStringContainsString('Green', $html);
        $this->assertStringContainsString('R285.00', $html);
        $this->assertSame(1, substr_count($html, 'spots left'));
    }

    public function test_equal_fees_keep_capped_rows_visible_with_full_and_single_spot_labels(): void
    {
        $event = $this->eventWithCategoryFees(285, [['Red', null], ['Orange', 285], ['Yellow', null]]);
        $red = $event->eventCategories[0];
        $orange = $event->eventCategories[1];
        $red->entry_limit = 16;
        $orange->entry_limit = 4;
        $capacity = $this->mock(DivisionCapacityService::class);
        $capacity->shouldReceive('remaining')->once()->with($red)->andReturn(0);
        $capacity->shouldReceive('remaining')->once()->with($orange)->andReturn(1);
        $html = $this->renderFeePartial($event);
        $this->assertStringContainsString('Entry fees:', $html);
        $this->assertStringContainsString('Red', $html);
        $this->assertStringContainsString('Full', $html);
        $this->assertStringContainsString('1 spot left', $html);
        $this->assertStringContainsString('Yellow', $html);
        $this->assertSame(3, substr_count($html, 'R285.00'));
        $this->assertStringNotContainsString('0 spots left', $html);
    }

    private function eventWithCategoryFees(float $eventFee, array $fees): Event
    {
        $event = new Event(['entryFee' => $eventFee]);

        $categoryEvents = collect($fees)->map(function (array $fee) use ($event) {
            $categoryEvent = new CategoryEvent(['entry_fee' => $fee[1]]);
            $categoryEvent->setRelation('event', $event);
            $categoryEvent->setRelation('category', new Category([
                'name' => $fee[0],
            ]));

            return $categoryEvent;
        });

        $event->setRelation('eventCategories', new Collection($categoryEvents));

        return $event;
    }

    private function renderFeePartial(Event $event): string
    {
        return view('frontend.event.partials.entry-fees', compact('event'))->render();
    }
}
