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
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registration->id]);
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
