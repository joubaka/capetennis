<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, TeamFixture, User, Venue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventVenueRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_removal_detaches_only_this_events_venue_and_allocations(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $venue = $this->venue();
        $draw = $this->attach($event, $venue);
        $otherDraw = $this->attach($other, $venue);
        $this->actingAs($this->admin($event))->deleteJson($this->url($event, $venue))
            ->assertOk()->assertJsonPath('venue_id', $venue->id);
        $this->assertDatabaseMissing('event_venues', ['event_id' => $event->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseMissing('draw_venues', ['draw_id' => $draw->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseMissing('draw_venue_court_allocations', ['draw_id' => $draw->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseMissing('event_venue_courts', ['event_id' => $event->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('venues', ['id' => $venue->id]);
        $this->assertDatabaseHas('event_venues', ['event_id' => $other->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('draw_venues', ['draw_id' => $otherDraw->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('draw_venue_court_allocations', ['draw_id' => $otherDraw->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('event_venue_courts', ['event_id' => $other->id, 'venue_id' => $venue->id]);
        $this->deleteJson($this->url($event, $venue))->assertNotFound();
    }

    public function test_other_event_admin_and_foreign_venue_are_rejected(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $venue = $this->venue();
        $this->attach($event, $venue);
        $this->actingAs($this->admin($other))->deleteJson($this->url($event, $venue))->assertForbidden();
        $this->actingAs($this->admin($event))->deleteJson($this->url($event, $this->venue()))->assertNotFound();
        $this->assertDatabaseHas('event_venues', ['event_id' => $event->id, 'venue_id' => $venue->id]);
    }

    public function test_locked_draws_prevent_removal(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue();
        $draw = $this->attach($event, $venue);
        $draw->forceFill(['locked' => true])->save();
        $this->actingAs($this->admin($event))->deleteJson($this->url($event, $venue))->assertUnprocessable();
        $this->assertDatabaseHas('draw_venues', ['draw_id' => $draw->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('event_venue_courts', ['event_id' => $event->id, 'venue_id' => $venue->id]);
    }

    public function test_saved_individual_match_prevents_removal_even_without_order_draw_id(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue();
        $draw = $this->attach($event, $venue);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        $slot = OrderOfPlay::create(['fixture_id' => $fixture->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-10-05 08:00:00', 'duration_minutes' => 75]);
        $this->actingAs($this->admin($event))->deleteJson($this->url($event, $venue))->assertUnprocessable();
        $this->assertDatabaseHas('order_of_plays', ['id' => $slot->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('event_venues', ['event_id' => $event->id, 'venue_id' => $venue->id]);
    }

    public function test_saved_team_match_prevents_removal(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue();
        $draw = $this->attach($event, $venue);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'round_nr' => 1,
            'match_nr' => 1, 'venue_id' => $venue->id, 'court_label' => '1', 'scheduled_at' => '2026-10-05 08:00:00']);
        $this->actingAs($this->admin($event))->deleteJson($this->url($event, $venue))->assertUnprocessable();
        $this->assertDatabaseHas('team_fixtures', ['id' => $fixture->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('event_venues', ['event_id' => $event->id, 'venue_id' => $venue->id]);
    }

    public function test_age_group_removal_keeps_event_courts_and_other_age_groups(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue();
        $draw = $this->attach($event, $venue);
        $otherDraw = Draw::factory()->create(['event_id' => $event->id]);
        $otherDraw->venues()->attach($venue->id, ['num_courts' => 1]);
        DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id, 'options' => json_encode([
            'gender_waves' => 'boys_then_girls',
            'rank_venue_preferences' => [['draw_ids' => [$draw->id, $otherDraw->id], 'venue_id' => $venue->id, 'min_rank' => 1, 'max_rank' => 4]],
        ]), 'created_at' => now(), 'updated_at' => now()]);
        $url = route('backend.event-venue-schedule.draw-venues.remove', [$event, $draw, $venue]);
        $this->actingAs($this->admin($event))->deleteJson($url)->assertOk()->assertJsonPath('draw_id', $draw->id);
        $this->assertDatabaseMissing('draw_venues', ['draw_id' => $draw->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseMissing('draw_venue_court_allocations', ['draw_id' => $draw->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('event_venues', ['event_id' => $event->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('event_venue_courts', ['event_id' => $event->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('draw_venues', ['draw_id' => $otherDraw->id, 'venue_id' => $venue->id]);
        $options = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        $this->assertSame([$otherDraw->id], $options['rank_venue_preferences'][0]['draw_ids']);
        $this->assertSame('boys_then_girls', $options['gender_waves']);
        $this->deleteJson($url)->assertNotFound();
    }

    public function test_age_group_removal_rejects_foreign_draw_and_other_event_admin(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $venue = $this->venue();
        $draw = $this->attach($event, $venue);
        $foreignDraw = $this->attach($other, $venue);
        $this->actingAs($this->admin($event))->deleteJson(route('backend.event-venue-schedule.draw-venues.remove', [$event, $foreignDraw, $venue]))->assertNotFound();
        $this->actingAs($this->admin($other))->deleteJson(route('backend.event-venue-schedule.draw-venues.remove', [$event, $draw, $venue]))->assertForbidden();
        $this->assertDatabaseHas('draw_venues', ['draw_id' => $draw->id, 'venue_id' => $venue->id]);
        $this->assertDatabaseHas('draw_venues', ['draw_id' => $foreignDraw->id, 'venue_id' => $venue->id]);
    }

    public function test_age_group_removal_blocks_protected_draws_and_saved_matches(): void
    {
        foreach (['locked', 'individual', 'team'] as $state) {
            $event = Event::factory()->create();
            $venue = $this->venue();
            $draw = $this->attach($event, $venue);
            if ($state === 'locked') {
                $draw->forceFill([$state => true])->save();
            } elseif ($state === 'individual') {
                $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
                OrderOfPlay::create(['fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                    'court' => '1', 'time' => '2026-10-05 08:00:00']);
            } else {
                TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'round_nr' => 1,
                    'match_nr' => 1, 'venue_id' => $venue->id, 'scheduled_at' => '2026-10-05 08:00:00']);
            }
            $this->actingAs($this->admin($event))->deleteJson(route('backend.event-venue-schedule.draw-venues.remove', [$event, $draw, $venue]))->assertUnprocessable();
            $this->assertDatabaseHas('draw_venues', ['draw_id' => $draw->id, 'venue_id' => $venue->id]);
            $this->assertDatabaseHas('draw_venue_court_allocations', ['draw_id' => $draw->id, 'venue_id' => $venue->id]);
        }
    }

    public function test_unused_venue_can_be_removed_from_a_published_draw(): void
    {
        foreach ([false, true] as $drawOnly) {
            $event = Event::factory()->create();
            $venue = $this->venue();
            $draw = $this->attach($event, $venue);
            $draw->forceFill(['published' => true])->save();
            $url = $drawOnly ? route('backend.event-venue-schedule.draw-venues.remove', [$event, $draw, $venue]) : $this->url($event, $venue);
            $this->actingAs($this->admin($event))->deleteJson($url)->assertOk();
            $this->assertDatabaseMissing('draw_venues', ['draw_id' => $draw->id, 'venue_id' => $venue->id]);
            $this->assertTrue((bool) $draw->fresh()->published);
        }
    }

    public function test_published_booking_blocks_removal_after_working_match_moves(): void
    {
        foreach ([false, true] as $drawOnly) {
            $event = Event::factory()->create();
            $venue = $this->venue();
            $draw = $this->attach($event, $venue);
            $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'round_nr' => 1,
                'match_nr' => 1, 'venue_id' => $this->venue()->id, 'scheduled_at' => '2026-10-05 09:00:00']);
            DB::table('published_schedule_assignments')->insert(['event_id' => $event->id, 'draw_id' => $draw->id,
                'fixture_kind' => 'team', 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                'scheduled_at' => '2026-10-05 08:00:00', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $url = $drawOnly ? route('backend.event-venue-schedule.draw-venues.remove', [$event, $draw, $venue]) : $this->url($event, $venue);
            $this->actingAs($this->admin($event))->deleteJson($url)->assertUnprocessable();
            $this->assertDatabaseHas('draw_venues', ['draw_id' => $draw->id, 'venue_id' => $venue->id]);
            $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $fixture->id, 'venue_id' => $venue->id]);
        }
    }

    public function test_other_draw_or_event_publication_does_not_block_unused_venue_removal(): void
    {
        foreach ([false, true] as $drawOnly) {
            $event = Event::factory()->create();
            $venue = $this->venue();
            $draw = $this->attach($event, $venue);
            $other = $drawOnly ? $event : Event::factory()->create();
            $otherDraw = Draw::factory()->create(['event_id' => $other->id]);
            $fixture = TeamFixture::create(['draw_id' => $otherDraw->id, 'fixture_type' => 1, 'round_nr' => 1, 'match_nr' => 1]);
            DB::table('published_schedule_assignments')->insert(['event_id' => $other->id, 'draw_id' => $otherDraw->id,
                'fixture_kind' => 'team', 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                'scheduled_at' => '2026-10-05 08:00:00', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $url = $drawOnly ? route('backend.event-venue-schedule.draw-venues.remove', [$event, $draw, $venue]) : $this->url($event, $venue);
            $this->actingAs($this->admin($event))->deleteJson($url)->assertOk();
            $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $fixture->id, 'venue_id' => $venue->id]);
        }
    }

    private function admin(Event $event): User
    {
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        return $admin;
    }

    private function venue(): Venue
    {
        $venue = new Venue();
        $venue->forceFill(['name' => 'Shared test venue'])->save();
        return $venue;
    }

    private function attach(Event $event, Venue $venue): Draw
    {
        $event->venues()->attach($venue->id, ['num_courts' => 1]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
        $draw->venues()->attach($venue->id, ['num_courts' => 1]);
        DB::table('draw_venue_court_allocations')->insert(['draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court_label' => '1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id,
            'label' => '1', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return $draw;
    }

    private function url(Event $event, Venue $venue): string
    {
        return route('backend.event-venue-schedule.venues.remove', [$event, $venue]);
    }
}
