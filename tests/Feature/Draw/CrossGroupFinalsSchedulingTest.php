<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\ScheduleAvailability;
use App\Models\{Draw, DrawGroup, Event, Fixture, OrderOfPlay, Player, Registration, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossGroupFinalsSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_cross_group_finals_share_four_courts_after_qualifying_and_rest(): void
    {
        $event = Event::factory()->create(['eventType' => 6, 'start_date' => '2026-09-10', 'end_date' => '2026-09-10']);
        $venue = Venue::forceCreate(['name' => 'Four available courts']);
        $event->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
        $draw->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'score_format' => 'timed', 'match_duration_minutes' => 30, 'changeover_minutes' => 0]);
        $groups = collect(['A', 'B'])->map(function ($name) use ($draw) {
            $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => $name, 'round_robin_phase' => 1]);
            $group->registrations()->attach(Registration::factory()->count(4)->create()->pluck('id'));
            return $group;
        });
        app(\App\Domain\Draws\Services\RoundRobinGenerationService::class)->generate($draw);
        $finals = collect(range(1, 4))->map(fn ($position) => Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 4, 'match_nr' => 1000 + $position,
            'registration1_id' => null, 'registration2_id' => null, 'winner_registration' => null, 'match_status' => 0,
            'registration1_source_group_id' => $groups[0]->id, 'registration2_source_group_id' => $groups[1]->id,
            'registration1_source_position' => $position, 'registration2_source_position' => $position,
        ]));
        $fixtures = $draw->drawFixtures()->get();
        $options = ['start' => '2026-09-10 08:00:00', 'end' => '2026-09-10 18:00:00', 'duration' => 30,
            'court_gap' => 0, 'player_rest' => 15, 'day_batch' => true, 'full_day_scope' => '2026-09-10',
            'draw_ids' => [$draw->id], 'draw_order' => [$draw->id], 'draw_order_mode' => 'mixed',
            'selected_fixture_keys' => $fixtures->map(fn ($fixture) => 'individual:'.$fixture->id)->all(),
            'draw_rounds' => [['draw_id' => $draw->id, 'rounds' => [1, 2, 3, 4]]],
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => ['1', '2', '3', '4']]];
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $rows = collect($preview['matches']);
        $this->assertCount(16, $rows);
        $opening = $rows->where('scheduled_at', '2026-09-10 08:00:00');
        $this->assertCount(4, $opening);
        $this->assertCount(4, $opening->pluck('court')->unique());
        $finalRows = $rows->whereIn('fixture_id', $finals->pluck('id'));
        $this->assertCount(4, $finalRows);
        $this->assertCount(1, $finalRows->pluck('scheduled_at')->unique());
        $this->assertCount(4, $finalRows->pluck('court')->unique());
        $this->assertGreaterThanOrEqual(\Carbon\Carbon::parse($rows->where('stage', 'RR')->max('scheduled_at'))->addMinutes(45)->toDateTimeString(), $finalRows->min('scheduled_at'));
        $this->assertSame(0, OrderOfPlay::count());
        $this->assertSame(16, $service->apply($event, $options, $preview['revision'])['count']);
        $this->assertSame(1, OrderOfPlay::whereIn('fixture_id', $finals->pluck('id'))->distinct()->count('time'));

        // Saved reservations use the same proof; overlapping ranks and real-player aliases cannot bypass rest.
        $first = $finals[0]; $second = $finals[1];
        $start = \Carbon\Carbon::parse('2026-09-10 16:00:00');
        $registrations = $groups->flatMap(fn ($group) => $group->groupRegistrations->pluck('registration_id'))->all();
        $savedTime = \Carbon\Carbon::parse($finalRows->firstWhere('fixture_id', $second->id)['scheduled_at']);
        $saved = ScheduleAvailability::load([$venue->id], $registrations, [$second->id], participantRest: 15);
        $this->assertEquals($savedTime, $saved->nextAvailableForMatch($savedTime, 30, 45, $venue->id, '5', $registrations, ScheduleAvailability::roundRobinPositionGroup($second)));
        $calendar = ScheduleAvailability::load([], $registrations);
        $calendar->reserveWithRest($venue->id, '1', $start, 30, 45, $registrations, ScheduleAvailability::roundRobinPositionGroup($first));
        $this->assertEquals($start, $calendar->nextAvailableForMatch($start, 30, 45, $venue->id, '2', $registrations, ScheduleAvailability::roundRobinPositionGroup($second)));
        $second->registration1_source_position = 1;
        $this->assertEquals($start->copy()->addMinutes(45), $calendar->nextAvailableForMatch($start, 30, 45, $venue->id, '2', $registrations, ScheduleAvailability::roundRobinPositionGroup($second)));
        $second->registration1_source_position = 2;
        $second->registration2_source_position = 5;
        $this->assertNull(ScheduleAvailability::roundRobinPositionGroup($second));
        $second->registration2_source_position = 2;
        $foreign = DrawGroup::create(['draw_id' => Draw::factory()->create(['event_id' => Event::factory()->create()->id])->id, 'name' => 'Foreign']);
        $second->registration2_source_group_id = $foreign->id;
        $this->assertNull(ScheduleAvailability::roundRobinPositionGroup($second));
        $second->registration2_source_group_id = $groups[1]->id;
        $incoming = Fixture::factory()->create(['draw_id' => $draw->id, 'parent_fixture_id' => $second->id]);
        $this->assertNull(ScheduleAvailability::roundRobinPositionGroup($second));
        $incoming->update(['parent_fixture_id' => null]);
        $player = Player::factory()->create();
        Registration::find($registrations[0])->players()->attach($player->id);
        Registration::find($registrations[4])->players()->attach($player->id);
        $this->assertNull(ScheduleAvailability::roundRobinPositionGroup($second));
    }
}
