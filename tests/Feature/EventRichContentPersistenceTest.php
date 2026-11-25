<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRichContentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_and_table_content_larger_than_text_column_limit_survives_save_and_reload(): void
    {
        // A valid PNG with harmless trailing bytes exercises the former 64 KB column limit.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a17sAAAAASUVORK5CYII=').str_repeat("\0", 70000);
        $html = '<table><tr><td style="background-color:#ffff00">Court 1</td></tr></table>'
            . '<img alt="Venue map" src="data:image/png;base64,'.base64_encode($png).'">';
        $this->assertGreaterThan(65535, strlen($html));
        $event = Event::factory()->create(['information' => $html]);
        $announcement = Announcement::create(['event_id' => $event->id, 'title' => 'Venue map', 'message' => $html]);
        $this->assertSame($event->information, $event->fresh()->information);
        $this->assertSame($announcement->message, $announcement->fresh()->message);
        $this->assertStringContainsString('<table>', $event->fresh()->information);
        $this->assertStringContainsString('data:image/png;base64,', $announcement->fresh()->message);
        $this->assertDatabaseCount('announcements', 1);
    }
}
