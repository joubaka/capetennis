<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Player, Registration, TeamFixture, Venue};
use App\Services\MyTennisService;
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicScheduleProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_working_rows_use_canonical_court_order_from_their_own_schedule(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $venue = Venue::forceCreate(['name' => 'Order courts']);
        $fixtures = collect(['Court 10', 'Court 2'])->map(function ($court) use ($draw, $venue) {
            $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
            OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
                'court' => $court, 'time' => '2026-10-09 09:00:00']);
            return $fixture;
        });
        $service = app(SchedulePublicationService::class);
        $service->publish($event, ['draw_id' => $draw->id]);
        $expected = [$fixtures[1]->id, $fixtures[0]->id];
        $this->assertSame($expected, $service->workingRows($event)->pluck('fixture_id')->all());
        $fixtures[1]->orderOfPlay()->update(['time' => '2026-10-10 15:00:00', 'court' => 'Court 99']);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertSame($expected, $service->publishedRows($event)->pluck('fixture_id')->all());
        $this->assertSame(array_reverse($expected), $service->workingRows($event)->pluck('fixture_id')->all());
    }

    public function test_team_snapshot_time_and_rank_order_survives_private_schedule_changes(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id, 'published' => true]);
        $venue = Venue::forceCreate(['name' => 'Rank snapshot venue']);
        $rows = collect([6, 5])->map(fn ($rank, $index) => TeamFixture::create([
            'draw_id' => $draws[$index]->id, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1,
            'fixture_type' => 1, 'home_rank_nr' => $rank, 'scheduled_at' => '2026-10-09 09:00:00',
            'venue_id' => $venue->id, 'court_label' => $rank === 6 ? 'Court 1' : 'Court 99',
        ]));
        $service = app(SchedulePublicationService::class);
        $service->publish($event, []);
        $rows[0]->update(['scheduled_at' => '2026-10-08 07:00:00', 'court_label' => 'Private court']);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $public = $service->publishedRows($event);
        $this->assertSame([$rows[1]->id, $rows[0]->id], $public->pluck('fixture_id')->all());
        $this->assertSame([5, 6], $public->pluck('rank')->all());
        $this->assertSame(['2026-10-09 09:00:00'], $public->pluck('scheduled_at')->unique()->all());
        $this->assertSame(['Court 99', 'Court 1'], $public->pluck('court')->all());
        $order = app(\App\Services\Scheduling\VenueMatchOrder::class);
        $this->assertSame($public->pluck('fixture_id')->all(), $public->reverse()->sort(fn ($a, $b) => $order->compare($a, $b))->pluck('fixture_id')->all());
        $this->assertSame([$rows[0]->id, $rows[1]->id], $service->workingRows($event)->pluck('fixture_id')->all());
    }

    public function test_draw_badge_days_are_chronological_unique_public_snapshot_days_only(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $venue = Venue::forceCreate(['name' => 'Public courts']);
        $event->venues()->attach($venue->id, ['num_courts' => 2]);
        $fixtures = collect(['2026-10-10 09:00:00', '2026-10-09 10:00:00', '2026-10-09 08:00:00'])->map(fn ($time, $index) => TeamFixture::create([
            'draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => $index + 1, 'fixture_type' => 1,
            'scheduled_at' => $time, 'venue_id' => $venue->id, 'court_label' => '1',
        ]));
        $service = app(SchedulePublicationService::class);
        $service->publish($event, ['draw_id' => $draw->id]);
        $fixtures->first()->update(['scheduled_at' => '2026-10-11 09:00:00']);
        $foreignEvent = Event::factory()->create(['eventType' => 3]);
        $foreignDraw = Draw::factory()->create(['event_id' => $foreignEvent->id, 'published' => true]);
        TeamFixture::create(['draw_id' => $foreignDraw->id, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1,
            'fixture_type' => 1, 'scheduled_at' => '2026-10-12 09:00:00', 'venue_id' => $venue->id, 'court_label' => '2']);
        $service->publish($foreignEvent, ['draw_id' => $foreignDraw->id]);
        $this->get(route('events.show', $event))->assertOk()->assertSee('Times available · Friday, Saturday')->assertDontSee('Times available · Sunday')->assertDontSee('Monday');
        $this->assertSame('Friday, Saturday', $service->publicDrawDayLabels($event)->get($draw->id));
        $service->hide($event, ['date' => '2026-10-09']);
        $this->get(route('events.show', $event))->assertOk()->assertSee('Times available · Saturday')->assertDontSee('Times available · Friday');
        $service->hide($event, ['draw_id' => $draw->id]);
        $this->get(route('events.show', $event))->assertOk()->assertDontSee('Times available');
    }

    public function test_public_draw_list_distinguishes_unscheduled_partial_and_complete_working_schedules_without_times(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $venue = new Venue();
        $venue->forceFill(['name' => 'Unpublished Timing Venue'])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 1]);
        $draws = [];
        foreach (['none' => [null], 'partial' => [null, '2026-10-09 13:45:00'], 'complete' => ['2026-10-09 13:45:00']] as $state => $times) {
            $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'drawName' => $state.' draw']);
            $draws[$state] = $draw;
            foreach ($times as $index => $time) {
                TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
                    'match_nr' => $index + 1, 'fixture_type' => 1, 'scheduled_at' => $time,
                    'venue_id' => $time ? $venue->id : null, 'court_label' => $time ? '1' : null]);
            }
        }
        $response = $this->get(route('events.show', $event))->assertOk()
            ->assertSee('Not scheduled yet')->assertSee('Partly scheduled · times not published')
            ->assertSee('Scheduled · times not published')->assertDontSee('13:45');
        $this->assertSame([0, 1, 1], $response->viewData('eventDraws')->pluck('scheduled_team_match_count')->map(fn ($count) => (int) $count)->sort()->values()->all());
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draws['complete']->id]);
        $this->get(route('events.show', $event))->assertOk()->assertSee('Times available');
    }

    public function test_admin_public_draw_cannot_show_working_times_when_only_the_draw_is_published(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $admin = \App\Models\User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => true]);
        $venue = new Venue();
        $venue->forceFill(['name' => 'Private Planning Courts'])->save();
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'match_nr' => 1, 'fixture_type' => 1, 'scheduled_at' => '2026-10-09 13:45:00',
            'venue_id' => $venue->id, 'court_label' => 'Private Court']);
        $this->assertFalse($draw->scheduleIsPublished());
        $this->actingAs($admin);
        $this->get(route('events.show', $event))->assertOk()
            ->assertViewHas('fixturesByDay', fn ($days) => $days->isEmpty())
            ->assertViewHas('drawPublicationSummary', fn ($summary) => $summary['schedule_published'] === 0);
        foreach (['frontend.fixtures.index', 'frontend.fixtures.show'] as $route) {
            $this->get(route($route, $draw))->assertOk()->assertSee('Match times to follow')
                ->assertDontSee('13:45')->assertDontSee('Private Planning Courts')->assertDontSee('Private Court')
                ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->first()->scheduled_at === null);
        }
        $this->assertSame('2026-10-09 13:45:00', $fixture->fresh()->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $fixture->update(['scheduled_at' => '2026-10-09 16:15:00']);
        $this->assertTrue($draw->fresh()->scheduleIsPublished());
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()->assertSee('13:45')->assertDontSee('16:15');
        app(SchedulePublicationService::class)->hide($event, ['draw_id' => $draw->id]);
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()->assertSee('Match times to follow')
            ->assertDontSee('13:45')->assertDontSee('16:15');
        $this->get(route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw->id]))->assertOk()
            ->assertSee('16:15');
    }

    public function test_public_venue_and_day_lists_use_published_times_after_a_private_cross_day_move(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $venue = new Venue();
        $venue->forceFill(['name' => 'Friday town'])->save();
        $otherVenue = new Venue();
        $otherVenue->forceFill(['name' => 'Sunday town'])->save();
        $event->venues()->attach([$venue->id => ['num_courts' => 1], $otherVenue->id => ['num_courts' => 1]]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0, 'scheduled_at' => '2026-10-09 08:00:00',
            'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60]);
        app(SchedulePublicationService::class)->publish($event, ['date' => '2026-10-09']);
        $fixture->update(['scheduled_at' => '2026-10-11 10:00:00', 'venue_id' => $otherVenue->id]);

        $this->get(route('fixtures.order', [$event->id, $venue->id, '2026-10-09']))
            ->assertOk()->assertViewHas('fixtures', fn ($fixtures) => $fixtures->count() === 1
                && $fixtures->first()->scheduled_at->format('Y-m-d H:i:s') === '2026-10-09 08:00:00')
            ->assertViewHas('availableDates', fn ($dates) => $dates->all() === ['2026-10-09']);
        $this->get(route('fixtures.order', [$event->id, $otherVenue->id, 'all']))
            ->assertOk()->assertViewHas('fixtures', fn ($fixtures) => $fixtures->isEmpty());
        $this->get(route('events.show', $event))->assertOk()
            ->assertViewHas('fixturesByDay', fn ($days) => $days->keys()->all() === ['2026-10-09']);
        $this->assertSame('2026-10-11 10:00:00', $fixture->fresh()->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_my_tennis_keeps_the_public_match_when_working_assignment_is_cleared(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-05 09:00:00'));
        $event = Event::factory()->create(['end_date' => '2026-10-11']);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $player = Player::factory()->create();
        $registration = Registration::factory()->create();
        $registration->players()->attach($player->id);
        $opponent = Registration::factory()->create();
        $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw->update(['category_event_id' => $category->id]);
        foreach ([$registration, $opponent] as $entry) {
            \App\Models\CategoryEventRegistration::factory()->create([
                'category_event_id' => $category->id, 'registration_id' => $entry->id,
            ]);
        }
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registration->id,
            'registration2_id' => $opponent->id]);
        $venue = new Venue();
        $venue->forceFill(['name' => 'Published town'])->save();
        $slot = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id,
            'time' => '2026-10-09 08:00:00', 'venue_id' => $venue->id, 'court' => '1']);
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $slot->delete();

        $match = app(MyTennisService::class)->nextScheduledMatchFor($player)->sole();
        $this->assertSame($fixture->id, $match->id);
        $this->assertSame('2026-10-09 08:00:00', (string) $match->orderOfPlay->time);
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $fixture->id]);
    }
}
