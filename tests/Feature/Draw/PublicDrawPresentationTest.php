<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, DrawSetting, Event, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PublicDrawPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_robin_draft_preview_uses_shared_navigation_and_schedule_contract(): void
    {
        Gate::before(fn (?User $user) => true);
        $this->actingAs((new User)->forceFill(['id' => 99]));

        $event = (new Event)->forceFill([
            'id' => 233,
            'name' => 'Overberg Tennis Trials - Leg 3 2026',
            'start_date' => '2026-09-06 08:00:00',
        ]);
        $draw = (new Draw)->forceFill([
            'id' => 1449,
            'event_id' => 233,
            'drawName' => 'u/19 Boys',
            'published' => false,
            'oop_published' => true,
        ]);
        $draw->setRelation('event', $event);
        $draw->setRelation('venues', collect());
        $draw->setRelation('settings', (new DrawSetting)->forceFill(['workflow' => 'round_robin']));
        $draw->setRelation('flexibleMonrad', null);

        $html = view('frontend.roundrobin.show', [
            'draw' => $draw,
            'groupsJson' => collect(),
            'rrFixtures' => [],
            'oops' => collect(),
            'standings' => [],
            'svg' => null,
        ])->render();

        $this->assertStringContainsString('Draft preview', $html);
        $this->assertStringContainsString('Draw not published', $html);
        $this->assertStringContainsString('Manage this draw', $html);
        $this->assertStringContainsString('Fixtures', $html);
        $this->assertStringContainsString('Round-robin standings', $html);
        $this->assertStringNotContainsString('id="matrix-tab"', $html);
        $this->assertStringContainsString('<details class="mt-4 rr-matrix-details" id="matrix-pane">', $html);
        $this->assertMatchesRegularExpression('/id="oop-tab"[\s\S]*?aria-selected="true"/', $html);
        $this->assertMatchesRegularExpression('/class="tab-pane fade show active"\s+id="oop-pane"/', $html);
        $this->assertStringContainsString('Find your name, then confirm the date, time and venue.', $html);
        $this->assertStringContainsString('Match times to follow', $html);
        $this->assertStringContainsString('Match times and venues have not been published yet.', $html);
        $this->assertStringNotContainsString('Match times published', $html);
        $this->assertStringNotContainsString('<th class="text-center">Court</th>', $html);
        $this->assertStringContainsString('roundrobin-public.js?v=', $html);
        $this->assertStringContainsString('draw-roundrobin.css?v=', $html);
        $this->assertStringNotContainsString('width: 24px !important', $html);
        $this->assertStringContainsString('td[data-label="Time"]', $html);
        $this->assertStringNotContainsString('<span class="badge bg-label-success">Draw published</span>', $html);
    }

    public function test_public_script_orders_fixtures_by_round_and_preserves_public_venue_only_display(): void
    {
        $script = str_replace("\r\n", "\n", file_get_contents(public_path('assets/js/roundrobin-public.js')));

        $this->assertStringContainsString('const sorted = RR_OOP.slice().sort(', $script);
        $this->assertStringContainsString('data-label="Match"', $script);
        $this->assertStringContainsString('data-label="Date"', $script);
        $this->assertStringContainsString('data-label="Time"', $script);
        $this->assertStringContainsString('data-label="Venue"', $script);
        $this->assertStringNotContainsString('data-label="Court"', $script);
        $this->assertStringNotContainsString("'Court ' + fx.court", $script);
        $this->assertStringContainsString("const followedBy = window.RR_SCHEDULE_AVAILABLE === false ? 'Time to follow' : 'Followed by';", $script);
        $this->assertStringContainsString("fx.schedule_hidden === true\n            ? 'Followed by'", $script);
        $this->assertStringContainsString("data-label=\"Date\" class=\"text-center\">\${escapeHtml(schedule.date || '—')}", $script);
        $this->assertStringContainsString("data-label=\"Time\" class=\"text-center\">\${escapeHtml(scheduleHidden ? followedBy", $script);
        $this->assertStringContainsString("data-label=\"Venue\">\${escapeHtml(fx.venue_name", $script);
        $this->assertStringContainsString('const round = Number(a.round || 1) - Number(b.round || 1);', $script);
        $this->assertStringContainsString('wrapper.empty();', $script);
        $this->assertStringContainsString('rr-matrix-table-shell', $script);
        $this->assertStringContainsString('rr-mobile-match-list', $script);
        $this->assertStringContainsString("url.searchParams.set('_fresh', Date.now().toString());", $script);
        $this->assertStringContainsString("cache: 'no-store'", $script);
        $this->assertStringContainsString("'Cache-Control': 'no-cache'", $script);
    }

    public function test_fixture_completion_distinguishes_upcoming_matches_from_drawn_and_terminal_results(): void
    {
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $draw->settings()->create(['workflow' => 'round_robin', 'score_format' => 'timed', 'num_sets' => 1]);
        $players = \App\Models\Registration::factory()->count(2)->create();
        $fixtures = collect();
        foreach ([
            ['pending', 0, null, null, null, false],
            ['in progress', 2, [2, 1], null, null, false],
            ['drawn result', 1, [3, 3], null, null, true],
            ['completed result', 1, [4, 2], $players[0]->id, null, true],
            ['walkover', 3, null, $players[0]->id, null, true],
            ['retirement', 4, [2, 1], $players[1]->id, null, true],
            ['void result', 1, [3, 3], null, $players[0]->id, true],
            ['double bye', 5, null, null, null, true],
        ] as $index => [$label, $status, $score, $winner, $voided, $completed]) {
            $fixture = \App\Models\Fixture::factory()->create([
                'draw_id' => $draw->id, 'stage' => 'RR', 'match_nr' => $index + 1,
                'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id,
                'match_status' => $status, 'winner_registration' => $winner,
                'round_robin_voided_registration_id' => $voided,
            ]);
            if ($score) $fixture->fixtureResults()->create([
                'set_nr' => 1, 'registration1_score' => $score[0], 'registration2_score' => $score[1],
            ]);
            $fixtures->put($fixture->id, [$label, $completed]);
        }
        $before = \App\Models\Fixture::where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $rows = app(\App\Services\DrawService::class)->loadRoundRobinHub($draw)['oops'];
        foreach ($rows as $row) {
            [$label, $completed] = $fixtures[$row['id']];
            $this->assertSame($completed, $row['completed'], $label);
        }
        $this->assertCount($fixtures->count(), $rows);
        $this->assertSame($before, \App\Models\Fixture::where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
    }
}
