<?php

namespace Tests\Unit;

use App\Models\Announcement;
use App\Models\Event;
use App\Services\RichTextSanitizer;
use Tests\TestCase;

class RichTextSanitizerTest extends TestCase
{
    /** @test */
    public function it_removes_scripts_event_handlers_and_unsafe_urls(): void
    {
        $dirty = '<p onclick="alert(1)">Hello <strong>world</strong></p>'
            . '<script>alert(1)</script>'
            . '<a href="javascript:alert(1)">bad link</a>';

        $clean = app(RichTextSanitizer::class)->sanitize($dirty);

        $this->assertStringContainsString('<strong>world</strong>', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    /** @test */
    public function announcement_and_event_models_sanitize_new_and_legacy_content(): void
    {
        $announcement = new Announcement();
        $announcement->message = '<p>Safe</p><img src=x onerror=alert(1)>';

        $event = new Event();
        $event->information = '<h2>Details</h2><iframe src="https://example.com"></iframe>';

        $this->assertSame('<p>Safe</p>', $announcement->getAttributes()['message']);
        $this->assertStringNotContainsString('img', $announcement->message);
        $this->assertStringContainsString('<h2>Details</h2>', $event->information);
        $this->assertStringNotContainsString('iframe', $event->information);

        $announcement->setRawAttributes(['message' => '<p onmouseover="bad()">Legacy</p>']);
        $this->assertSame('<p>Legacy</p>', $announcement->message);
    }
}
