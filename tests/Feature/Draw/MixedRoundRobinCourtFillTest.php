<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, DrawGroup, Event, Fixture, OrderOfPlay, Registration, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MixedRoundRobinCourtFillTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_proven_disjoint_group_positions_can_share_a_time_and_saved_bookings_keep_the_proof(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $venue = Venue::forceCreate(['name' => 'Proof courts']);
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);
        $registrations = Registration::factory()->count(6)->create();
        $group->registrations()->attach($registrations->pluck('id'));
        $make = fn ($one, $two) => Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN',
            'registration1_id' => null, 'registration2_id' => null, 'registration1_source_group_id' => $group->id,
            'registration2_source_group_id' => $group->id, 'registration1_source_position' => $one,
            'registration2_source_position' => $two, 'winner_registration' => null]);
        $first = $make(1, 2); $other = $make(3, 4); $overlap = $make(2, 3);
        $availability = \App\Domain\Draws\Services\ScheduleAvailability::class;
        $firstToken = $availability::roundRobinPositionGroup($first);
        $otherToken = $availability::roundRobinPositionGroup($other);
        $this->assertNotNull($firstToken);
        $calendar = $availability::load([], $registrations->pluck('id')->all());
        $start = \Carbon\Carbon::parse('2026-09-10 08:00:00');
        $calendar->reserveWithRest($venue->id, '1', $start, 15, 30, $registrations->pluck('id')->all(), $firstToken);
        $this->assertEquals($start, $calendar->nextAvailableForMatch($start, 15, 30, $venue->id, '2', $registrations->pluck('id')->all(), $otherToken));
        $this->assertEquals($start->copy()->addMinutes(30), $calendar->nextAvailableForMatch($start, 15, 30, $venue->id, '2', $registrations->pluck('id')->all(), $availability::roundRobinPositionGroup($overlap)));
        $this->assertEquals($start->copy()->addMinutes(15), $calendar->nextAvailableForMatch($start, 15, 30, $venue->id, '1', $registrations->pluck('id')->all(), $otherToken));
        $this->assertEquals($start->copy()->addMinutes(30), $calendar->nextAvailableForMatch($start, 15, 30, $venue->id, '2', [$registrations[0]->id]));
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id]);
        OrderOfPlay::create(['fixture_id' => $first->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => $start, 'duration_minutes' => 15, 'gap_minutes' => 0]);
        $saved = $availability::load([$venue->id], $registrations->pluck('id')->all(), participantRest: 15);
        $this->assertEquals($start, $saved->nextAvailableForMatch($start, 15, 30, $venue->id, '2', $registrations->pluck('id')->all(), $otherToken));
        $this->assertEquals($start->copy()->addMinutes(30), $saved->nextAvailableForMatch($start, 15, 30, $venue->id, '2', $registrations->pluck('id')->all(), $firstToken));
        $this->assertEquals($start->copy()->addMinutes(30), $saved->nextAvailableForMatch($start, 15, 30, $venue->id, '2', $registrations->pluck('id')->all(), 'rr-position:99999:'.$group->id.':3,4'));
        $other->registration1_source_position = 7;
        $this->assertNull($availability::roundRobinPositionGroup($other));
        $other->registration1_source_position = 3;
        $incoming = Fixture::factory()->create(['draw_id' => $draw->id, 'parent_fixture_id' => $other->id]);
        $this->assertNull($availability::roundRobinPositionGroup($other));
        $incoming->update(['parent_fixture_id' => null]);
        $player = \App\Models\Player::factory()->create();
        $registrations[0]->players()->attach($player->id); $registrations[1]->players()->attach($player->id);
        $this->assertNull($availability::roundRobinPositionGroup($other));
    }

    public static function timings(): array
    {
        return ['continuous' => [0, false], 'required rest' => [15, false], 'fixed court booking' => [0, true], 'shared player across draws' => [15, false, true]];
    }

    #[DataProvider('timings')]
    public function test_mixed_draws_fill_four_courts_on_fifteen_minute_slots_without_player_clashes(int $rest, bool $fixed, bool $shared = false): void
    {
        $event = Event::factory()->create(['eventType' => 6, 'start_date' => '2026-09-10', 'end_date' => '2026-09-10']);
        $venue = Venue::forceCreate(['name' => 'Fictional high school']);
        $event->venues()->attach($venue->id, ['num_courts' => 4]);
        $draws = collect();
        foreach ([7, 6] as $size) {
            $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
            $draw->venues()->attach($venue->id, ['num_courts' => 4]);
            $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'score_format' => 'timed', 'match_duration_minutes' => 15, 'changeover_minutes' => 0]);
            $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A', 'round_robin_phase' => 1]);
            $group->registrations()->attach(Registration::factory()->count($size)->create()->pluck('id'));
            app(\App\Domain\Draws\Services\RoundRobinGenerationService::class)->generate($draw);
            foreach ([1, 3, 5] as $position) {
                Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => $size + 1,
                    'match_nr' => 1000 + $position, 'registration1_id' => null, 'registration2_id' => null,
                    'registration1_source_group_id' => $group->id, 'registration1_source_position' => $position,
                    'registration2_source_group_id' => $group->id, 'registration2_source_position' => $position + 1,
                    'winner_registration' => null, 'match_status' => 0]);
            }
            $draws->push($draw);
        }
        $fixtures = $draws->flatMap(fn ($draw) => $draw->drawFixtures()->get());
        $sharedRegistrations = [];
        if ($shared) {
            $player = \App\Models\Player::factory()->create();
            foreach ($draws as $draw) {
                $registration = Registration::findOrFail($fixtures->firstWhere('draw_id', $draw->id)->registration1_id);
                $registration->players()->attach($player->id);
                $sharedRegistrations[] = $registration->id;
            }
        }
        if ($fixed) {
            $otherDraw = Draw::factory()->create(['event_id' => $event->id]);
            $booking = Fixture::factory()->create(['draw_id' => $otherDraw->id]);
            OrderOfPlay::create(['fixture_id' => $booking->id, 'draw_id' => $otherDraw->id, 'venue_id' => $venue->id,
                'court' => '4', 'time' => '2026-09-10 08:00:00', 'duration_minutes' => 15, 'gap_minutes' => 0]);
        }
        $options = ['start' => '2026-09-10 08:00:00', 'end' => '2026-09-10 18:00:00', 'duration' => 15,
            'court_gap' => 0, 'player_rest' => $rest, 'day_batch' => true, 'full_day_scope' => '2026-09-10',
            'draw_ids' => $draws->pluck('id')->all(), 'draw_order' => $draws->pluck('id')->all(), 'draw_order_mode' => 'mixed',
            'selected_fixture_keys' => $fixtures->map(fn ($fixture) => 'individual:'.$fixture->id)->all(),
            'draw_rounds' => $draws->map(fn ($draw) => ['draw_id' => $draw->id, 'rounds' => $fixtures->where('draw_id', $draw->id)->pluck('round')->unique()->values()->all()])->all(),
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => ['1', '2', '3', '4']]];
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount(42, $preview['matches']);
        $waves = collect($preview['matches'])->groupBy('scheduled_at');
        foreach ($waves as $time => $matches) {
            $this->assertLessThanOrEqual(4, $matches->count(), $time);
            $this->assertCount($matches->count(), $matches->pluck('court')->unique());
            $players = $matches->flatMap(function ($row) use ($fixtures) {
                $fixture = $fixtures->firstWhere('id', $row['fixture_id']);
                return array_filter([$fixture->registration1_id, $fixture->registration2_id]);
            });
            $this->assertCount($players->count(), $players->unique());
        }
        if (! $rest && ! $fixed) {
            $this->assertSame(array_fill(0, 9, 4), $waves->take(9)->map->count()->values()->all());
            $this->assertSame(['2026-09-10 08:00:00', '2026-09-10 08:15:00', '2026-09-10 08:30:00'], $waves->keys()->take(3)->all());
        }
        foreach ($fixtures->where('stage', 'RR')->flatMap(fn ($fixture) => [$fixture->registration1_id, $fixture->registration2_id])->unique() as $registration) {
            $ids = $fixtures->filter(fn ($fixture) => in_array($registration, [$fixture->registration1_id, $fixture->registration2_id], true))->pluck('id');
            $times = collect($preview['matches'])->whereIn('fixture_id', $ids)->pluck('scheduled_at')->sort()->values();
            foreach ($times->slice(1) as $index => $time) $this->assertGreaterThanOrEqual(15 + $rest, \Carbon\Carbon::parse($times[$index - 1])->diffInMinutes($time));
        }
        if ($shared) {
            $ids = $fixtures->filter(fn ($fixture) => array_intersect($sharedRegistrations, [$fixture->registration1_id, $fixture->registration2_id]))->pluck('id');
            $times = collect($preview['matches'])->whereIn('fixture_id', $ids)->pluck('scheduled_at')->sort()->values();
            foreach ($times->slice(1) as $index => $time) $this->assertGreaterThanOrEqual(15 + $rest, \Carbon\Carbon::parse($times[$index - 1])->diffInMinutes($time));
            // An undecided finalist can still be the person entered in the other draw.
            $possible = collect($preview['matches'])->filter(fn ($row) => array_intersect($row['participant_ids'] ?? [], $sharedRegistrations));
            foreach ($possible->where('draw_id', $draws[0]->id) as $left) {
                foreach ($possible->where('draw_id', $draws[1]->id) as $right) {
                    $this->assertGreaterThanOrEqual(15 + $rest, \Carbon\Carbon::parse($left['scheduled_at'])->diffInMinutes($right['scheduled_at'], true));
                }
            }
        }
        $this->assertSame((int) $fixed, OrderOfPlay::count());
        foreach ($draws as $draw) {
            $rows = collect($preview['matches'])->where('draw_id', $draw->id);
            $finishedGroups = \Carbon\Carbon::parse($rows->where('stage', 'RR')->max('scheduled_at'))->addMinutes(15);
            $this->assertGreaterThanOrEqual($finishedGroups->toDateTimeString(), $rows->where('stage', 'MAIN')->min('scheduled_at'));
        }
        $this->assertSame(42, $service->apply($event, $options, $preview['revision'])['count']);
    }
}
