<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FlexibleMonradDraw;
use App\Models\DrawGroup;
use App\Models\DrawGroupRegistration;
use App\Models\FixtureResult;
use App\Models\OrderOfPlay;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use App\Models\Venue;
use App\Services\Draw\FlexibleMonradService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawPackTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->event = Event::factory()->create([
            'name' => 'Cape Junior Championships',
            'eventType' => 6,
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
        ]);
        DB::table('event_admins')->insert([
            'event_id' => $this->event->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_pack_includes_flexible_draw_players_and_applied_schedule(): void
    {
        $draw = Draw::factory()->create([
            'event_id' => $this->event->id,
            'drawName' => 'Boys U14',
        ]);
        $draw->settings()->create(['workflow' => 'custom_monrad']);
        FlexibleMonradDraw::create([
            'draw_id' => $draw->id,
            'revision' => 1,
            'draft' => ['size' => 4, 'slots' => []],
            'graph' => ['matches' => []],
        ]);

        $home = Registration::factory()->create();
        $away = Registration::factory()->create();
        $home->players()->attach(Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith']));
        $away->players()->attach(Player::factory()->create(['name' => 'Thando', 'surname' => 'Dlamini']));

        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'FM',
            'round' => 1,
            'match_nr' => 1,
            'registration1_id' => $home->id,
            'registration2_id' => $away->id,
        ]);
        $venue = Venue::forceCreate(['name' => 'Newlands Tennis Club']);
        OrderOfPlay::create([
            'draw_id' => $draw->id,
            'fixture_id' => $fixture->id,
            'venue_id' => $venue->id,
            'court' => 'Court 2',
            'time' => '2026-09-20 09:30:00',
            'duration_minutes' => 75,
        ]);

        $this->actingAs($this->admin)
            ->get(route('headoffice.drawPack', ['event' => $this->event, 'draw_ids' => [$draw->id]]))
            ->assertOk()
            ->assertSee('Complete Draw Pack', false)
            ->assertSee('Boys U14')
            ->assertSee('Flexible Monrad')
            ->assertSee('Jamie Smith')
            ->assertSee('Thando Dlamini')
            ->assertSee('Newlands Tennis Club')
            ->assertSee('Court 2')
            ->assertSee('09:30')
            ->assertSee('Master order of play');
    }

    public function test_pack_rejects_draws_from_another_event(): void
    {
        $foreignDraw = Draw::factory()->create([
            'event_id' => Event::factory()->create()->id,
            'drawName' => 'Private foreign draw',
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('headoffice.drawPack', [
                'event' => $this->event,
                'draw_ids' => [$foreignDraw->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('draw_ids')
            ->assertDontSee('Private foreign draw');
    }

    public function test_unfiltered_pack_includes_every_match_from_every_event_draw(): void
    {
        $firstDraw = Draw::factory()->create([
            'event_id' => $this->event->id,
            'drawName' => 'Boys U16',
        ]);
        $secondDraw = Draw::factory()->create([
            'event_id' => $this->event->id,
            'drawName' => 'Girls U16',
        ]);

        Fixture::factory()->create([
            'draw_id' => $firstDraw->id,
            'stage' => 'MAIN',
            'match_nr' => 101,
        ]);
        Fixture::factory()->create([
            'draw_id' => $secondDraw->id,
            'stage' => 'MAIN',
            'match_nr' => 202,
        ]);

        $this->actingAs($this->admin)
            ->get(route('headoffice.drawPack', $this->event))
            ->assertOk()
            ->assertSee('2</strong><span>Total matches', false)
            ->assertSee('Boys U16')
            ->assertSee('Girls U16')
            ->assertSee('M101')
            ->assertSee('M202');
    }

    public function test_round_robin_matrix_orients_scores_and_prints_canonical_win_totals(): void
    {
        $draw = Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'Boys U12']);
        $draw->settings()->create(['workflow' => 'round_robin']);
        $group = DrawGroup::factory()->create(['draw_id' => $draw->id, 'name' => 'A']);

        $home = Registration::factory()->create();
        $away = Registration::factory()->create();
        $home->players()->attach(Player::factory()->create(['name' => 'Home', 'surname' => 'Player']));
        $away->players()->attach(Player::factory()->create(['name' => 'Away', 'surname' => 'Player']));
        DrawGroupRegistration::factory()->create(['draw_group_id' => $group->id, 'registration_id' => $home->id, 'seed' => 1]);
        DrawGroupRegistration::factory()->create(['draw_group_id' => $group->id, 'registration_id' => $away->id, 'seed' => 2]);

        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'draw_group_id' => $group->id,
            'stage' => 'RR',
            'registration1_id' => $home->id,
            'registration2_id' => $away->id,
        ]);
        FixtureResult::factory()->create([
            'fixture_id' => $fixture->id,
            'registration1_score' => 6,
            'registration2_score' => 2,
            'winner_registration' => $home->id,
            'loser_registration' => $away->id,
        ]);
        $fixture->update(['winner_registration' => $home->id]);

        $html = $this->actingAs($this->admin)
            ->get(route('headoffice.drawPack', ['event' => $this->event, 'draw_ids' => [$draw->id]]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/Home Player<\/th>.*?<td class="diagonal"><\/td>.*?<td>6-2<\/td>.*?<td>1<\/td>/s', $html);
        $this->assertMatchesRegularExpression('/Away Player<\/th>.*?<td>2-6<\/td>.*?<td class="diagonal"><\/td>.*?<td>0<\/td>/s', $html);
    }

    public function test_timed_match_without_a_court_remains_incomplete(): void
    {
        $draw = Draw::factory()->create(['event_id' => $this->event->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        $venue = Venue::forceCreate(['name' => 'Incomplete Venue']);
        OrderOfPlay::create([
            'draw_id' => $draw->id,
            'fixture_id' => $fixture->id,
            'venue_id' => $venue->id,
            'court' => null,
            'time' => '2026-09-20 09:30:00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('headoffice.drawPack', ['event' => $this->event, 'draw_ids' => [$draw->id]]))
            ->assertOk()
            ->assertSee('1 match in this pack is not yet fully assigned a date, venue and court.')
            ->assertSee('1 already has a time but still needs a venue or court.')
            ->assertSee('Incomplete Venue')
            ->assertSee('TBA');
    }

    public function test_pack_includes_saved_rules_lifecycle_state_and_pathway_board(): void
    {
        $draw = Draw::factory()->create([
            'event_id' => $this->event->id,
            'drawName' => 'Girls U18',
            'published' => 0,
            'oop_published' => 0,
            'locked' => 0,
        ]);
        $draw->settings()->create([
            'workflow' => 'round_robin_playoffs',
            'notes' => ['general' => 'Report to the referee before play.'],
        ]);
        $final = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 3]);
        Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'MAIN',
            'round' => 1,
            'match_nr' => 1,
            'parent_fixture_id' => $final->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('headoffice.drawPack', ['event' => $this->event, 'draw_ids' => [$draw->id]]))
            ->assertOk()
            ->assertSee('General rules')
            ->assertSee('Report to the referee before play.')
            ->assertSee('Draft draw')
            ->assertSee('Schedule unpublished')
            ->assertSee('Bracket and placement pathways')
            ->assertSee('Winner to M3');
    }

    public function test_pack_names_unresolved_feeders_and_orders_fixture_rows_by_time(): void
    {
        $draw = Draw::factory()->create([
            'event_id' => $this->event->id,
            'drawName' => 'Girls U16',
        ]);
        $venue = Venue::forceCreate(['name' => 'Rondebosch Tennis Club']);

        $semi1 = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 1,
        ]);
        $semi2 = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 2,
        ]);
        $final = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 3,
        ]);
        $playoff = Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 4,
        ]);

        $semi1->update([
            'parent_fixture_id' => $final->id, 'feeder_slot' => 1,
            'loser_parent_fixture_id' => $playoff->id, 'loser_feeder_slot' => 1,
        ]);
        $semi2->update([
            'parent_fixture_id' => $final->id, 'feeder_slot' => 2,
            'loser_parent_fixture_id' => $playoff->id, 'loser_feeder_slot' => 2,
        ]);

        OrderOfPlay::create([
            'draw_id' => $draw->id, 'fixture_id' => $final->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-09-20 15:00:00', 'duration_minutes' => 75,
        ]);
        OrderOfPlay::create([
            'draw_id' => $draw->id, 'fixture_id' => $playoff->id, 'venue_id' => $venue->id,
            'court' => '2', 'time' => '2026-09-20 13:00:00', 'duration_minutes' => 75,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('headoffice.drawPack', ['event' => $this->event, 'draw_ids' => [$draw->id]]))
            ->assertOk()
            ->assertSee('Winner of Match 1')
            ->assertSee('Winner of Match 2')
            ->assertSee('Loser of Match 1')
            ->assertSee('Loser of Match 2');

        $fixtureSection = str($response->getContent())->after('Fixtures in scheduled order');
        $positions = collect(['M4', 'M3', 'M1'])
            ->map(fn (string $match) => $fixtureSection->position($match));
        $this->assertNotContains(false, $positions->all());
        $this->assertTrue($positions[0] < $positions[1]);
        $this->assertTrue($positions[1] < $positions[2]);
    }

    public function test_guest_and_unrelated_user_cannot_access_pack(): void
    {
        $url = route('headoffice.drawPack', $this->event);

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    }

    public function test_pack_can_be_downloaded_as_a_pdf(): void
    {
        $draw = Draw::factory()->create([
            'event_id' => $this->event->id,
            'drawName' => 'Girls U12',
        ]);
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN']);

        $response = $this->actingAs($this->admin)->get(route('headoffice.drawPack', [
            'event' => $this->event,
            'draw_ids' => [$draw->id],
            'download' => 1,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('draw_pack.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_per_venue_order_of_play_separates_selected_draws_into_venue_copies(): void
    {
        $firstDraw = Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'Boys U14']);
        $secondDraw = Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'Girls U14']);
        $newlands = Venue::forceCreate(['name' => 'Newlands Tennis Club']);
        $rondebosch = Venue::forceCreate(['name' => 'Rondebosch Tennis Club']);

        foreach ([
            [$firstDraw, $newlands, 'Court 1', '2026-09-20 09:00:00'],
            [$secondDraw, $newlands, 'Court 2', '2026-09-20 10:30:00'],
            [$secondDraw, $rondebosch, 'Court 3', '2026-09-21 08:30:00'],
        ] as [$draw, $venue, $court, $time]) {
            $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
            OrderOfPlay::create([
                'draw_id' => $draw->id,
                'fixture_id' => $fixture->id,
                'venue_id' => $venue->id,
                'court' => $court,
                'time' => $time,
            ]);
        }

        Fixture::factory()->create([
            'draw_id' => $firstDraw->id,
            'match_nr' => 99,
        ]);

        $response = $this->actingAs($this->admin)->get(route('headoffice.drawPack', [
            'event' => $this->event,
            'draw_ids' => [$firstDraw->id, $secondDraw->id],
            'print_type' => 'venue',
        ]));

        $response->assertOk()
            ->assertSee('Per-Venue Order of Play')
            ->assertSee('Venue order of play')
            ->assertSee('Newlands Tennis Club')
            ->assertSee('Rondebosch Tennis Club')
            ->assertSee('Boys U14')
            ->assertSee('Girls U14')
            ->assertSee('Court 1')
            ->assertSee('Court 2')
            ->assertSee('Court 3')
            ->assertSee('Not on venue list: Boys U14 - M99')
            ->assertSee('Missing time and venue')
            ->assertDontSee('Complete Draw Pack');

        $this->assertSame(2, substr_count($response->getContent(), 'Venue order of play'));
    }

    public function test_pack_rejects_unknown_print_type(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('headoffice.drawPack', [
                'event' => $this->event,
                'print_type' => 'private-data',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('print_type');
    }

    public function test_multiple_flexible_monrad_brackets_download_as_one_direct_pdf(): void
    {
        $boys = $this->generatedFlexibleMonradDraw('Boys U15 Monrad');
        $girls = $this->generatedFlexibleMonradDraw('Girls U15 Monrad');

        $this->actingAs($this->admin)
            ->get(route('headoffice.drawPack', [
                'event' => $this->event,
                'draw_ids' => [$boys->id, $girls->id],
                'print_type' => 'bracket',
                'download' => 1,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('Cape_Junior_Championships_monrad_brackets.pdf');

        $eventPage = $this->get(route('headOffice.show', $this->event));
        $eventPage->assertOk()
            ->assertSee('Flexible Monrad Bracket Only')
            ->assertSee('Print the graphical bracket exactly as shown in the Monrad workspace')
            ->assertSee('Select one or more Flexible Monrad draws above')
            ->assertSee('Each selected draw is fitted to one PDF page.');

        $modal = file_get_contents(resource_path('views/backend/headOffice/individual-event-show.blade.php'));
        $this->assertStringContainsString('function downloadSelectedMonradBrackets()', $modal);
        $this->assertStringContainsString("params.append('draw_ids[]', $(this).val())", $modal);
        $this->assertStringNotContainsString('openSelectedMonradBracket', $modal);
        $this->assertStringNotContainsString('data-monrad-print-url', $modal);

        $pdfView = file_get_contents(resource_path('views/backend/draw/pdf/flexible-monrad-brackets.blade.php'));
        $this->assertStringContainsString('height: 166mm', $pdfView);
        $this->assertStringContainsString('page-break-after: always', $pdfView);
        $this->assertStringContainsString('All brackets and final positions', $pdfView);

        $boardView = file_get_contents(resource_path('views/backend/draw/pdf/partials/flexible-monrad-board-svg.blade.php'));
        $this->assertStringContainsString('data-ct-edge=""', $boardView);
        $this->assertStringContainsString("'#eaf5fc'", $boardView);
        $this->assertStringContainsString("'#fff4d6'", $boardView);
        $this->assertStringNotContainsString('class="card"', $boardView);

        $standardDraw = Draw::factory()->create([
            'event_id' => $this->event->id,
            'drawName' => 'Standard Knockout',
        ]);
        $this->getJson(route('headoffice.drawPack', [
            'event' => $this->event,
            'draw_ids' => [$standardDraw->id],
            'print_type' => 'bracket',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('draw_ids');
    }

    private function generatedFlexibleMonradDraw(string $name, int $size = 4): Draw
    {
        $category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $draw = Draw::factory()->create([
            'event_id' => $this->event->id,
            'category_event_id' => $category->id,
            'drawName' => $name,
        ]);
        $registrations = Registration::factory()->count($size)->create();
        foreach ($registrations as $registration) {
            $registration->players()->attach(Player::factory()->create());
            $registration->categoryEvents()->attach($category->id, [
                'status' => 'registered',
                'payment_status_id' => 1,
            ]);
        }
        $slots = [];
        $depth = (int) log($size, 2);
        foreach ($registrations as $index => $registration) {
            $path = strtr(str_pad(decbin($index), $depth, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = ['type' => 'player', 'id' => $registrations[$index]->id];
        }
        $service = app(FlexibleMonradService::class);
        $service->save($draw, ['size' => $size, 'mode' => 'custom_monrad', 'slots' => $slots], 0);
        $service->generate($draw, 1);

        return $draw->refresh();
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
    }
}
