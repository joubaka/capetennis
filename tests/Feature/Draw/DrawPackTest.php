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
