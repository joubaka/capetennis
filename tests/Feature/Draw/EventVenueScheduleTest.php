<?php

namespace Tests\Feature\Draw;

use App\Jobs\SendBulkEmailJob;
use App\Models\{Announcement, BulkEmailLog, CategoryEvent, CategoryEventRegistration, Draw, DrawGroup, DrawSetting, Event, Fixture, FixtureResult, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\FlexibleMonradService;
use App\Services\Scheduling\EventVenueScheduleService;
use App\Services\Scheduling\RoundRobinPlayoffScheduleService;
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

    public function test_unresolved_legacy_fixtures_show_winner_and_loser_match_paths(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Path Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        $semifinals = collect([1, 2])->map(fn ($match) => Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 1, 'match_nr' => $match, 'bracket_id' => 1,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
        ]));
        $final = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 2, 'match_nr' => 3, 'bracket_id' => 1,
            'registration1_id' => null, 'registration2_id' => null,
        ]);
        $playoff = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 2, 'match_nr' => 4, 'bracket_id' => 2,
            'registration1_id' => null, 'registration2_id' => null,
        ]);
        foreach ($semifinals as $slot => $semifinal) {
            $semifinal->update(['parent_fixture_id' => $final->id, 'feeder_slot' => $slot + 1,
                'loser_parent_fixture_id' => $playoff->id]);
            DB::table('fixtures')->where('id', $semifinal->id)->update(['loser_feeder_slot' => $slot + 1]);
        }

        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions());
        $matches = collect($preview['matches'])->keyBy('fixture_id');

        $this->assertSame(['Winner of Match 1', 'Winner of Match 2'], $matches[$final->id]['participants']);
        $this->assertSame(['Loser of Match 1', 'Loser of Match 2'], $matches[$playoff->id]['participants']);
        $this->assertSame(3, $matches[$final->id]['match']);
        $this->assertSame(4, $matches[$playoff->id]['match']);
    }

    public function test_position_pair_playoffs_are_prepared_and_scheduled_after_the_round_robin(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Round Robin Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'U/10B Boys']);
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        DrawSetting::updateOrCreate(['draw_id' => $draw->id], [
            'workflow' => 'round_robin_playoffs',
            'playoff_config' => [
                ['name' => '1st/2nd', 'slug' => 'main', 'size' => 2, 'positions' => [1, 2], 'enabled' => true],
                ['name' => '3rd/4th', 'slug' => 'plate', 'size' => 2, 'positions' => [3, 4], 'enabled' => true],
            ],
        ]);
        $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A']);
        $registrations = Registration::factory()->count(4)->create();
        foreach ($registrations as $seed => $registration) {
            $group->registrations()->attach($registration->id, ['seed' => $seed + 1]);
        }
        foreach ([[0, 1], [2, 3], [0, 2], [1, 3], [0, 3], [1, 2]] as $index => [$home, $away]) {
            Fixture::factory()->create([
                'draw_id' => $draw->id,
                'draw_group_id' => $group->id,
                'stage' => 'RR',
                'round' => intdiv($index, 2) + 1,
                'match_nr' => $index + 1,
                'registration1_id' => $registrations[$home]->id,
                'registration2_id' => $registrations[$away]->id,
            ]);
        }

        $created = app(RoundRobinPlayoffScheduleService::class)->prepare($draw->fresh());
        $preview = app(EventVenueScheduleService::class)->preview($event->fresh(), $this->schedulingOptions());
        $matches = collect($preview['matches']);
        $playoffs = $matches->whereIn('fixture_id', $created->pluck('id'));

        $this->assertCount(2, $created);
        $this->assertCount(8, $matches);
        $this->assertSame([4], $playoffs->pluck('wave')->unique()->values()->all());
        $this->assertSame(['Group A #1', 'Group A #2'], $playoffs->firstWhere('stage', 'MAIN')['participants']);
        $this->assertSame(['Group A #3', 'Group A #4'], $playoffs->firstWhere('stage', 'PLATE')['participants']);
        $this->assertSame(['2026-09-10 14:45:00'], $playoffs->pluck('scheduled_at')->unique()->values()->all());
    }

    public function test_unresolved_flexible_monrad_fixtures_show_their_numbered_source_paths(): void
    {
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        $venue = $this->venue($event, 'Monrad Venue');
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        $registrations = Registration::factory()->count(4)->create();
        foreach ($registrations as $registration) {
            $registration->categoryEvents()->attach($category->id, ['status' => 'registered', 'payment_status_id' => 1]);
        }
        $slots = [];
        foreach (['aa', 'ab', 'ba', 'bb'] as $index => $path) {
            $slots[$path] = ['type' => 'player', 'id' => $registrations[$index]->id];
        }
        $service = app(FlexibleMonradService::class);
        $service->save($draw, ['size' => 4, 'slots' => $slots], 0);
        $record = $service->generate($draw, 1);

        $preview = app(EventVenueScheduleService::class)->preview($event->fresh(), $this->schedulingOptions());
        $matches = collect($preview['matches'])->keyBy('fixture_id');

        $this->assertSame(['Winner of Match 1', 'Winner of Match 2'],
            $matches[$record->fixture_map['main_final']]['participants']);
        $this->assertSame(['Loser of Match 1', 'Loser of Match 2'],
            $matches[$record->fixture_map['place_3']]['participants']);

        $record = $service->score($draw, $record->fixture_map['main_a'], [[6, 2]], $record->revision, false);
        $partlyResolved = app(EventVenueScheduleService::class)
            ->preview($event->fresh(), $this->schedulingOptions());
        $finalParticipants = collect($partlyResolved['matches'])
            ->firstWhere('fixture_id', $record->fixture_map['main_final'])['participants'];
        $this->assertCount(2, $finalParticipants);
        $this->assertSame('Winner of Match 2', $finalParticipants[1]);
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

    public function test_a_single_venue_can_be_applied_while_other_venues_remain_in_planning(): void
    {
        $event = Event::factory()->create();
        $venues = collect([$this->venue($event, 'Approved Venue'), $this->venue($event, 'Still Planning')]);
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        $fixtures = collect();
        foreach ($draws as $index => $draw) {
            $draw->venues()->attach($venues[$index]->id, ['num_courts' => 1]);
            $fixtures->push(Fixture::factory()->create([
                'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
                'registration1_id' => Registration::factory()->create()->id,
                'registration2_id' => Registration::factory()->create()->id,
            ]));
        }
        $service = app(EventVenueScheduleService::class);
        $options = $this->schedulingOptions();
        $preview = $service->preview($event, $options);

        $otherEvent = Event::factory()->create();
        $foreignVenue = $this->venue($otherEvent, 'Foreign Venue');
        try {
            $service->apply($event, $options + ['apply_venue_ids' => [$foreignVenue->id]], $preview['revision']);
            $this->fail('A venue outside the preview was accepted.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('One or more venues selected for applying are not available in this preview.', $exception->getMessage());
        }

        $applied = $service->apply($event, $options + ['apply_venue_ids' => [$venues[0]->id]], $preview['revision']);

        $this->assertSame(1, $applied['count']);
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixtures[0]->id, 'venue_id' => $venues[0]->id]);
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $fixtures[1]->id]);

        $nextPreview = $service->preview($event->fresh(), $options);
        $this->assertSame([$fixtures[0]->id], collect($nextPreview['existing_matches'])->pluck('fixture_id')->all());
        $this->assertSame([$fixtures[1]->id], collect($nextPreview['matches'])->pluck('fixture_id')->all());
        $this->assertSame(collect($preview['matches'])->firstWhere('fixture_id', $fixtures[0]->id)['scheduled_at'],
            $nextPreview['existing_matches'][0]['scheduled_at']);
    }

    public function test_an_applied_venue_is_only_replanned_when_explicitly_requested(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Fixed Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($venue->id, ['num_courts' => 1]);
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
        ]);
        $service = app(EventVenueScheduleService::class);
        $options = $this->schedulingOptions();
        $preview = $service->preview($event, $options);
        $service->apply($event, $options, $preview['revision']);

        $laterOptions = array_replace($options, ['start' => '2026-09-10 09:00:00']);
        $fixedPreview = $service->preview($event->fresh(), $laterOptions);
        $this->assertCount(0, $fixedPreview['matches']);
        $this->assertSame('2026-09-10 08:00:00', collect($fixedPreview['existing_matches'])
            ->firstWhere('fixture_id', $fixture->id)['scheduled_at']);

        $replanned = $service->preview($event->fresh(), $laterOptions + ['replan_venue_ids' => [$venue->id]]);
        $this->assertCount(0, $replanned['existing_matches']);
        $this->assertSame('2026-09-10 09:00:00', collect($replanned['matches'])
            ->firstWhere('fixture_id', $fixture->id)['scheduled_at']);
    }

    public function test_applied_matches_can_be_returned_to_planning_by_venue_or_draw(): void
    {
        $event = Event::factory()->create();
        $venues = collect([$this->venue($event, 'North Venue'), $this->venue($event, 'South Venue')]);
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        $fixtures = collect();
        foreach ($draws as $index => $draw) {
            $draw->venues()->attach($venues[$index]->id, ['num_courts' => 1]);
            $fixtures->push(Fixture::factory()->create([
                'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
                'registration1_id' => Registration::factory()->create()->id,
                'registration2_id' => Registration::factory()->create()->id,
            ]));
        }
        $service = app(EventVenueScheduleService::class);
        $options = $this->schedulingOptions();
        $preview = $service->preview($event, $options);
        $service->apply($event, $options, $preview['revision']);

        $venueResult = $service->unapply($event->fresh(), null, $venues[0]->id);

        $this->assertSame(1, $venueResult['count']);
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $fixtures[0]->id]);
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixtures[1]->id]);
        $this->assertFalse((bool) $fixtures[0]->fresh()->scheduled);
        $this->assertTrue((bool) $fixtures[1]->fresh()->scheduled);

        $drawResult = $service->unapply($event->fresh(), $draws[1]->id, null);

        $this->assertSame(1, $drawResult['count']);
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $fixtures[1]->id]);
        $this->assertFalse((bool) $fixtures[1]->fresh()->scheduled);
        $this->assertDatabaseHas('draw_audit_logs', [
            'draw_id' => $draws[0]->id, 'action' => 'event_venue_schedule_unapplied',
        ]);
        $this->assertDatabaseHas('draw_audit_logs', [
            'draw_id' => $draws[1]->id, 'action' => 'event_venue_schedule_unapplied',
        ]);
    }

    public function test_played_locked_or_published_matches_cannot_be_returned_to_planning(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Protected Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($venue->id, ['num_courts' => 1]);
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1, 'bracket_id' => 1,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
        ]);
        $service = app(EventVenueScheduleService::class);
        $options = $this->schedulingOptions();
        $preview = $service->preview($event, $options);
        $service->apply($event, $options, $preview['revision']);

        $draw->update(['locked' => true]);
        try {
            $service->unapply($event->fresh(), $draw->id, null);
            $this->fail('A locked draw schedule was unapplied.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('locked or published', $exception->getMessage());
        }

        $draw->update(['locked' => false]);
        FixtureResult::factory()->create(['fixture_id' => $fixture->id]);
        try {
            $service->unapply($event->fresh(), $draw->id, null);
            $this->fail('A played match schedule was unapplied.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Played matches cannot be returned to planning.', $exception->getMessage());
        }
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixture->id]);
        $this->assertTrue((bool) $fixture->fresh()->scheduled);
    }

    public function test_event_admin_can_remove_one_match_assignment_without_clearing_the_rest(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Preview Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'locked' => false]);
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        $fixtures = Fixture::factory()->count(2)->create([
            'draw_id' => $draw->id, 'round' => 1, 'bracket_id' => 1, 'scheduled' => true,
        ]);
        foreach ($fixtures as $index => $fixture) {
            OrderOfPlay::create([
                'draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                'court' => (string) ($index + 1), 'time' => '2026-09-06 0'.($index + 8).':00:00',
            ]);
        }
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->postJson(route('backend.event-venue-schedule.unapply', $event), ['fixture_id' => $fixtures[0]->id])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $fixtures[0]->id]);
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixtures[1]->id]);
        $this->assertFalse((bool) $fixtures[0]->fresh()->scheduled);
        $this->assertTrue((bool) $fixtures[1]->fresh()->scheduled);
        $this->assertDatabaseHas('draw_audit_logs', [
            'draw_id' => $draw->id, 'action' => 'event_venue_schedule_unapplied',
        ]);

        $otherEvent = Event::factory()->create();
        $otherDraw = Draw::factory()->create(['event_id' => $otherEvent->id]);
        $otherFixture = Fixture::factory()->create(['draw_id' => $otherDraw->id, 'scheduled' => true]);
        OrderOfPlay::create([
            'draw_id' => $otherDraw->id, 'fixture_id' => $otherFixture->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-09-06 12:00:00',
        ]);

        $this->postJson(route('backend.event-venue-schedule.unapply', $event), ['fixture_id' => $otherFixture->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This match does not belong to the event.');
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $otherFixture->id]);
    }

    public function test_event_admin_can_drag_one_match_to_an_open_allocated_slot(): void
    {
        $event = Event::factory()->create();
        $venue = $this->venue($event, 'Manual Schedule Venue');
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        DB::table('draw_venue_court_allocations')->insert([
            'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court_label' => '2',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $fixtures = Fixture::factory()->count(2)->create([
            'draw_id' => $draw->id, 'round' => 1, 'bracket_id' => 1,
            'registration1_id' => Registration::factory(),
            'registration2_id' => Registration::factory(),
        ]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $url = route('backend.event-venue-schedule.manual-assignment', $event);
        $assignment = [
            'fixture_id' => $fixtures[0]->id, 'scheduled_at' => '2026-09-10 09:30:00',
            'venue_id' => $venue->id, 'court' => '2', 'duration' => 75,
            'court_gap' => 5, 'player_rest' => 60,
        ];

        $this->actingAs($admin)->postJson($url, array_replace($assignment, ['court' => '1']))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Choose an active court allocated to this draw.');

        $this->postJson($url, $assignment)
            ->assertOk()
            ->assertJsonPath('assignment.fixture_id', $fixtures[0]->id)
            ->assertJsonPath('assignment.court', '2');

        $this->assertDatabaseHas('order_of_plays', [
            'fixture_id' => $fixtures[0]->id, 'venue_id' => $venue->id, 'court' => '2',
            'time' => '2026-09-10 09:30:00', 'duration_minutes' => 75, 'gap_minutes' => 5,
        ]);
        $this->assertDatabaseHas('draw_audit_logs', [
            'draw_id' => $draw->id, 'action' => 'event_venue_match_manually_scheduled',
        ]);

        $this->postJson($url, array_replace($assignment, ['fixture_id' => $fixtures[1]->id]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Schedule conflict: the court or a participant is already booked during this time.');
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $fixtures[1]->id]);

        DB::table('draw_venue_court_allocations')->insert([
            'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court_label' => '1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sharedPlayer = Player::factory()->create();
        $fixtures[0]->registration1->players()->attach($sharedPlayer->id);
        $fixtures[1]->registration1->players()->attach($sharedPlayer->id);
        $this->postJson($url, array_replace($assignment, [
            'fixture_id' => $fixtures[1]->id, 'court' => '1', 'scheduled_at' => '2026-09-10 11:00:00',
        ]))->assertUnprocessable()
            ->assertJsonPath('message', 'Schedule conflict: the court or a participant is already booked during this time.');
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
            ->assertSee('data-draw-summary="'.$draws->first()->id.'"', false)
            ->assertSee('Shared Venue · 2 courts')
            ->assertSee('<details class="card preview-venue mb-4"', false)
            ->assertDontSee('<details class="card preview-venue mb-4" open>', false)
            ->assertSee('open only the one you are editing')
            ->assertSee('id="court-allocation-step"', false)
            ->assertSee('id="schedule-rules-step"', false)
            ->assertSee('id="schedule-activity"', false)
            ->assertSee('id="schedule-activity-bar"', false)
            ->assertSee('Applying schedule…')
            ->assertSee('Rebuilding the applied schedule view…')
            ->assertSee('100% · Complete')
            ->assertSee('window.location.assign(drawsUrl)', false)
            ->assertSee('schedule=applied')
            ->assertSee('Replan all applied venues')
            ->assertSee('Unapply venue times')
            ->assertSee('manual-assignment')
            ->assertSee('draggable="true"', false)
            ->assertSee('Drop or place selected match')
            ->assertSee('Match selected. Choose an Available slot.')
            ->assertSee("document.getElementById('generate-preview').click()", false)
            ->assertSee('venue-schedule\/unapply', false)
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
        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.unapply', $event), ['draw_id' => 1])
            ->assertForbidden();
        $this->actingAs($admin)->postJson(route('backend.event-venue-schedule.manual-assignment', $event), [
            'fixture_id' => 1, 'scheduled_at' => '2026-09-10 09:00:00', 'venue_id' => 1,
            'court' => '1', 'duration' => 75, 'court_gap' => 5, 'player_rest' => 60,
        ])->assertForbidden();
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
