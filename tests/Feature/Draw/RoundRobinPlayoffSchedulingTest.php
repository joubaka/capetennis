<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, DrawGroup, Event, Fixture, OrderOfPlay, Registration, User, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use App\Domain\Draws\Services\ScheduleAvailability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoundRobinPlayoffSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $event = Event::factory()->create(['start_date' => '2026-10-30', 'end_date' => '2026-10-31']);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Playoff scheduling']);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'playoff_config' => [
            ['slug' => 'main', 'name' => 'Main', 'size' => 4, 'positions' => [1, 2], 'enabled' => true],
            ['slug' => 'plate', 'name' => 'Plate', 'size' => 4, 'positions' => [3, 4], 'enabled' => true],
        ]]);
        $venue = Venue::forceCreate(['name' => 'Playoff courts']);
        $event->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw->venues()->attach($venue->id, ['num_courts' => 4]);
        foreach (['A', 'B'] as $name) {
            $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => $name]);
            $registrations = Registration::factory()->count(4)->create();
            $group->registrations()->attach($registrations->pluck('id')->all());
            foreach ([[0, 1], [2, 3], [0, 2], [1, 3], [0, 3], [1, 2]] as $index => [$one, $two]) {
                Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR',
                    'round' => intdiv($index, 2) + 1, 'match_nr' => $draw->drawFixtures()->count() + 1,
                    'registration1_id' => $registrations[$one]->id, 'registration2_id' => $registrations[$two]->id]);
            }
        }
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        return [$event, $draw, $venue];
    }

    public function test_read_only_page_then_explicit_idempotent_preparation_exposes_playoff_rounds(): void
    {
        [$event, $draw] = $this->scenario();
        $url = route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => [$draw->id]]);
        $this->get($url)->assertOk()->assertSee('Prepare playoff fixtures');
        $this->assertSame(12, $draw->drawFixtures()->count());
        $post = route('backend.event-venue-schedule.prepare-playoffs', $event);
        $this->post($post, ['draw_ids' => [$draw->id]])->assertRedirect($url);
        $ids = $draw->drawFixtures()->pluck('id')->all();
        $this->assertCount(20, $ids);
        $this->post($post, ['draw_ids' => [$draw->id]])->assertRedirect();
        $this->assertSame($ids, $draw->drawFixtures()->pluck('id')->all());
        $service = app(EventVenueScheduleService::class);
        $this->assertSame([1, 2, 3, 4, 5], $service->availableDrawRounds(collect([$draw->fresh()]), true)[$draw->id]);
        $details = $service->dayRoundDetails($draw->fresh());
        $this->assertSame([1, 2, 3, 4, 5], array_keys($details));
        $this->assertSame(4, collect($details[4])->sum('count'));
        $this->assertSame(4, collect($details[5])->sum('count'));
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertSame(0, $draw->drawFixtures()->where('stage', '!=', 'RR')->whereNotNull('registration1_id')->count());
    }

    public function test_prepared_unresolved_playoffs_can_be_selected_previewed_and_saved_after_group_feeders(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $this->post(route('backend.event-venue-schedule.prepare-playoffs', $event), ['draw_ids' => [$draw->id]])->assertRedirect();
        $service = app(EventVenueScheduleService::class);
        $options = ['day_batch' => true, 'full_day_scope' => '2026-10-30', 'start' => '2026-10-30 08:00', 'end' => '2026-10-30 22:00',
            'draw_ids' => [$draw->id], 'draw_order' => [$draw->id], 'draw_order_mode' => 'mixed',
            'draw_rounds' => [['draw_id' => $draw->id, 'rounds' => [1, 2, 3, 4, 5]]],
            'selected_fixture_keys' => $draw->drawFixtures()->pluck('id')->map(fn ($id) => 'individual:'.$id)->all(),
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => ['1', '2', '3', '4']],
            'duration' => 30, 'player_rest' => 15, 'court_gap' => 0];
        $preview = $service->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount(20, $preview['matches']);
        $matches = collect($preview['matches']);
        $this->assertGreaterThanOrEqual(Carbon::parse($matches->where('stage', 'RR')->max('scheduled_at'))->addMinutes(45)->toDateTimeString(), $matches->where('round', 4)->min('scheduled_at'));
        $this->assertStringContainsString('Group', implode(' ', $matches->firstWhere('round', 4)['participants']));
        $service->apply($event, $options, $preview['revision']);
        $this->assertDatabaseCount('order_of_plays', 20);
        $this->assertSame(0, $draw->drawFixtures()->where('stage', '!=', 'RR')->whereNotNull('registration1_id')->count());
    }

    public function test_preparation_rejects_foreign_draws_and_unauthorized_actor_and_protected_draws(): void
    {
        [$event, $draw] = $this->scenario();
        $url = route('backend.event-venue-schedule.prepare-playoffs', $event);
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $this->post($url, ['draw_ids' => [$draw->id, $foreign->id]])->assertStatus(422);
        foreach (['locked', 'published'] as $protected) {
            $draw->update([$protected => true]);
            $this->post($url, ['draw_ids' => [$draw->id]])->assertSessionHasErrors('playoffs');
            $this->assertSame(12, $draw->drawFixtures()->count());
            $draw->update([$protected => false]);
        }
        $this->actingAs(User::factory()->create())->post($url, ['draw_ids' => [$draw->id]])->assertForbidden();
        $this->assertSame(12, $draw->drawFixtures()->count());
    }

    public function test_saved_unresolved_playoff_reserves_possible_players_at_other_venues(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $this->post(route('backend.event-venue-schedule.prepare-playoffs', $event), ['draw_ids' => [$draw->id]])->assertRedirect();
        $playoff = $draw->drawFixtures()->whereNotNull('registration1_source_group_id')->firstOrFail();
        $registration = DrawGroup::findOrFail($playoff->registration1_source_group_id)->groupRegistrations->first()->registration_id;
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $playoff->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-10-30 10:00', 'duration_minutes' => 30, 'gap_minutes' => 0]);
        $otherVenue = Venue::forceCreate(['name' => 'Other courts']);
        $otherDraw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $calendar = ScheduleAvailability::load([$otherVenue->id], [$registration], [], $otherDraw, 15);
        $this->assertSame('2026-10-30 10:45:00', $calendar->nextAvailableForMatch(
            Carbon::parse('2026-10-30 10:00'), 30, 45, $otherVenue->id, '1', [$registration]
        )->toDateTimeString());
    }

    public function test_round_robin_players_cannot_overlap_even_in_the_same_round(): void
    {
        [$event, $draw] = $this->scenario();
        $fixtures = $draw->drawFixtures()->where('round', 1)->orderBy('id')->take(2)->get();
        $fixtures[1]->update(['registration1_id' => $fixtures[0]->registration1_id]);
        $preview = app(EventVenueScheduleService::class)->preview($event, [
            'start' => '2026-10-30 08:00', 'end' => '2026-10-30 22:00', 'duration' => 30,
            'player_rest' => 15, 'court_gap' => 0, 'draw_ids' => [$draw->id],
        ]);
        $matches = collect($preview['matches'])->keyBy('fixture_id');
        $this->assertGreaterThanOrEqual(Carbon::parse($matches[$fixtures[0]->id]['scheduled_at'])->addMinutes(45)->toDateTimeString(), $matches[$fixtures[1]->id]['scheduled_at']);
    }
}
