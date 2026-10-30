<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, DrawAuditLog, Event, Fixture, OrderOfPlay, Registration, User, Venue};
use App\Services\Draw\GroupAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoundRobinRebuildSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false, 'oop_published' => false]);
        $group = $draw->groups()->create(['name' => 'A']);
        $registrations = Registration::factory()->count(2)->create();
        $group->registrations()->sync($registrations->modelKeys());
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'match_status' => 0,
            'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id]);
        $venue = Venue::forceCreate(['name' => 'Rebuild regression courts']);
        $booking = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-10-31 08:00:00', 'duration_minutes' => 20, 'gap_minutes' => 5]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        $this->actingAs($user);
        return [$draw, $fixture, $booking];
    }

    public function test_endpoint_releases_old_bookings_preserves_other_draw_and_records_full_before_snapshot(): void
    {
        [$draw, $fixture, $booking] = $this->scenario();
        $foreign = Fixture::factory()->create(['draw_id' => Draw::factory()->create()->id, 'stage' => 'RR', 'match_status' => 0]);
        $otherBooking = OrderOfPlay::create(['fixture_id' => $foreign->id, 'draw_id' => $foreign->draw_id,
            'venue_id' => $booking->venue_id, 'court' => '2', 'time' => $booking->time]);
        DB::table('schedules')->insert(['fixture_id' => $fixture->id, 'draw_id' => $draw->id,
            'venue_id' => $booking->venue_id, 'time' => $booking->time]);
        $this->postJson(route('backend.draw.regenerate-rr', $draw), [
            'revision' => app(GroupAssignmentService::class)->revision($draw),
        ])->assertOk();
        $this->assertDatabaseMissing('fixtures', ['id' => $fixture->id]);
        $this->assertDatabaseMissing('order_of_plays', ['id' => $booking->id]);
        $this->assertDatabaseMissing('schedules', ['fixture_id' => $fixture->id]);
        $this->assertDatabaseHas('order_of_plays', ['id' => $otherBooking->id]);
        $this->assertSame(1, $draw->drawFixtures()->count());
        $this->assertSame(2, $draw->groups()->first()->registrations()->count());
        $audit = DrawAuditLog::where('draw_id', $draw->id)->where('action', 'rr_regenerated')->sole();
        $this->assertSame($fixture->registration1_id, $audit->payload['before']['fixtures'][0]['registration1_id']);
        $this->assertSame(1, $audit->payload['released_bookings']);
        $this->assertSame(1, $audit->payload['released_legacy_schedules']);
        $this->assertSame(5, $audit->payload['before']['bookings']['order_of_plays'][0]['gap_minutes']);
    }

    public function test_stale_revision_and_manual_walkover_cannot_destroy_booked_fixtures(): void
    {
        [$draw, $fixture, $booking] = $this->scenario();
        $this->postJson(route('backend.draw.regenerate-rr', $draw), ['revision' => 'stale'])->assertUnprocessable();
        $fixture->update(['match_status' => 3, 'winner_registration' => $fixture->registration1_id]);
        $this->postJson(route('backend.draw.regenerate-rr', $draw))->assertUnprocessable()->assertJsonPath('scored_fixtures', 1);
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
        $this->assertDatabaseHas('order_of_plays', ['id' => $booking->id]);
        $this->assertDatabaseMissing('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'rr_regenerated']);
    }

    public function test_audit_failure_rolls_back_fixture_and_booking_replacement(): void
    {
        [$draw, $fixture, $booking] = $this->scenario();
        $reject = true;
        DrawAuditLog::creating(function ($audit) use (&$reject) {
            if ($reject && $audit->action === 'rr_regenerated') throw new \RuntimeException('Synthetic audit failure');
        });
        try {
            $this->postJson(route('backend.draw.regenerate-rr', $draw))->assertUnprocessable();
        } finally {
            $reject = false;
        }
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
        $this->assertDatabaseHas('order_of_plays', ['id' => $booking->id]);
        $this->assertSame(1, $draw->drawFixtures()->count());
    }

    public function test_published_times_and_retirement_status_remain_protected(): void
    {
        [$draw, $fixture, $booking] = $this->scenario();
        DB::table('published_schedule_assignments')->insert(['event_id' => $draw->event_id, 'draw_id' => $draw->id,
            'fixture_kind' => 'individual', 'fixture_id' => $fixture->id, 'scheduled_at' => '2026-10-31 08:00:00',
            'duration' => 20, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson(route('backend.draw.regenerate-rr', $draw))->assertUnprocessable();
        $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $fixture->id]);
        $this->assertDatabaseHas('order_of_plays', ['id' => $booking->id]);
        DB::table('published_schedule_assignments')->where('fixture_id', $fixture->id)->delete();
        $fixture->update(['match_status' => 4]);
        $this->postJson(route('backend.draw.regenerate-rr', $draw))->assertUnprocessable();
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id, 'match_status' => 4]);
        $this->assertDatabaseHas('order_of_plays', ['id' => $booking->id]);
    }
}
