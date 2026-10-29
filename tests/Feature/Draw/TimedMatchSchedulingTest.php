<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Registration, User, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimedMatchSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function setupDraw(bool $timed = true, ?Event $event = null, ?Venue $venue = null): array
    {
        $event ??= Event::factory()->create(['eventType' => 6]);
        if (! $venue) {
            $venue = new Venue();
            $venue->forceFill(['name' => 'Timed court'])->save();
            $event->venues()->attach($venue->id, ['num_courts' => 1]);
        }
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'locked' => false]);
        $draw->venues()->attach($venue->id, ['num_courts' => 1]);
        $draw->settings()->create(['workflow' => 'round_robin', 'score_format' => $timed ? 'timed' : 'short_set_4',
            'match_duration_minutes' => $timed ? 20 : null, 'changeover_minutes' => $timed ? 5 : null]);
        return [$event, $venue, $draw];
    }

    private function fixture(Draw $draw, int $number = 1): Fixture
    {
        return Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'round' => 1,
            'match_nr' => $number, 'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id]);
    }

    private function schedulingOptions(string $end = '18:00:00'): array
    {
        return ['start' => '2026-09-10 08:00:00', 'end' => '2026-09-10 '.$end,
            'duration' => 60, 'court_gap' => 10, 'player_rest' => 0, 'wave_minutes' => 0];
    }

    public function test_timed_matches_fit_their_actual_window_and_persist_playing_time_and_changeover(): void
    {
        [$event, , $draw] = $this->setupDraw();
        $this->fixture($draw);
        $this->fixture($draw, 2);
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $this->schedulingOptions('08:45:00'));
        $this->assertSame([], $preview['unscheduled']);
        $this->assertSame(['2026-09-10 08:00:00', '2026-09-10 08:25:00'], array_column($preview['matches'], 'scheduled_at'));
        $this->assertSame([20, 20], array_column($preview['matches'], 'duration'));
        $this->assertSame([5, 5], array_column($preview['matches'], 'court_gap'));
        $this->assertSame(2, $scheduler->apply($event, $this->schedulingOptions('08:45:00'), $preview['revision'])['count']);
        $this->assertSame([20, 20], OrderOfPlay::orderBy('time')->pluck('duration_minutes')->map(fn ($value) => (int) $value)->all());
        $this->assertSame([5, 5], OrderOfPlay::orderBy('time')->pluck('gap_minutes')->map(fn ($value) => (int) $value)->all());
    }

    public function test_mixed_draws_reserve_each_matches_own_duration_and_gap_and_timing_changes_invalidate_preview(): void
    {
        [$event, $venue, $timed] = $this->setupDraw();
        [, , $standard] = $this->setupDraw(false, $event, $venue);
        $this->fixture($timed);
        $this->fixture($standard);
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $this->schedulingOptions());
        $rows = collect($preview['matches']);
        $this->assertSame([20, 5], [$rows->firstWhere('draw_id', $timed->id)['duration'], $rows->firstWhere('draw_id', $timed->id)['court_gap']]);
        $this->assertSame([60, 10], [$rows->firstWhere('draw_id', $standard->id)['duration'], $rows->firstWhere('draw_id', $standard->id)['court_gap']]);
        $first = $rows->first();
        $this->assertSame(Carbon::parse($first['scheduled_at'])->addMinutes($first['duration'] + $first['court_gap'])->toDateTimeString(), $rows->last()['scheduled_at']);
        $timed->settings->update(['match_duration_minutes' => 25]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fresh preview');
        $scheduler->apply($event, $this->schedulingOptions(), $preview['revision']);
    }

    public function test_existing_bookings_keep_saved_timing_until_explicitly_moved_and_manual_moves_use_canonical_timing(): void
    {
        [$event, $venue, $draw] = $this->setupDraw();
        $first = $this->fixture($draw);
        $second = $this->fixture($draw, 2);
        OrderOfPlay::create(['fixture_id' => $first->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-09-10 08:00:00', 'duration_minutes' => 30, 'gap_minutes' => 7]);
        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions());
        $this->assertSame([30, 7], [$preview['existing_matches'][0]['duration'], $preview['existing_matches'][0]['court_gap']]);
        $this->assertSame('2026-09-10 08:37:00', $preview['matches'][0]['scheduled_at']);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $data = ['fixture_id' => $second->id, 'venue_id' => $venue->id, 'court' => '1',
            'scheduled_at' => '2026-09-10 08:30:00', 'duration' => 75, 'court_gap' => 0, 'player_rest' => 0];
        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.manual-assignment', $event), $data)->assertUnprocessable();
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $second->id]);
        $data['scheduled_at'] = '2026-09-10 08:37:00';
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event), $data)->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $second->id, 'duration_minutes' => 20, 'gap_minutes' => 5]);
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $first->id, 'duration_minutes' => 30, 'gap_minutes' => 7]);

        $draw->settings->update(['match_duration_minutes' => 5]);
        $data = array_replace($data, ['scheduled_at' => '2026-09-10 09:00:00', 'duration' => 5, 'court_gap' => 60]);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event), $data)->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $second->id, 'duration_minutes' => 5, 'gap_minutes' => 5]);
        [, , $standard] = $this->setupDraw(false, $event, $venue);
        $standardFixture = $this->fixture($standard);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event),
            array_replace($data, ['fixture_id' => $standardFixture->id]))->assertUnprocessable();
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $standardFixture->id]);
    }

    public function test_legacy_auto_scheduler_uses_saved_timed_rules_instead_of_submitted_estimates(): void
    {
        [, $venue, $draw] = $this->setupDraw();
        $this->fixture($draw);
        $this->fixture($draw, 2);
        app(\App\Services\ScheduleEngine::class)->autoSchedule($draw->id, 75,
            [$venue->id => ['courts' => ['1']]], '2026-09-10 08:00:00', null, 10);
        $slots = OrderOfPlay::where('draw_id', $draw->id)->orderBy('time')->get();
        $this->assertSame(['2026-09-10 08:00:00', '2026-09-10 08:25:00'], $slots->pluck('time')->all());
        $this->assertSame([20, 20], $slots->pluck('duration_minutes')->map(fn ($value) => (int) $value)->all());
        $this->assertSame([5, 5], $slots->pluck('gap_minutes')->map(fn ($value) => (int) $value)->all());
    }
}
