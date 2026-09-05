<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FlexibleMonradDraw;
use App\Models\OrderOfPlay;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use App\Models\Venue;
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
}
