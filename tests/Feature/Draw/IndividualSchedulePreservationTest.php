<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, FixtureResult, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\ScheduleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IndividualSchedulePreservationTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
        $venue = Venue::forceCreate(['name' => 'Synthetic courts']);
        $draw->venues()->attach($venue->id, ['num_courts' => 1]);
        $registrations = Registration::factory()->count(4)->create();
        $fixtures = collect([0, 2])->map(fn ($offset) => Fixture::factory()->create([
            'draw_id' => $draw->id, 'stage' => 'RR', 'round' => 1, 'scheduled' => 1,
            'registration1_id' => $registrations[$offset]->id,
            'registration2_id' => $registrations[$offset + 1]->id,
        ]));
        foreach ($fixtures as $index => $fixture) {
            OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id,
                'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-25 '.($index ? '10' : '08').':00:00',
                'duration_minutes' => 90, 'gap_minutes' => 0]);
        }
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        return [$draw, $venue, $fixtures];
    }

    public function test_auto_scheduling_preserves_results_and_their_court_reservation(): void
    {
        [$draw, $venue, $fixtures] = $this->scenario();
        $result = FixtureResult::factory()->create(['fixture_id' => $fixtures[0]->id]);
        $before = $fixtures[0]->orderOfPlay->getAttributes();
        $this->postJson(route('backend.individual-schedule.auto', $draw), [
            'start' => '2026-10-25 08:00:00', 'duration' => 60,
        ])->assertOk();
        $this->assertSame($before, $fixtures[0]->fresh()->orderOfPlay->getAttributes());
        $this->assertSame('2026-10-25 09:30:00', $fixtures[1]->fresh()->orderOfPlay->time);
        $this->assertDatabaseHas('fixture_results', ['id' => $result->id]);
        $this->assertDatabaseCount('order_of_plays', 2);
    }

    public function test_auto_scheduling_preserves_partial_play_without_results(): void
    {
        [$draw, $venue, $fixtures] = $this->scenario();
        $fixtures[0]->update(['match_status' => 2]);
        $before = $fixtures[0]->orderOfPlay->getAttributes();
        app(ScheduleEngine::class)->autoSchedule($draw->id, 60, [$venue->id => ['courts' => ['1']]], '2026-10-25 08:00:00');
        $this->assertSame($before, $fixtures[0]->fresh()->orderOfPlay->getAttributes());
        $this->assertSame('2026-10-25 09:30:00', $fixtures[1]->fresh()->orderOfPlay->time);
    }

    public function test_clear_and_trials_reset_preserve_all_bookings_when_any_match_has_play(): void
    {
        [$draw, , $fixtures] = $this->scenario();
        $fixtures[0]->update(['match_status' => 2]);
        foreach (['backend.individual-schedule.clear', 'backend.trials.reset'] as $name) {
            $this->json($name === 'backend.trials.reset' ? 'DELETE' : 'POST', route($name, $draw))->assertUnprocessable();
            $this->assertSame(2, OrderOfPlay::where('draw_id', $draw->id)->count());
            $this->assertSame(2, $draw->drawFixtures()->where('scheduled', 1)->count());
        }
    }

    public function test_clear_uses_fixture_ownership_and_preserves_other_events(): void
    {
        [$draw, $venue, $fixtures] = $this->scenario();
        // Older Trials bookings do not always carry draw_id.
        $fixtures[0]->orderOfPlay->update(['draw_id' => null]);
        $otherDraw = Draw::factory()->create();
        $otherFixture = Fixture::factory()->create(['draw_id' => $otherDraw->id, 'scheduled' => 1]);
        $otherSlot = OrderOfPlay::create(['draw_id' => $otherDraw->id, 'fixture_id' => $otherFixture->id,
            'time' => '2026-10-25 08:00:00', 'court' => '1', 'venue_id' => $venue->id]);
        $this->postJson(route('backend.individual-schedule.clear', $draw))->assertOk();
        $this->assertSame(0, OrderOfPlay::whereIn('fixture_id', $fixtures->pluck('id'))->count());
        $this->assertSame(0, $draw->drawFixtures()->where('scheduled', 1)->count());
        $this->assertDatabaseHas('order_of_plays', ['id' => $otherSlot->id]);
        $this->postJson(route('backend.individual-schedule.clear', $otherDraw))->assertForbidden();
    }

    public function test_audit_detects_actual_duration_and_normalized_court_conflicts(): void
    {
        [$draw, , $fixtures] = $this->scenario();
        $player = Player::factory()->create();
        $fixtures[0]->registration1->players()->attach($player->id);
        $fixtures[1]->registration1->players()->attach($player->id);
        $fixtures[1]->orderOfPlay->update(['time' => '2026-10-25 09:00:00', 'court' => 'Court 1']);
        $this->getJson(route('backend.individual-schedule.audit', $draw))->assertOk()
            ->assertJsonCount(1, 'conflicts')->assertJsonCount(1, 'player_conflicts');
    }
}
