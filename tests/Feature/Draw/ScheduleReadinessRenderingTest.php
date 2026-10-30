<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Registration, User, Venue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleReadinessRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_reports_unprepared_playoffs_even_when_existing_matches_are_scheduled_without_writing(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 6, 'name' => 'Fictional schedule readiness event']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Fictional Orange draw', 'published' => false, 'oop_published' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'playoff_config' => [
            ['name' => 'Main playoff', 'slug' => 'main', 'size' => 2, 'positions' => [1], 'enabled' => true],
            ['name' => 'Placement playoff', 'slug' => 'plate', 'size' => 2, 'positions' => [2], 'enabled' => true],
        ]]);
        $venue = Venue::forceCreate(['name' => 'Fictional school courts']);
        foreach (['A', 'B'] as $index => $name) {
            $group = $draw->groups()->create(['name' => $name]);
            $players = Registration::factory()->count(2)->create();
            $group->registrations()->sync($players->modelKeys());
            $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id,
                'stage' => 'RR', 'match_status' => 0, 'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id]);
            OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                'court' => (string) ($index + 1), 'time' => '2026-10-31 08:00:00', 'duration_minutes' => 20, 'gap_minutes' => 5]);
        }
        $fixtureCount = Fixture::count();
        $bookingCount = OrderOfPlay::count();
        $auditCount = DB::table('draw_audit_logs')->count();
        $response = $this->actingAs($user)->get(route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'draw_id' => $draw->id]));
        $response->assertOk()->assertSee('0 existing matches without a day or time')
            ->assertSee('Playoff preparation is still needed.')
            ->assertSee('2 configured playoff stage(s) have no fixtures.')
            ->assertSee('Prepare playoffs in scheduling');
        $this->assertSame($fixtureCount, Fixture::count());
        $this->assertSame($bookingCount, OrderOfPlay::count());
        $this->assertSame($auditCount, DB::table('draw_audit_logs')->count());
        $this->assertFalse((bool) $draw->fresh()->published);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        if (getenv('CT_SCHEDULE_READINESS_HTML')) {
            file_put_contents(storage_path('framework/testing/schedule-readiness.html'), $response->getContent());
        }
    }
}
