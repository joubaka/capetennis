<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, Player, Registration, User, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventVenueScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_it_mixes_draws_on_one_shared_physical_court_pool(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Eight Court Venue');
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        foreach ($draws as $draw) {
            $draw->venues()->attach($venue->id, ['num_courts' => 2]);
            foreach (range(1, 2) as $match) {
                Fixture::factory()->create([
                    'draw_id' => $draw->id, 'round' => 1, 'match_nr' => $match, 'bracket_id' => 1,
                    'registration1_id' => Registration::factory()->create()->id,
                    'registration2_id' => Registration::factory()->create()->id,
                ]);
            }
        }

        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions());

        $this->assertCount(4, $preview['matches']);
        $firstWave = collect($preview['matches'])->where('scheduled_at', '2026-09-10 08:00:00');
        $this->assertCount(2, $firstWave);
        $this->assertCount(2, $firstWave->pluck('draw_id')->unique(), 'The first courts should be shared fairly between draws.');
        $this->assertSame(['1', '2'], $firstWave->pluck('court')->sort()->values()->all());
        $this->assertSame([], $preview['unscheduled']);
    }

    public function test_two_consecutive_byes_keep_the_first_playable_match_in_the_third_wave(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Main Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($venue->id, ['num_courts' => 1]);
        $player = Registration::factory()->create();
        $opponent = Registration::factory()->create();

        $roundOneBye = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
            'registration1_id' => $player->id, 'registration2_id' => null, 'winner_registration' => $player->id,
        ]);
        $roundTwoBye = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 2, 'match_nr' => 2, 'bracket_id' => 1,
            'registration1_id' => $player->id, 'registration2_id' => null, 'winner_registration' => $player->id,
        ]);
        $firstPlayable = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 3, 'match_nr' => 3, 'bracket_id' => 1,
            'registration1_id' => $player->id, 'registration2_id' => $opponent->id,
        ]);
        $roundOneBye->update(['parent_fixture_id' => $roundTwoBye->id]);
        $roundTwoBye->update(['parent_fixture_id' => $firstPlayable->id]);

        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions());

        $this->assertSame(2, $preview['automatic_byes']);
        $this->assertCount(1, $preview['matches']);
        $this->assertSame(3, $preview['matches'][0]['wave']);
        $this->assertSame('2026-09-10 11:00:00', $preview['matches'][0]['scheduled_at']);
    }

    public function test_player_rest_is_protected_across_draws_and_different_venues(): void
    {
        $event = Event::factory()->create();
        $venues = collect([$this->venue($event, 'North'), $this->venue($event, 'South')]);
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        $player = Player::factory()->create();
        foreach ($draws as $index => $draw) {
            $draw->venues()->attach($venues[$index]->id, ['num_courts' => 1]);
            $sharedRegistration = Registration::factory()->create();
            $sharedRegistration->players()->attach($player->id);
            Fixture::factory()->create([
                'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
                'registration1_id' => $sharedRegistration->id,
                'registration2_id' => Registration::factory()->create()->id,
            ]);
        }

        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions());
        $times = collect($preview['matches'])->pluck('scheduled_at')->sort()->values()->all();

        $this->assertSame(['2026-09-10 08:00:00', '2026-09-10 10:15:00'], $times);
        $this->assertCount(2, collect($preview['matches'])->pluck('venue_id')->unique());
    }

    public function test_applying_requires_the_current_preview_revision(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Main Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($venue->id, ['num_courts' => 1]);
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
        ]);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $this->schedulingOptions());

        $result = $service->apply($event, $this->schedulingOptions(), $preview['revision']);

        $this->assertSame(1, $result['count']);
        $this->assertDatabaseHas('order_of_plays', [
            'fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-09-10 08:00:00',
        ]);
        $this->assertTrue((bool) $fixture->fresh()->scheduled);
    }

    public function test_event_admin_can_assign_several_draws_to_the_same_shared_venue(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Shared Venue');
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $response = $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.assignments', $event), [
            'venues' => [['id' => $venue->id, 'courts' => 8]],
            'assignments' => $draws->map(fn ($draw) => ['draw_id' => $draw->id, 'venue_ids' => [$venue->id]])->all(),
        ]);

        $response->assertOk();
        foreach ($draws as $draw) {
            $this->assertDatabaseHas('draw_venues', [
                'draw_id' => $draw->id, 'venue_id' => $venue->id, 'num_courts' => 8,
            ]);
        }
        $this->get(route('backend.event-venue-schedule.index', $event))
            ->assertOk()->assertSee('Schedule every assigned age group')->assertSee('Shared Venue');
    }

    public function test_an_admin_of_another_event_cannot_open_or_generate_the_schedule(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->get(route('backend.event-venue-schedule.index', $event))->assertForbidden();
        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.preview', $event), $this->schedulingOptions())
            ->assertForbidden();
    }

    private function schedulingOptions(): array
    {
        return [
            'start' => '2026-09-10 08:00:00', 'end' => '2026-09-10 18:00:00',
            'duration' => 75, 'wave_minutes' => 90, 'court_gap' => 0, 'player_rest' => 60,
        ];
    }

    private function venue(Event $event, string $name): Venue
    {
        $venue = new Venue();
        $venue->forceFill(['name' => $name, 'event_id' => $event->id])->save();
        return $venue;
    }
}
