<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Event;
use Tests\TestCase;

class EventAnnouncementPresentationTest extends TestCase
{
    public function test_announcements_are_collapsed_and_keep_sanitized_content_and_revision_tracking(): void
    {
        $event = new Event(['name' => 'Example tennis event']);
        $event->id = 987654;
        $announcement = new Announcement(['title' => 'Starting times', 'message' => '<p>Play starts at 09:00. Please arrive 15 minutes early.</p><script>alert(1)</script>']);
        $announcement->id = 123;
        $announcement->created_at = now();
        $event->setRelation('announcements', collect([$announcement]));
        $html = view('frontend.event.partials.event-announcements', compact('event'))->render();

        $this->assertStringContainsString('View 1 new announcement', $html);
        $this->assertStringContainsString('ct:announcements:987654:guest', $html);
        $this->assertStringContainsString('Mark as viewed &amp; close', $html);
        $this->assertStringContainsString('<p>Play starts at 09:00.', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('<details open', $html);
        preg_match('/data-revision="([^"]+)"/', $html, $revision);
        $announcement->message = '<p>Updated: play starts at 10:00.</p>';
        $updated = view('frontend.event.partials.event-announcements', compact('event'))->render();
        $this->assertStringNotContainsString($revision[0], $updated);

        if (getenv('ANNOUNCEMENT_QA_EXPORT')) {
            $html = str_replace(asset('assets/js/event-announcements.js'), 'assets/js/event-announcements.js', $html);
            file_put_contents(public_path('announcement-qa.html'), '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="assets/vendor/css/core.css"><link rel="stylesheet" href="assets/vendor/css/theme-default.css"><link rel="stylesheet" href="css/frontend-workspace.css"></head><body class="ct-frontend"><main class="container py-4" style="max-width:900px"><h2>Example tennis event</h2>'.$html.'<section class="card p-4"><h3>Teams and registration</h3><p>The next event section remains easy to reach.</p></section></main></body></html>');
        }
    }

    public function test_no_announcement_card_is_rendered_for_an_empty_event(): void
    {
        $event = new Event;
        $event->setRelation('announcements', collect());
        $this->assertSame('', trim(view('frontend.event.partials.event-announcements', compact('event'))->render()));
    }
}
