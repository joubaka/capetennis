<?php

namespace Tests\Feature;

use Tests\TestCase;

class EventInformationDrawLinkTest extends TestCase
{
    public function test_information_has_no_draw_link_before_publication(): void
    {
        $html = $this->information([['published' => false]]);

        $this->assertStringNotContainsString('data-event-draw-jump style=', $html);
        $this->assertStringNotContainsString('View published draw', $html);
    }

    public function test_information_links_to_a_single_published_draw_even_when_drafts_exist(): void
    {
        $html = $this->information([['published' => true], ['published' => false]]);

        $this->assertStringContainsString('href="#event-draws-match-times"', $html);
        $this->assertStringContainsString("View published draw\n", str_replace("\r", '', $html));
        $this->assertStringNotContainsString('View published draws', $html);
        $this->assertLessThan(strpos($html, 'View published draw'), strpos($html, 'Tournament details.'));
    }

    public function test_information_links_to_multiple_published_draws(): void
    {
        $this->assertStringContainsString('View published draws', $this->information([
            ['published' => true], ['published' => true], ['published' => false],
        ]));
    }

    private function information(array $draws): string
    {
        return view('frontend.event.partials.event-information', [
            'event' => (object) ['information' => '<p>Tournament details.</p>'],
            'eventDraws' => collect($draws),
        ])->render();
    }
}
