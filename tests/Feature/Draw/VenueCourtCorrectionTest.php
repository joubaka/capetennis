<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, FixtureResult, OrderOfPlay, Team, TeamFixture, TeamTie, User, Venue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VenueCourtCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function setupVenue(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create();
        $venue = new Venue();
        $venue->forceFill(['name' => 'Six Courts'])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 6]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => true, 'published' => true]);
        $draw->venues()->attach($venue->id, ['num_courts' => 6]);
        foreach (range(1, 6) as $label) DB::table('draw_venue_court_allocations')->insert([
            'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court_label' => (string) $label,
        ]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        return [$event, $venue, $draw];
    }

    private function booking(Draw $draw, Venue $venue, string $court = '5'): Fixture
    {
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'scheduled' => 1]);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court' => $court, 'time' => '2026-10-09 08:00:00']);
        return $fixture;
    }

    private function publish(Event $event, Draw $draw, Venue $venue, int $id, string $kind = 'individual'): void
    {
        DB::table('published_schedule_assignments')->insert(['event_id' => $event->id, 'draw_id' => $draw->id,
            'fixture_kind' => $kind, 'fixture_id' => $id, 'venue_id' => $venue->id, 'court' => 'Court 5',
            'scheduled_at' => '2026-10-09 08:00:00', 'published_at' => now()]);
        $draw->update(['oop_published' => true]);
    }

    public function test_confirmed_reduction_clears_all_venue_bookings_and_public_times_but_preserves_draws_and_other_events(): void
    {
        [$event, $venue, $draw] = $this->setupVenue();
        $first = $this->booking($draw, $venue, '1');
        $second = $this->booking($draw, $venue, '5');
        $team = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'round_nr' => 1, 'match_nr' => 1,
            'venue_id' => $venue->id, 'court_label' => '6', 'scheduled_at' => '2026-10-09 09:00:00', 'scheduled' => 1]);
        $this->publish($event, $draw, $venue, $first->id);
        $this->publish($event, $draw, $venue, $team->id, 'team');
        $other = Event::factory()->create();
        $other->venues()->attach($venue->id, ['num_courts' => 8]);
        $otherDraw = Draw::factory()->create(['event_id' => $other->id]);
        $foreign = $this->booking($otherDraw, $venue);
        $this->publish($other, $otherDraw, $venue, $foreign->id);
        $url = route('backend.event-venue-schedule.courts.configure', [$event, $venue]);
        $body = ['courts' => 4, 'ball_type' => 'standard'];
        $warning = $this->postJson($url, $body)->assertStatus(409)->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('impact.scheduled_matches', 3)->assertJsonPath('impact.published_matches', 2);
        $this->assertDatabaseCount('order_of_plays', 3);
        $confirmed = $body + ['confirm_reset' => true, 'correction_revision' => $warning->json('correction_revision')];
        $this->postJson($url, $confirmed)->assertOk();
        $this->assertDatabaseCount('order_of_plays', 1);
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $foreign->id]);
        $this->assertSame(0, (int) $first->fresh()->scheduled);
        $this->assertSame(0, (int) $second->fresh()->scheduled);
        $this->assertNull($team->fresh()->scheduled_at);
        $this->assertTrue((bool) $draw->fresh()->locked);
        $this->assertTrue((bool) $draw->fresh()->published);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $this->assertDatabaseCount('published_schedule_assignments', 1);
        $this->assertDatabaseHas('published_schedule_assignments', ['event_id' => $other->id, 'fixture_id' => $foreign->id]);
        $this->assertSame(4, DB::table('event_venue_courts')->where('event_id', $event->id)->where('active', true)->count());
        $this->assertDatabaseHas('event_venues', ['event_id' => $other->id, 'venue_id' => $venue->id, 'num_courts' => 8]);
        $this->assertDatabaseHas('draw_venues', ['draw_id' => $draw->id, 'venue_id' => $venue->id, 'num_courts' => 4]);
        $this->assertSame(4, DB::table('draw_venue_court_allocations')->where('draw_id', $draw->id)->count());
        $this->assertDatabaseCount('fixtures', 3);
        $this->assertDatabaseCount('team_fixtures', 1);
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'schedule_scope_hidden']);
        $newBooking = $this->booking($draw, $venue, '1');
        $this->postJson($url, $confirmed)->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $newBooking->id]);
    }

    public function test_correction_retracts_only_this_venues_public_times_and_keeps_other_venue_publication(): void
    {
        [$event, $venue, $draw] = $this->setupVenue();
        $otherVenue = new Venue(); $otherVenue->forceFill(['name' => 'Other Venue'])->save();
        $event->venues()->attach($otherVenue->id, ['num_courts' => 6]);
        $draw->venues()->attach($otherVenue->id, ['num_courts' => 6]);
        $first = Fixture::factory()->create(['draw_id' => $draw->id]);
        $other = $this->booking($draw, $otherVenue, '1');
        $this->publish($event, $draw, $venue, $first->id);
        $this->publish($event, $draw, $otherVenue, $other->id);
        $url = route('backend.event-venue-schedule.courts.configure', [$event, $venue]);
        $body = ['courts' => 4, 'ball_type' => 'standard'];
        $warning = $this->postJson($url, $body)->assertStatus(409)->assertJsonPath('impact.scheduled_matches', 0)
            ->assertJsonPath('impact.published_matches', 1);
        $this->postJson($url, $body + ['confirm_reset' => true, 'correction_revision' => $warning->json('correction_revision')])->assertOk();
        $this->assertDatabaseCount('published_schedule_assignments', 1);
        $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $other->id, 'venue_id' => $otherVenue->id]);
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $other->id, 'venue_id' => $otherVenue->id]);
        $this->assertTrue((bool) $draw->fresh()->oop_published);
        $this->assertTrue((bool) $draw->fresh()->published);
    }

    public function test_changed_schedule_requires_a_fresh_warning_and_never_trusts_the_confirmation_flag_alone(): void
    {
        [$event, $venue, $draw] = $this->setupVenue();
        $first = $this->booking($draw, $venue);
        $url = route('backend.event-venue-schedule.courts.configure', [$event, $venue]);
        $body = ['courts' => 4, 'ball_type' => 'green'];
        $warning = $this->postJson($url, $body)->assertStatus(409);
        $this->booking($draw, $venue, '1');
        $stale = $this->postJson($url, $body + ['confirm_reset' => true, 'correction_revision' => $warning->json('correction_revision')])
            ->assertStatus(409)->assertJsonPath('impact.scheduled_matches', 2);
        $this->assertNotSame($warning->json('correction_revision'), $stale->json('correction_revision'));
        $this->postJson($url, $body + ['confirm_reset' => true])->assertStatus(409);
        $this->assertDatabaseCount('order_of_plays', 2);
        $this->assertDatabaseHas('event_venues', ['event_id' => $event->id, 'venue_id' => $venue->id, 'num_courts' => 6]);
        $this->postJson($url, $body + ['confirm_reset' => true, 'correction_revision' => $stale->json('correction_revision')])->assertOk();
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $first->id]);
    }

    public function test_played_matches_block_confirmation_without_changing_courts_or_schedule(): void
    {
        [$event, $venue, $draw] = $this->setupVenue();
        $fixture = $this->booking($draw, $venue, '1');
        $fixture->update(['match_status' => 1]);
        $this->publish($event, $draw, $venue, $fixture->id);
        $this->postJson(route('backend.event-venue-schedule.courts.configure', [$event, $venue]),
            ['courts' => 4, 'ball_type' => 'standard', 'confirm_reset' => true])->assertUnprocessable();
        $this->assertDatabaseCount('order_of_plays', 1);
        $this->assertDatabaseCount('published_schedule_assignments', 1);
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id, 'match_status' => 1]);
        $this->assertDatabaseHas('event_venues', ['event_id' => $event->id, 'venue_id' => $venue->id, 'num_courts' => 6]);
    }

    public function test_results_on_a_moved_public_match_and_completed_team_ties_cannot_be_cleared(): void
    {
        [$event, $venue, $draw] = $this->setupVenue();
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        FixtureResult::factory()->create(['fixture_id' => $fixture->id]);
        $this->publish($event, $draw, $venue, $fixture->id);
        $url = route('backend.event-venue-schedule.courts.configure', [$event, $venue]);
        $this->postJson($url, ['courts' => 4, 'ball_type' => 'standard'])->assertUnprocessable();
        $this->assertDatabaseCount('fixture_results', 1);
        $this->assertDatabaseCount('published_schedule_assignments', 1);
        DB::table('published_schedule_assignments')->delete();
        $tie = TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'home_team_id' => Team::factory()->create()->id, 'away_team_id' => Team::factory()->create()->id, 'status' => 'completed']);
        $team = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1,
            'round_nr' => 1, 'match_nr' => 1, 'venue_id' => $venue->id, 'court_label' => '1', 'scheduled_at' => '2026-10-09 09:00:00']);
        $this->postJson($url, ['courts' => 4, 'ball_type' => 'standard', 'confirm_reset' => true])->assertUnprocessable();
        $this->assertNotNull($team->fresh()->scheduled_at);
        $this->assertDatabaseHas('team_ties', ['id' => $tie->id, 'status' => 'completed']);
        $this->assertDatabaseHas('event_venues', ['event_id' => $event->id, 'venue_id' => $venue->id, 'num_courts' => 6]);
    }

    public function test_played_team_history_without_a_time_blocks_both_court_correction_actions(): void
    {
        [$event, $venue, $draw] = $this->setupVenue();
        $team = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'round_nr' => 1, 'match_nr' => 1,
            'venue_id' => $venue->id, 'court_label' => 'Court 5', 'scheduled_at' => null, 'match_status' => 1]);
        $this->postJson(route('backend.event-venue-schedule.courts.configure', [$event, $venue]),
            ['courts' => 4, 'ball_type' => 'standard', 'confirm_reset' => true])->assertUnprocessable();
        $this->deleteJson(route('backend.event-venue-schedule.courts.remove', [$event, $venue]),
            ['label' => '5', 'confirm_reset' => true])->assertUnprocessable();
        $this->assertDatabaseHas('team_fixtures', ['id' => $team->id, 'match_status' => 1, 'court_label' => 'Court 5']);
        $this->assertDatabaseHas('event_venues', ['event_id' => $event->id, 'venue_id' => $venue->id, 'num_courts' => 6]);
    }

    public function test_a_dependency_at_another_venue_rolls_back_all_working_and_public_changes(): void
    {
        [$event, $venue, $draw] = $this->setupVenue();
        $otherVenue = new Venue(); $otherVenue->forceFill(['name' => 'Other Venue'])->save();
        $later = $this->booking($draw, $otherVenue);
        $first = $this->booking($draw, $venue);
        $first->update(['parent_fixture_id' => $later->id]);
        $team = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'round_nr' => 1, 'match_nr' => 1,
            'venue_id' => $venue->id, 'court_label' => '6', 'scheduled_at' => '2026-10-09 09:00:00']);
        $this->publish($event, $draw, $venue, $first->id);
        $url = route('backend.event-venue-schedule.courts.configure', [$event, $venue]);
        $body = ['courts' => 4, 'ball_type' => 'standard'];
        $warning = $this->postJson($url, $body)->assertStatus(409);
        $this->postJson($url, $body + ['confirm_reset' => true, 'correction_revision' => $warning->json('correction_revision')])
            ->assertUnprocessable()->assertJsonPath('message', 'A saved later match depends on this assignment. Return the dependent match to planning too.');
        $this->assertDatabaseCount('order_of_plays', 2);
        $this->assertNotNull($team->fresh()->scheduled_at);
        $this->assertDatabaseCount('published_schedule_assignments', 1);
        $this->assertDatabaseHas('event_venues', ['event_id' => $event->id, 'venue_id' => $venue->id, 'num_courts' => 6]);
        $this->assertDatabaseCount('draw_audit_logs', 0);
    }

    public function test_confirmed_cross_removal_is_idempotent_and_unauthorized_users_cannot_reset(): void
    {
        [$event, $venue, $draw] = $this->setupVenue();
        $fixture = $this->booking($draw, $venue);
        $url = route('backend.event-venue-schedule.courts.remove', [$event, $venue]);
        $warning = $this->deleteJson($url, ['label' => '5'])->assertStatus(409);
        $body = ['label' => '5', 'confirm_reset' => true, 'correction_revision' => $warning->json('correction_revision')];
        $this->deleteJson($url, $body)->assertOk();
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $fixture->id]);
        $this->assertDatabaseHas('event_venue_courts', ['event_id' => $event->id, 'label' => '5', 'active' => false]);
        $new = $this->booking($draw, $venue, '1');
        $this->deleteJson($url, $body)->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $new->id]);
        $this->actingAs(User::factory()->create())->deleteJson($url, ['label' => '4', 'confirm_reset' => true])->assertForbidden();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $new->id]);
    }
}
