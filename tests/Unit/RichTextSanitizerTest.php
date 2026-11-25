<?php

namespace Tests\Unit;

use App\Models\Announcement;
use App\Models\Event;
use App\Services\RichTextSanitizer;
use Tests\TestCase;

class RichTextSanitizerTest extends TestCase
{
    public function test_it_removes_scripts_event_handlers_and_unsafe_urls(): void
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
    public function test_announcement_and_event_models_sanitize_new_and_legacy_content(): void
    {
        $announcement = new Announcement();
        $announcement->message = '<p>Safe</p><img src=x onerror=alert(1)>';

        $event = new Event();
        $event->information = '<h2>Details</h2><iframe src="https://example.com"></iframe>';

        $this->assertStringContainsString('<p>Safe</p>', $announcement->getAttributes()['message']);
        $this->assertStringNotContainsString('onerror', $announcement->message);
        $this->assertStringContainsString('<h2>Details</h2>', $event->information);
        $this->assertStringNotContainsString('iframe', $event->information);

        $announcement->setRawAttributes(['message' => '<p onmouseover="bad()">Legacy</p>']);
        $this->assertSame('<p>Legacy</p>', $announcement->message);
    }
    public function test_event_content_preserves_word_tables_styles_and_valid_raster_images(): void
    {
        $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a17sAAAAASUVORK5CYII=';
        $html = '<p style="text-align:center;color:#ff0000;font-family:Calibri;font-size:14pt">Heading</p>'
            . '<table style="border-collapse:collapse"><tr><td colspan="2" style="background-color:#ffff00;border:1px solid #000000">Venue</td></tr></table>'
            . '<img src="'.$image.'" width="100" alt="Map">';
        $event = new Event(['information' => $html]);
        $announcement = new Announcement(['message' => $html]);
        foreach ([$event->information, $announcement->message] as $clean) {
            $this->assertStringContainsString('<table', $clean);
            $this->assertStringContainsString('colspan="2"', $clean);
            $this->assertStringContainsString('text-align:center', $clean);
            $this->assertStringContainsString('data:image/png;base64,', $clean);
        }
        $this->assertTrue(app(RichTextSanitizer::class)->hasEventContent('<img src="'.$image.'">'));
        $this->assertStringNotContainsString('<table', app(RichTextSanitizer::class)->sanitize($html));
        $this->assertStringNotContainsString('<img', app(RichTextSanitizer::class)->sanitize($html));
    }

    public function test_event_content_rejects_active_content_and_unsafe_image_sources(): void
    {
        $html = '<svg onload="alert(1)"></svg><iframe src="https://example.com"></iframe>'
            . '<p style="position:fixed;background-image:url(https://evil.example/x);color:red" onclick="alert(1)">Safe</p>'
            . '<img src="data:image/svg+xml;base64,PHN2Zy8+"><img src="file:///C:/secret.png"><img src="javascript:alert(1)">'
            . '<a href="data:text/html;base64,PHNjcmlwdD4=">Link</a>'
            . '<img src="data:image/png;base64,bm90IGFuIGltYWdlIGF0IGFsbA==">'
            . '<img src="data:image/jpeg;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+PC9zdmc+">';
        $clean = app(RichTextSanitizer::class)->sanitizeEventContent($html);
        foreach (['<svg', '<iframe', 'onclick', 'onload', 'position:', 'background-image', 'file:', 'javascript:', 'data:'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $clean);
        }
    }

    public function test_event_model_rejects_oversized_content_before_persistence(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        new Event(['information' => str_repeat('x', RichTextSanitizer::MAX_EVENT_BYTES + 1)]);
    }

    public function test_announcement_model_rejects_oversized_image_before_persistence(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        new Announcement(['message' => '<img src="data:image/png;base64,'.base64_encode(str_repeat('x', RichTextSanitizer::MAX_IMAGE_BYTES + 100)).'">']);
    }
}
