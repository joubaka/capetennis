<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EventAboutPanelTest extends TestCase
{
    public function test_individual_event_about_panel_uses_the_standard_event_layout(): void
    {
        $event = new Event([
            'entryFee' => 450,
            'organizer' => 'Cape Tennis Office',
            'email' => 'events@example.test',
        ]);

        $categoryEvent = new CategoryEvent(['entry_fee' => null]);
        $categoryEvent->setRelation('category', new Category(['name' => 'Under 14']));
        $event->setRelation('eventCategories', new Collection([$categoryEvent]));

        $html = view('frontend.event.partials.event-about', [
            'event' => $event,
            'sDate' => 'Fri 04 Dec 2026',
            'eDate' => 'Sun 06 Dec 2026',
            'formatEntryLine' => 'Fri 20 Nov 2026',
            'formatWithdrawalLine' => 'Fri 27 Nov 2026',
        ])->render();

        $this->assertStringContainsString('>About<', $html);
        $this->assertStringContainsString('Start Date:', $html);
        $this->assertStringContainsString('End Date:', $html);
        $this->assertStringContainsString('Entry deadline:', $html);
        $this->assertStringContainsString('Withdrawal deadline:', $html);
        $this->assertStringContainsString('Entry fee:', $html);
        $this->assertStringContainsString('R450.00', $html);
        $this->assertStringContainsString('>Contact<', $html);
        $this->assertStringContainsString('Cape Tennis Office', $html);
        $this->assertStringContainsString('mailto:events@example.test', $html);
        $this->assertStringNotContainsString('At a glance', $html);

        $this->assertTrue(
            strpos($html, 'Start Date:') < strpos($html, 'End Date:')
            && strpos($html, 'End Date:') < strpos($html, 'Entry deadline:')
            && strpos($html, 'Entry deadline:') < strpos($html, 'Withdrawal deadline:')
            && strpos($html, 'Withdrawal deadline:') < strpos($html, 'Entry fee:'),
            'The About facts should follow the same order as the standard event template.'
        );
    }
}
