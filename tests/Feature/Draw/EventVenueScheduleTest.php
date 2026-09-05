<?php

namespace Tests\Feature\Draw;

use App\Jobs\SendBulkEmailJob;
use App\Models\{Announcement, BulkEmailLog, CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventVenueScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_it_mixes_draws_on_one_shared_physical_court_pool(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Eight Court Venue');
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        foreach ($draws as $draw) {
            $draw->venues()->attach($venue->id, ['num_courts' => 2]);
            foreach (range(1, 2) as $match) {
                Fixture::factory()->create([
                    'draw_id' => $draw->id, 'round' => 1, 'match_nr' => $match, 'bracket_id' => 1,
                    'registration1_id' => Registration::factory()->create()->id,
                    'registration2_id' => Registration::factory()->create()->id,
                ]);
            }
        }

        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions());

        $this->assertCount(4, $preview['matches']);
        $firstWave = collect($preview['matches'])->where('scheduled_at', '2026-09-10 08:00:00');
        $this->assertCount(2, $firstWave);
        $this->assertCount(2, $firstWave->pluck('draw_id')->unique(), 'The first courts should be shared fairly between draws.');
        $this->assertSame(['1', '2'], $firstWave->pluck('court')->sort()->values()->all());
        $this->assertSame([], $preview['unscheduled']);
    }

    public function test_two_consecutive_byes_keep_the_first_playable_match_in_the_third_wave(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Main Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($venue->id, ['num_courts' => 1]);
        $player = Registration::factory()->create();
        $opponent = Registration::factory()->create();

        $roundOneBye = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
            'registration1_id' => $player->id, 'registration2_id' => null, 'winner_registration' => $player->id,
        ]);
        $roundTwoBye = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 2, 'match_nr' => 2, 'bracket_id' => 1,
            'registration1_id' => $player->id, 'registration2_id' => null, 'winner_registration' => $player->id,
        ]);
        $firstPlayable = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 3, 'match_nr' => 3, 'bracket_id' => 1,
            'registration1_id' => $player->id, 'registration2_id' => $opponent->id,
        ]);
        $roundOneBye->update(['parent_fixture_id' => $roundTwoBye->id]);
        $roundTwoBye->update(['parent_fixture_id' => $firstPlayable->id]);

        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions());

        $this->assertSame(2, $preview['automatic_byes']);
        $this->assertCount(1, $preview['matches']);
        $this->assertSame(3, $preview['matches'][0]['wave']);
        $this->assertSame('2026-09-10 11:00:00', $preview['matches'][0]['scheduled_at']);
    }

    public function test_player_rest_is_protected_across_draws_and_different_venues(): void
    {
        $event = Event::factory()->create();
        $venues = collect([$this->venue($event, 'North'), $this->venue($event, 'South')]);
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        $player = Player::factory()->create();
        foreach ($draws as $index => $draw) {
            $draw->venues()->attach($venues[$index]->id, ['num_courts' => 1]);
            $sharedRegistration = Registration::factory()->create();
            $sharedRegistration->players()->attach($player->id);
            Fixture::factory()->create([
                'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
                'registration1_id' => $sharedRegistration->id,
                'registration2_id' => Registration::factory()->create()->id,
            ]);
        }

        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions());
        $times = collect($preview['matches'])->pluck('scheduled_at')->sort()->values()->all();

        $this->assertSame(['2026-09-10 08:00:00', '2026-09-10 10:15:00'], $times);
        $this->assertCount(2, collect($preview['matches'])->pluck('venue_id')->unique());
    }

    public function test_applying_requires_the_current_preview_revision(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Main Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($venue->id, ['num_courts' => 1]);
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
        ]);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $this->schedulingOptions());

        $result = $service->apply($event, $this->schedulingOptions(), $preview['revision']);

        $this->assertSame(1, $result['count']);
        $this->assertDatabaseHas('order_of_plays', [
            'fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-09-10 08:00:00',
        ]);
        $this->assertTrue((bool) $fixture->fresh()->scheduled);
    }

    public function test_event_admin_can_assign_several_draws_to_the_same_shared_venue(): void
    {
        $event = Event::factory()->create();
        $availableVenue = $this->venue($event, 'Available Venue');
        $venue = $this->venue($event, 'Shared Venue');
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        foreach (range(1, 8) as $label) {
            DB::table('event_venue_courts')->insert([
                'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => (string) $label,
                'ball_type' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.assignments', $event), [
            'venues' => [['id' => $venue->id, 'courts' => 8]],
            'assignments' => $draws->map(fn ($draw) => [
                'draw_id' => $draw->id, 'venue_ids' => [$venue->id],
                'court_allocations' => [['venue_id' => $venue->id, 'court_labels' => ['2', '8']]],
            ])->all(),
        ]);

        $response->assertOk();
        foreach ($draws as $draw) {
            $this->assertDatabaseHas('draw_venues', [
                'draw_id' => $draw->id, 'venue_id' => $venue->id, 'num_courts' => 8,
            ]);
        }
        Schema::table('events', fn (Blueprint $table) => $table->string('venues')->nullable());
        DB::table('events')->where('id', $event->id)->update(['venues' => 'Legacy venue description']);
        $this->get(route('backend.event-venue-schedule.index', $event))
            ->assertOk()
            ->assertSee('Schedule every assigned age group')
            ->assertSeeInOrder(['Shared Venue', $availableVenue->name])
            ->assertSeeInOrder(['Court 2', 'Court 8', 'Court 1'])
            ->assertSee('open only the one you are editing')
            ->assertSee('id="court-allocation-step"', false)
            ->assertSee('id="schedule-rules-step"', false)
            ->assertSee('Next: timing rules');
    }

    public function test_an_admin_of_another_event_cannot_open_or_generate_the_schedule(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->get(route('backend.event-venue-schedule.index', $event))->assertForbidden();
        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.preview', $event), $this->schedulingOptions())
            ->assertForbidden();
    }

    public function test_preview_automatically_uses_only_venues_assigned_to_the_selected_draws(): void
    {
        $event = Event::factory()->create();
        $assignedVenue = $this->venue($event, 'Assigned Venue');
        $this->venue($event, 'Unrelated Event Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($assignedVenue->id, ['num_courts' => 1]);
        Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
        ]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $response = $this->actingAs($admin)->postJson(
            route('backend.event-venue-schedule.preview', $event),
            $this->schedulingOptions() + ['draw_ids' => [$draw->id]],
        );

        $response->assertOk()
            ->assertJsonCount(1, 'venues')
            ->assertJsonPath('venues.0.id', $assignedVenue->id)
            ->assertJsonCount(1, 'matches');
    }

    public function test_draws_can_be_limited_to_named_ball_type_courts(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Ball Court Venue');
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        foreach ([['Orange 1', 'orange'], ['Orange 2', 'orange'], ['Green 1', 'green'], ['Yellow 1', 'yellow']] as [$label, $type]) {
            DB::table('event_venue_courts')->insert([
                'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => $label,
                'ball_type' => $type, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ($draws as $draw) {
            $draw->venues()->attach($venue->id, ['num_courts' => 4]);
            Fixture::factory()->create([
                'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
                'registration1_id' => Registration::factory()->create()->id,
                'registration2_id' => Registration::factory()->create()->id,
            ]);
        }
        foreach ([[$draws[0]->id, ['Orange 1', 'Orange 2']], [$draws[1]->id, ['Green 1', 'Yellow 1']]] as [$drawId, $labels]) {
            foreach ($labels as $label) {
                DB::table('draw_venue_court_allocations')->insert([
                    'draw_id' => $drawId, 'venue_id' => $venue->id, 'court_label' => $label,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions());
        $courtsByDraw = collect($preview['matches'])->groupBy('draw_id')->map->pluck('court');

        $this->assertContains($courtsByDraw[$draws[0]->id]->first(), ['Orange 1', 'Orange 2']);
        $this->assertContains($courtsByDraw[$draws[1]->id]->first(), ['Green 1', 'Yellow 1']);
    }

    public function test_an_age_group_can_start_later_without_delaying_other_draws(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Stacked Venue');
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        foreach ($draws as $draw) {
            $draw->venues()->attach($venue->id, ['num_courts' => 1]);
            Fixture::factory()->create([
                'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
                'registration1_id' => Registration::factory()->create()->id,
                'registration2_id' => Registration::factory()->create()->id,
            ]);
        }
        $options = $this->schedulingOptions() + [
            'draw_ids' => $draws->pluck('id')->all(),
            'draw_starts' => [['draw_id' => $draws[1]->id, 'start' => '2026-09-10 11:00:00']],
        ];

        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $matches = collect($preview['matches'])->keyBy('draw_id');

        $this->assertSame('2026-09-10 08:00:00', $matches[$draws[0]->id]['scheduled_at']);
        $this->assertSame('2026-09-10 11:00:00', $matches[$draws[1]->id]['scheduled_at']);
    }

    public function test_event_admin_can_add_a_venue_and_named_ball_type_courts_from_the_schedule_page(): void
    {
        $event = Event::factory()->create();
        $venue = tap(new Venue(), fn (Venue $model) => $model->forceFill(['name' => 'Junior Courts'])->save());
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.venues', $event), [
            'venue_id' => $venue->id, 'courts' => 3, 'ball_type' => 'orange',
        ])->assertOk();
        $this->assertDatabaseHas('event_venues', [
            'event_id' => $event->id, 'venue_id' => $venue->id, 'num_courts' => 3,
        ]);
        $this->assertSame(3, DB::table('event_venue_courts')->where('event_id', $event->id)
            ->where('venue_id', $venue->id)->where('ball_type', 'orange')->count());

        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.courts', $event), [
            'venue_id' => $venue->id, 'label' => 'Green show court', 'ball_type' => 'green',
        ])->assertOk();
        $this->assertDatabaseHas('event_venue_courts', [
            'event_id' => $event->id, 'venue_id' => $venue->id,
            'label' => 'Green show court', 'ball_type' => 'green', 'active' => 1,
        ]);
        $this->assertDatabaseHas('event_venues', [
            'event_id' => $event->id, 'venue_id' => $venue->id, 'num_courts' => 4,
        ]);

        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.courts.configure', [$event, $venue]), [
            'courts' => 8, 'ball_type' => 'yellow',
        ])->assertOk();
        $this->assertSame(8, DB::table('event_venue_courts')->where('event_id', $event->id)
            ->where('venue_id', $venue->id)->where('active', true)->count());
        $this->assertSame(8, DB::table('event_venue_courts')->where('event_id', $event->id)
            ->where('venue_id', $venue->id)->where('ball_type', 'yellow')->count());
        $this->assertDatabaseMissing('event_venue_courts', [
            'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => 'Green show court',
        ]);
        $this->assertDatabaseHas('event_venues', [
            'event_id' => $event->id, 'venue_id' => $venue->id, 'num_courts' => 8,
        ]);
        $this->actingAs($admin)->get(route('backend.event-venue-schedule.index', $event))
            ->assertOk()
            ->assertSee('Manage venues and court setup')
            ->assertSee('value="yellow" selected', false)
            ->assertSee('value="standard"', false)
            ->assertDontSee('<option value="'.$venue->id.'">'.$venue->name.'</option>', false);
    }

    public function test_a_numbered_court_with_a_scheduled_match_cannot_be_removed(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Protected Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($venue->id, ['num_courts' => 3]);
        foreach (range(1, 3) as $label) {
            DB::table('event_venue_courts')->insert([
                'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => (string) $label,
                'ball_type' => 'standard', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
        ]);
        OrderOfPlay::create([
            'fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court' => '3', 'time' => '2026-09-10 08:00:00',
        ]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.courts.configure', [$event, $venue]), [
            'courts' => 2, 'ball_type' => 'standard',
        ])->assertUnprocessable()->assertJsonPath('message',
            'A court being removed already has scheduled matches. Clear those bookings before reducing or replacing the courts.');

        $this->assertDatabaseHas('event_venue_courts', [
            'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => '3',
        ]);
    }

    public function test_venue_court_setup_is_isolated_to_venues_belonging_to_the_event(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $otherVenue = $this->venue($otherEvent, 'Other Event Venue');
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->postJson(
            route('backend.event-venue-schedule.courts.configure', [$event, $otherVenue]),
            ['courts' => 8, 'ball_type' => 'standard'],
        )->assertNotFound();

        $this->assertDatabaseMissing('event_venue_courts', [
            'event_id' => $event->id, 'venue_id' => $otherVenue->id,
        ]);
    }

    public function test_blank_venue_and_court_names_are_rejected(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Valid Venue');
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.venues', $event), [
            'name' => '   ', 'courts' => 1, 'ball_type' => 'standard',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.courts', $event), [
            'venue_id' => $venue->id, 'label' => '   ', 'ball_type' => 'standard',
        ])->assertUnprocessable()->assertJsonValidationErrors('label');
    }

    public function test_event_admin_can_review_edit_and_publish_the_saved_venue_announcement(): void
    {
        Queue::fake();
        $event = Event::factory()->create(['name' => 'Overberg Tennis Trials']);
        $venue = $this->venue($event, 'Hermanus High School');
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/12 Girls']);
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        foreach (['1', '2'] as $label) {
            DB::table('event_venue_courts')->insert([
                'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => $label,
                'ball_type' => 'standard', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('draw_venue_court_allocations')->insert([
                'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court_label' => $label,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $player = Player::factory()->create(['email' => 'Player@Example.com']);
        $activeRegistration = Registration::factory()->create();
        $activeRegistration->players()->attach($player);
        $categoryEvent = CategoryEvent::factory()->for($event)->create();
        CategoryEventRegistration::factory()->paid()->create([
            'category_event_id' => $categoryEvent->id,
            'registration_id' => $activeRegistration->id,
            'status' => 'active',
        ]);

        // Duplicate, withdrawn, and unpaid entries must not expand the delivery list.
        $duplicateRegistration = Registration::factory()->create();
        $duplicateRegistration->players()->attach($player);
        CategoryEventRegistration::factory()->paid()->create([
            'category_event_id' => $categoryEvent->id,
            'registration_id' => $duplicateRegistration->id,
            'status' => 'active',
        ]);
        foreach ([['withdrawn', 1], ['active', null]] as [$status, $paymentStatus]) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create());
            CategoryEventRegistration::factory()->create([
                'category_event_id' => $categoryEvent->id,
                'registration_id' => $registration->id,
                'status' => $status,
                'payment_status_id' => $paymentStatus,
                'withdrawn_at' => $status === 'withdrawn' ? now() : null,
            ]);
        }

        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->get(route('backend.event-venue-schedule.index', $event))
            ->assertOk()
            ->assertSee('Announce assigned courts')
            ->assertSee('Review venue announcement and email')
            ->assertSee('u/12 Girls')
            ->assertSee('Hermanus High School')
            ->assertSee('Court 1')
            ->assertSee('Court 2')
            ->assertSee('unique active, paid player');

        $response = $this->actingAs($admin)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Final court allocation',
            'message' => '<p>Courts are ready. Please review your age group.</p>',
            'sendMail' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('mail.total', 1)
            ->assertJsonPath('mail.queued', 1);
        $announcement = Announcement::where('event_id', $event->id)->sole();
        $this->assertSame('Final court allocation', $announcement->title);
        $this->assertDatabaseHas('bulk_email_logs', [
            'related_id' => $announcement->id,
            'mail_type' => 'event_announcement',
            'recipient_email' => 'player@example.com',
            'status' => 'queued',
        ]);
        $this->assertSame(1, BulkEmailLog::where('related_id', $announcement->id)->count());
        Queue::assertPushed(SendBulkEmailJob::class, 1);
    }

    public function test_an_admin_of_another_event_cannot_publish_or_edit_event_announcements(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $announcement = Announcement::create([
            'event_id' => $event->id,
            'title' => 'Protected',
            'message' => '<p>Protected announcement</p>',
        ]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $otherEvent->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Cross-event', 'message' => '<p>Should not save</p>', 'sendMail' => false,
        ])->assertForbidden();
        $this->actingAs($admin)->patchJson(route('admin.announcements.update', $announcement), [
            'title' => 'Changed', 'message' => '<p>Changed</p>',
        ])->assertForbidden();

        $this->assertSame('Protected', $announcement->fresh()->title);
        $this->assertDatabaseMissing('announcements', ['event_id' => $event->id, 'title' => 'Cross-event']);
    }

    private function schedulingOptions(): array
    {
        return [
            'start' => '2026-09-10 08:00:00', 'end' => '2026-09-10 18:00:00',
            'duration' => 75, 'wave_minutes' => 90, 'court_gap' => 0, 'player_rest' => 60,
        ];
    }

    private function venue(Event $event, string $name): Venue
    {
        $venue = new Venue();
        $venue->forceFill(['name' => $name])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 1]);
        return $venue;
    }
}
