<?php

namespace Tests\Unit;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DrawPackPresentationTest extends TestCase
{
    public function test_draw_pack_renders_correct_matrix_schedule_rules_and_pathways(): void
    {
        $html = view('backend.draw.pdf.draw-pack', $this->viewData())->render();

        $this->assertMatchesRegularExpression('/Home Player<\/th>.*?<td class="diagonal"><\/td>.*?<td>6-2<\/td>.*?<td>1<\/td>/s', $html);
        $this->assertMatchesRegularExpression('/Away Player<\/th>.*?<td>2-6<\/td>.*?<td class="diagonal"><\/td>.*?<td>0<\/td>/s', $html);
        $this->assertStringContainsString('2 matches in this pack are not yet fully assigned', $html);
        $this->assertStringContainsString('already has a time but still needs a venue or court', $html);
        $this->assertStringContainsString('Report to the referee before play.', $html);
        $this->assertStringContainsString('Draft draw', $html);
        $this->assertStringContainsString('Schedule unpublished', $html);
        $this->assertStringContainsString('Bracket and placement pathways', $html);
        $this->assertStringContainsString('Winner to M3', $html);
    }

    public function test_large_groups_use_a_readable_ledger(): void
    {
        $data = $this->viewData();
        $players = collect(range(1, 13))->map(fn (int $id) => [
            'id' => $id,
            'display_name' => "Player {$id}",
            'pivot' => ['seed' => $id],
        ])->all();
        $draw = $data['draws']->first();
        $draw['groups'][0]['registrations'] = $players;
        $data['draws'] = collect([$draw]);

        $html = view('backend.draw.pdf.draw-pack', $data)->render();

        $this->assertStringContainsString('a 13-player grid would be unreadable on A4', $html);
        $this->assertStringContainsString('large-box results ledger', $html);
    }

    public function test_draw_pack_pdf_is_generated(): void
    {
        $pdf = Pdf::loadView('backend.draw.pdf.draw-pack', $this->viewData())
            ->setPaper('A4', 'landscape')
            ->output();

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_browser_print_builders_escape_untrusted_draw_content(): void
    {
        $workspacePrint = file_get_contents(resource_path('views/backend/draw/roundrobin/print-scripts.blade.php'));
        $eventPrint = file_get_contents(resource_path('views/backend/headOffice/individual-event-show.blade.php'));

        $this->assertStringContainsString('function escapeHtml(value)', $workspacePrint);
        $this->assertStringContainsString("escapeHtml(fx.home || '---')", $workspacePrint);
        $this->assertStringContainsString('escapeHtml(group.name)', $workspacePrint);
        $this->assertStringContainsString('escapeHtml(drawName)', $workspacePrint);
        $this->assertStringContainsString('function escapePrintHtml(value)', $eventPrint);
        $this->assertStringContainsString("escapePrintHtml(fx.home || '---')", $eventPrint);
        $this->assertStringContainsString('escapePrintHtml(drawData.name)', $eventPrint);
        $this->assertStringNotContainsString("var home = fx.home || '---'", $eventPrint);
        $this->assertStringContainsString('<fieldset class="mb-3">', $eventPrint);
        $this->assertStringContainsString('aria-labelledby="draw-pack-modal-title"', $eventPrint);
        $this->assertStringContainsString('assistive-technology-friendly version', $eventPrint);
    }

    private function viewData(): array
    {
        $event = new Event([
            'name' => 'Cape Junior Championships',
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
        ]);
        $fixtures = [
            [
                'id' => 10, 'stage' => 'RR', 'round' => 1, 'match_nr' => 10,
                'playoff_type' => null, 'home' => 'Home Player', 'away' => 'Away Player',
                'r1_id' => 1, 'r2_id' => 2, 'score' => '6-2', 'winner' => 1,
                'scheduled_at' => '2026-09-20 08:00:00', 'venue' => 'Newlands Tennis Club',
                'court' => 'Court 1', 'duration' => 75, 'winner_feeders' => [],
                'loser_feeders' => [], 'winner_to' => null, 'loser_to' => null,
            ],
            [
                'id' => 1, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 1,
                'playoff_type' => null, 'home' => 'Group A #1', 'away' => 'Group B #2',
                'r1_id' => null, 'r2_id' => null, 'score' => '', 'winner' => null,
                'scheduled_at' => null, 'venue' => null, 'court' => null, 'duration' => 75,
                'winner_feeders' => [], 'loser_feeders' => [], 'winner_to' => 3, 'loser_to' => null,
            ],
            [
                'id' => 3, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 3,
                'playoff_type' => null, 'home' => 'Winner of Match 1', 'away' => 'Winner of Match 2',
                'r1_id' => null, 'r2_id' => null, 'score' => '', 'winner' => null,
                'scheduled_at' => '2026-09-20 12:00:00', 'venue' => 'Newlands Tennis Club',
                'court' => '', 'duration' => 75, 'winner_feeders' => [1, 2],
                'loser_feeders' => [], 'winner_to' => null, 'loser_to' => null,
            ],
        ];
        $draw = [
            'id' => 1,
            'name' => 'Boys U14',
            'format' => 'Round Robin and Playoffs',
            'groups' => [[
                'id' => 1,
                'name' => 'A',
                'registrations' => [
                    ['id' => 1, 'display_name' => 'Home Player', 'pivot' => ['seed' => 1]],
                    ['id' => 2, 'display_name' => 'Away Player', 'pivot' => ['seed' => 2]],
                ],
            ]],
            'rrFixtures' => [1 => [[
                'id' => 10,
                'group_id' => 1,
                'r1_id' => 1,
                'r2_id' => 2,
                'all_sets' => ['6-2'],
                'score' => '6-2',
                'home_score' => 6,
                'away_score' => 2,
                'winner' => 1,
            ]]],
            'oops' => $fixtures,
            'standings' => [],
            'notes' => ['General rules' => 'Report to the referee before play.'],
            'published' => false,
            'schedule_published' => false,
            'locked' => false,
        ];
        $schedule = collect($fixtures)
            ->whereNotNull('scheduled_at')
            ->map(fn (array $fixture) => $fixture + ['draw_id' => 1, 'draw_name' => 'Boys U14'])
            ->values();

        return [
            'event' => $event,
            'draws' => new Collection([$draw]),
            'schedule' => $schedule,
            'includeStandings' => true,
            'autoPrint' => false,
        ];
    }
}
