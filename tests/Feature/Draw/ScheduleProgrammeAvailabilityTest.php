<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\ScheduleAvailability;
use App\Models\{Draw, Event, Fixture, OrderOfPlay, Venue};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleProgrammeAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_bounded_calendar_keeps_long_bookings_that_span_the_opening_time(): void
    {
        $venue = Venue::forceCreate(['name' => 'Shared programme courts']);
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-10-08 21:00:00', 'duration_minutes' => 720, 'gap_minutes' => 0]);
        $start = Carbon::parse('2026-10-09 08:00:00');
        $calendar = ScheduleAvailability::load([$venue->id], [], [], null, 0, [], null, null,
            $start, Carbon::parse('2026-10-11 19:00:00'));
        $this->assertSame('2026-10-09 09:00:00', $calendar->nextAvailableForMatch(
            $start, 30, 30, $venue->id, '1', [])->format('Y-m-d H:i:s'));
    }

    public function test_closing_buffer_keeps_future_bookings_that_overlap_a_match_gap(): void
    {
        $venue = Venue::forceCreate(['name' => 'Shared closing courts']);
        $draw = Draw::factory()->create();
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-10-11 18:15:00', 'duration_minutes' => 30, 'gap_minutes' => 0]);
        $calendar = ScheduleAvailability::load([$venue->id], [], [], null, 0, [], null, null,
            Carbon::parse('2026-10-09 08:00:00'), Carbon::parse('2026-10-11 19:00:00'));
        $this->assertSame('2026-10-11 18:45:00', $calendar->nextAvailableForMatch(
            Carbon::parse('2026-10-11 17:45:00'), 60, 30, $venue->id, '1', [])->format('Y-m-d H:i:s'));
    }

    public function test_expired_history_is_omitted_without_changing_current_court_availability(): void
    {
        $venue = Venue::forceCreate(['name' => 'Historic shared courts']);
        $draw = Draw::factory()->create();
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2020-01-01 08:00:00', 'duration_minutes' => 75, 'gap_minutes' => 0]);
        $start = Carbon::parse('2026-10-09 08:00:00');
        $bounded = ScheduleAvailability::load([$venue->id], [], [], null, 0, [], null, null,
            $start, Carbon::parse('2026-10-11 19:00:00'));
        $unbounded = ScheduleAvailability::load([$venue->id], [], [], null, 0);
        $this->assertNotSame($unbounded->fingerprint(), $bounded->fingerprint());
        $this->assertTrue($bounded->nextAvailableForMatch($start, 30, 30, $venue->id, '1', [])
            ->eq($unbounded->nextAvailableForMatch($start, 30, 30, $venue->id, '1', [])));
    }
}

