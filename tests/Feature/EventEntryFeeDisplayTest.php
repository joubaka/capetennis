<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventType;
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

        $html = $this->renderFeePartial($event);

        $this->assertStringContainsString('Entry fee:', $html);
        $this->assertStringContainsString('R490.00', $html);
        $this->assertStringNotContainsString('R0.00', $html);
        $this->assertStringNotContainsString('Entry fees:', $html);
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
