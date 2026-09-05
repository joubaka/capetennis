<?php

namespace Tests\Unit;

use App\Models\Event;
use Tests\TestCase;

class EventWorkspaceHeaderTest extends TestCase
{
    public function test_it_links_to_the_public_page_and_event_home(): void
    {
        $event = new Event([
            'name' => 'Header links event',
            'start_date' => '2026-09-06',
        ]);
        $event->id = 233;
        $event->setRelation('series', null);

        $html = view('backend.event.partials.header', ['event' => $event])->render();

        $this->assertStringContainsString(
            'href="'.route('events.show', $event).'"',
            $html
        );
        $this->assertStringContainsString('target="_blank" rel="noopener"', $html);
        $this->assertStringContainsString('>Public page</span>', $html);
        $this->assertStringContainsString(
            'href="'.route('admin.events.overview', $event).'"',
            $html
        );
        $this->assertStringContainsString('>Event home</span>', $html);
    }
}
