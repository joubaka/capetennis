<?php

namespace Tests\Feature\Draw;

use App\Models\{Category, CategoryEvent, Draw, Event, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\{FlexibleMonradService, FlexibleMonradScheduler};
use App\Services\Scheduling\{DailyMatchLimit, EventVenueScheduleService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleDailyLimitTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $split = true): array
    {
        $event = Event::factory()->create(['start_date' => '2026-10-30', 'end_date' => '2026-11-01']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'Under 19 Boys'])->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'drawName' => 'Under 19 Boys']);
        $draw->settings()->create(['workflow' => 'feed_in_monrad', 'playoff_config' => ['challenge_playoffs' => true]]);
        $venue = Venue::forceCreate(['name' => 'Daily limit test courts']);
        $draw->venues()->attach($venue->id, ['num_courts' => 6]);
        $slots = [];
        foreach (Registration::factory()->count(16)->create() as $index => $registration) {
            $registration->categoryEvents()->attach($category->id, ['status' => 'registered', 'payment_status_id' => 1]);
            $registration->players()->attach(Player::factory()->create()->id);
            $path = strtr(str_pad(decbin($index), 4, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = ['type' => 'player', 'id' => $registration->id];
        }
        $compiler = app(FlexibleMonradService::class);
        $compiler->save($draw, ['size' => 16, 'slots' => $slots], 0);
        $compiler->generate($draw, 1);
        $draw = $draw->fresh();
        $rounds = app(EventVenueScheduleService::class)->availableDrawRounds(collect([$draw]), true)[$draw->id];
        $days = array_map(fn ($date) => ['start' => $date.' 08:00:00', 'end' => $date.' 20:00:00'], ['2026-10-30', '2026-10-31', '2026-11-01']);
        $programme = ['days' => $days, 'rounds' => array_map(fn ($round) => ['draw_id' => $draw->id, 'round' => $round, 'day' => $split && $round > 3 ? 2 : 1, 'sequence' => $round], $rounds)];
        $options = ['start' => $days[0]['start'], 'end' => $days[2]['end'], 'duration' => 60, 'wave_minutes' => 90, 'court_gap' => 5,
            'player_rest' => 30, 'draw_ids' => [$draw->id], 'programme' => $programme, 'limit_daily_matches' => true];
        return [$event, $draw, $venue, $options];
    }

    private function admin(Event $event): User
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        return $admin;
    }

    public function test_optional_cap_blocks_fourth_path_and_split_saves_all_matches_and_challenges(): void
    {
        [$event, $draw, $venue, $options] = $this->scenario(false);
        $service = app(EventVenueScheduleService::class);
        $unlimited = $service->preview($event, array_replace($options, ['limit_daily_matches' => false]));
        $this->assertCount(43, $unlimited['matches']);
        $this->assertSame([], $unlimited['unscheduled']);
        $limited = $service->preview($event, $options);
        $this->assertNotEmpty($limited['unscheduled']);
        $this->assertStringContainsString('Maximum 3 matches', implode(' ', array_column($limited['unscheduled'], 'reason')));
        $this->assertSame(0, OrderOfPlay::count());
        foreach ($options['programme']['rounds'] as &$round) if ($round['round'] > 3) $round['day'] = 2;
        unset($round);
        $preview = $service->preview($event, $options);
        $this->assertCount(43, $preview['matches']);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertSame(43, $service->apply($event, $options, $preview['revision'])['count']);
        $stored = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        $this->assertTrue($stored['limit_daily_matches']);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $allOneDay = $options;
        foreach ($allOneDay['programme']['rounds'] as &$round) $round['day'] = 1;
        unset($round);
        $allOneDay['limit_daily_matches'] = false;
        $allOneDay['reschedule_existing'] = true;
        $allOneDay['replan_venue_ids'] = [$venue->id];
        $this->assertNotEmpty($service->preview($event, $allOneDay)['unscheduled']);
    }

    public function test_manual_date_time_and_court_move_succeeds_with_daily_limit_enabled(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $this->admin($event);
        $this->postJson(route('backend.event-venue-schedule.timing-settings', $event), ['duration' => 60, 'player_rest' => 30, 'court_gap' => 5, 'limit_daily_matches' => true])->assertOk();
        $match = collect((array) app(FlexibleMonradService::class)->state($draw)['matches'])->first(fn ($match) => ! array_filter($match['sources'], fn ($source) => isset($source['match'])));
        OrderOfPlay::create(['fixture_id' => $match['id'], 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-30 08:00:00', 'duration_minutes' => 60, 'gap_minutes' => 5]);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event), ['fixture_id' => $match['id'],
            'scheduled_at' => '2026-10-31 09:00:00', 'venue_id' => $venue->id, 'court' => '2', 'duration' => 60, 'court_gap' => 5, 'player_rest' => 30])->assertOk();
        $saved = OrderOfPlay::where('fixture_id', $match['id'])->firstOrFail();
        $this->assertSame('2026-10-31 09:00:00', (string) $saved->time);
        $this->assertSame('2', (string) $saved->court);
        $this->assertSame(1, OrderOfPlay::count());
        $this->assertNotNull(DailyMatchLimit::configured($event));
    }

    public function test_manual_move_replaces_booking_and_saved_cap_cannot_be_bypassed_by_request(): void
    {
        [$event, $draw, $venue, $options] = $this->scenario();
        $this->admin($event);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $service->apply($event, $options, $preview['revision']);
        $target = collect($preview['matches'])->firstWhere('round', 4);
        $old = OrderOfPlay::where('fixture_id', $target['fixture_id'])->firstOrFail()->time;
        $data = ['fixture_id' => $target['fixture_id'], 'scheduled_at' => '2026-10-30 18:00:00', 'venue_id' => $venue->id,
            'court' => '1', 'duration' => 60, 'court_gap' => 5, 'player_rest' => 30, 'limit_daily_matches' => false];
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event), $data)->assertUnprocessable()->assertJsonFragment(['message' => 'Maximum 3 matches per player on 2026-10-30. Move this match or an earlier round to another day; conditional play-offs count too.']);
        $this->assertEquals($old, OrderOfPlay::where('fixture_id', $target['fixture_id'])->first()->time);
        $limit = DailyMatchLimit::load($event);
        $this->assertNull($limit->error('individual:'.$target['fixture_id'], substr($target['scheduled_at'], 0, 10)));
        $this->postJson(route('backend.event-venue-schedule.timing-settings', $event), ['duration' => 60, 'player_rest' => 30, 'court_gap' => 5, 'limit_daily_matches' => false])->assertOk();
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event), $data)->assertOk();
        $this->assertSame(43, OrderOfPlay::count());
        $this->assertFalse((bool) $draw->fresh()->oop_published);
    }

    public function test_limit_uses_real_player_across_registrations_and_draws(): void
    {
        [$event, $draw, $venue, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $service->apply($event, $options, $preview['revision']);
        $entrant = $draw->registrations()->first();
        $otherRegistration = Registration::factory()->create();
        $otherRegistration->players()->attach($entrant->players->first()->id);
        $otherDraw = Draw::factory()->create(['event_id' => $event->id]);
        $otherDraw->venues()->attach($venue->id, ['num_courts' => 6]);
        $fixture = Fixture::factory()->create(['draw_id' => $otherDraw->id, 'registration1_id' => $otherRegistration->id, 'registration2_id' => Registration::factory()->create()->id]);
        $this->assertStringContainsString('Maximum 3 matches', DailyMatchLimit::load($event)->error('individual:'.$fixture->id, '2026-10-30'));
        $before = OrderOfPlay::orderBy('id')->get()->toJson();
        try {
            app(\App\Services\ScheduleEngine::class)->saveFixture($otherDraw, $fixture->id, '2026-10-30 18:00:00', $venue->id, '1', 60);
            $this->fail('Legacy single scheduler ignored daily limit.');
        } catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('Maximum 3 matches', $exception->getMessage()); }
        try {
            app(\App\Services\ScheduleEngine::class)->autoSchedule($otherDraw->id, 60, [$venue->id => ['courts' => range(1, 6)]], '2026-10-30 18:00:00');
            $this->fail('Legacy auto scheduler ignored daily limit.');
        } catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('Maximum 3 matches', $exception->getMessage()); }
        $this->assertSame($before, OrderOfPlay::orderBy('id')->get()->toJson());
    }

    public function test_canonical_single_auto_and_api_bulk_cannot_bypass_saved_limit(): void
    {
        [$event, $draw, $venue, $options] = $this->scenario();
        $this->admin($event);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $service->apply($event, $options, $preview['revision']);
        $target = collect($preview['matches'])->firstWhere('round', 4);
        try {
            app(FlexibleMonradScheduler::class)->saveFixture($draw, $target['fixture_id'], '2026-10-30 18:00:00', $venue->id, '1', 60);
            $this->fail('Canonical manual scheduler ignored daily limit.');
        } catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('Maximum 3 matches', $exception->getMessage()); }
        $before = OrderOfPlay::orderBy('id')->get()->toJson();
        try {
            app(FlexibleMonradScheduler::class)->schedule($draw->id, 60, [$venue->id => ['courts' => range(1, 6)]], '2026-10-30 08:00:00');
            $this->fail('Canonical auto scheduler ignored daily limit.');
        } catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('Maximum 3 matches', $exception->getMessage()); }
        $this->assertSame($before, OrderOfPlay::orderBy('id')->get()->toJson());
        $this->postJson('/api/draws/'.$draw->id.'/schedule', ['items' => [['fixture_id' => $target['fixture_id'], 'start_time' => '2026-10-30 18:00:00', 'court' => '1', 'venue_id' => $venue->id]]])->assertUnprocessable();
        $this->assertSame($before, OrderOfPlay::orderBy('id')->get()->toJson());
    }

    public function test_settings_and_manual_moves_remain_event_authorized_and_modal_is_available(): void
    {
        [$event, $draw, $venue, $options] = $this->scenario();
        $this->admin($event);
        $response = $this->get(route('backend.event-venue-schedule.index', $event))->assertOk()
            ->assertSee('Maximum 3 matches per player per day')->assertSee('reschedule-match-form', false)->assertSee('Save new time');
        if ($snapshot = getenv('SCHEDULING_LIMIT_SNAPSHOT')) {
            if (! is_dir(dirname($snapshot))) mkdir(dirname($snapshot), 0777, true);
            file_put_contents($snapshot, $response->getContent());
        }
        $other = Event::factory()->create();
        $this->postJson(route('backend.event-venue-schedule.timing-settings', $other), ['duration' => 60, 'player_rest' => 30, 'court_gap' => 5, 'limit_daily_matches' => true])->assertForbidden();
    }
}
