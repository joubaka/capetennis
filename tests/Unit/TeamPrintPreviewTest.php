<?php

namespace Tests\Unit;

use App\Models\Draw;
use App\Models\Event;
use Tests\TestCase;

class TeamPrintPreviewTest extends TestCase
{
    public function test_pdf_links_render_for_venue_draw_and_age_previews(): void
    {
        $event = new Event;
        $event->id = 241;
        $draw = new Draw;
        $draw->id = 52;
        $base = ['name' => 'Print preview', 'fixtures' => collect(),
            'selectedDate' => '2026-10-09', 'availableDays' => collect(['2026-10-09'])];

        foreach ([
            [[], route('fixture.create.pdf.venue', ['fixtures' => []])],
            [['draw' => $draw], route('fixture.create.pdf', ['fixtures' => 52, 'date' => '2026-10-09'])],
            [['event' => $event, 'age' => 12], route('headoffice.venuePrintPack',
                ['event' => $event, 'age' => 12, 'date' => '2026-10-09', 'download' => 1])],
        ] as [$context, $expectedUrl]) {
            $html = view('backend.draw.pdf.team-print-preview', array_merge($base, $context))->render();
            $this->assertStringContainsString('href="'.e($expectedUrl).'">Save as PDF</a>', $html);
            $this->assertStringNotContainsString('@elseisset', $html);
        }
    }
}
