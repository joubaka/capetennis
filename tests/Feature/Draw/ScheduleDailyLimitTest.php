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

    private function canonicalJson(array $value): array
    {
        foreach ($value as &$item) if (is_array($item)) $item = $this->canonicalJson($item);
        unset($item);
        if (! array_is_list($value)) ksort($value);
        return $value;
    }

    private function scenario(bool $split = true, int $size = 16, bool $bye = false): array
    {
        $event = Event::factory()->create(['start_date' => '2026-10-30', 'end_date' => '2026-11-01']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'Under 19 Boys'])->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'drawName' => 'Under 19 Boys']);
        $draw->settings()->create(['workflow' => 'feed_in_monrad', 'playoff_config' => ['challenge_playoffs' => true]]);
        $venue = Venue::forceCreate(['name' => 'Daily limit test courts']);
        $draw->venues()->attach($venue->id, ['num_courts' => 6]);
        $slots = [];
        foreach (Registration::factory()->count($size)->create() as $index => $registration) {
            $registration->categoryEvents()->attach($category->id, ['status' => 'registered', 'payment_status_id' => 1]);
            $registration->players()->attach(Player::factory()->create()->id);
            $path = strtr(str_pad(decbin($index), (int) log($size, 2), '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = $bye && $index === $size - 1 ? ['type' => 'bye'] : ['type' => 'player', 'id' => $registration->id];
        }
        $compiler = app(FlexibleMonradService::class);
        $compiler->save($draw, ['size' => $size, 'slots' => $slots], 0);
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
        $draw->update(['published' => true]);
        $publication = app(\App\Services\Scheduling\SchedulePublicationService::class);
        $this->assertSame(1, $publication->publish($event, ['draw_id' => $draw->id]));
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event), ['fixture_id' => $match['id'],
            'scheduled_at' => '2026-10-31 09:00:00', 'venue_id' => $venue->id, 'court' => '2', 'duration' => 60, 'court_gap' => 5, 'player_rest' => 30])->assertOk();
        $saved = OrderOfPlay::where('fixture_id', $match['id'])->firstOrFail();
        $this->assertSame('2026-10-31 09:00:00', (string) $saved->time);
        $this->assertSame('2', (string) $saved->court);
        $this->assertSame(1, OrderOfPlay::count());
        $this->assertNotNull(DailyMatchLimit::configured($event));
        $this->assertSame('2026-10-30 08:00:00', $publication->publishedRows($event)->sole()['scheduled_at']);
        $this->assertSame('2026-10-31 09:00:00', $publication->workingRows($event)->sole()['scheduled_at']);
        $publication->publish($event, ['draw_id' => $draw->id]);
        $this->assertSame('2026-10-31 09:00:00', $publication->publishedRows($event)->sole()['scheduled_at']);
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

    public function test_manual_move_uses_requested_court_gap_in_both_validations(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $this->admin($event);
        $opening = collect((array) app(FlexibleMonradService::class)->state($draw)['matches'])
            ->filter(fn ($match) => ! array_filter($match['sources'], fn ($source) => isset($source['match'])))->take(2)->values();
        foreach ($opening as $index => $match) {
            OrderOfPlay::create(['fixture_id' => $match['id'], 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court' => '1',
                'time' => $index ? '2026-10-30 09:00:00' : '2026-10-30 08:00:00', 'duration_minutes' => 60, 'gap_minutes' => $index ? 0 : 5]);
        }
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event), ['fixture_id' => $opening[0]['id'],
            'scheduled_at' => '2026-10-30 08:00:00', 'venue_id' => $venue->id, 'court' => '1', 'duration' => 60, 'court_gap' => 0, 'player_rest' => 30])->assertOk();
        $this->assertSame(0, (int) OrderOfPlay::where('fixture_id', $opening[0]['id'])->firstOrFail()->gap_minutes);
        $this->assertSame(2, OrderOfPlay::count());
        $this->assertFalse((bool) $draw->fresh()->oop_published);
    }

    public function test_manual_monrad_feeder_requires_player_rest_and_preserves_later_booking(): void
    {
        [$event, $draw, $venue] = $this->scenario();
        $this->admin($event);
        $matches = (array) app(FlexibleMonradService::class)->state($draw)['matches'];
        $target = collect($matches)->first(fn ($match) => $match['section'] === 'Main draw' && (int) $match['round'] === 2);
        foreach ($target['sources'] as $index => $source) {
            $feeder = $matches[$source['match']];
            OrderOfPlay::create(['fixture_id' => $feeder['id'], 'draw_id' => $draw->id, 'venue_id' => $venue->id,
                'court' => (string) ($index + 1), 'time' => '2026-10-30 08:00:00', 'duration_minutes' => 60, 'gap_minutes' => 5]);
        }
        $data = ['fixture_id' => $target['id'], 'scheduled_at' => '2026-10-30 09:05:00', 'venue_id' => $venue->id,
            'court' => '3', 'duration' => 60, 'court_gap' => 5, 'player_rest' => 30];
        $url = route('backend.event-venue-schedule.manual-assignment', $event);
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonFragment(['message' => 'This match must start after its qualifying matches finish.']);
        $this->assertSame(2, OrderOfPlay::count());
        $this->postJson($url, array_replace($data, ['scheduled_at' => '2026-10-30 09:30:00']))->assertOk();
        $data['fixture_id'] = $matches[$target['sources'][0]['match']]['id'];
        $data['scheduled_at'] = '2026-10-30 08:30:00'; $data['court'] = '1';
        $this->postJson($url, $data)->assertUnprocessable();
        $this->assertSame('2026-10-30 08:00:00', (string) OrderOfPlay::where('fixture_id', $data['fixture_id'])->firstOrFail()->time);
        $this->assertSame(3, OrderOfPlay::count());
    }

    public function test_manual_legacy_unresolved_feeder_requires_player_rest(): void
    {
        [$event, , $venue] = $this->scenario();
        $this->admin($event);
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $draw->venues()->attach($venue->id, ['num_courts' => 6]);
        $target = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 2, 'registration1_id' => null, 'registration2_id' => null, 'match_status' => 0]);
        foreach ([1, 2] as $court) {
            $feeder = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'parent_fixture_id' => $target->id,
                'registration1_id' => Registration::factory()->create()->id, 'registration2_id' => Registration::factory()->create()->id, 'match_status' => 0]);
            OrderOfPlay::create(['fixture_id' => $feeder->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
                'court' => (string) $court, 'time' => '2026-10-30 08:00:00', 'duration_minutes' => 60, 'gap_minutes' => 5]);
        }
        $data = ['fixture_id' => $target->id, 'scheduled_at' => '2026-10-30 09:05:00', 'venue_id' => $venue->id,
            'court' => '3', 'duration' => 60, 'court_gap' => 5, 'player_rest' => 30];
        $url = route('backend.event-venue-schedule.manual-assignment', $event);
        $this->postJson($url, $data)->assertUnprocessable();
        $this->postJson($url, array_replace($data, ['scheduled_at' => '2026-10-30 09:30:00']))->assertOk();
        $this->assertSame(3, OrderOfPlay::count());
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
            ->assertSee('Maximum 3 matches per player per day')->assertSee('reschedule-match-form', false)->assertSee('Save new time')
            ->assertSee('Search for a venue…')->assertSee('minimumResultsForSearch:0', false);
        if ($snapshot = getenv('SCHEDULING_LIMIT_SNAPSHOT')) {
            if (! is_dir(dirname($snapshot))) mkdir(dirname($snapshot), 0777, true);
            file_put_contents($snapshot, $response->getContent());
        }
        $other = Event::factory()->create();
        $this->postJson(route('backend.event-venue-schedule.timing-settings', $other), ['duration' => 60, 'player_rest' => 30, 'court_gap' => 5, 'limit_daily_matches' => true])->assertForbidden();
    }

    public function test_round_details_match_actual_generated_fixtures_for_all_sizes_and_byes(): void
    {
        $service = app(EventVenueScheduleService::class);
        foreach ([[8, false], [16, false], [32, false], [16, true]] as [$size, $bye]) {
            [, $draw] = $this->scenario(size: $size, bye: $bye);
            $state = (array) app(FlexibleMonradService::class)->state($draw)['matches'];
            $playable = collect($state)->reject(fn ($match) => $match['automatic']);
            $rounds = $service->programmeFixtureRounds($draw);
            $details = $service->programmeRoundDetails($draw);
            $ids = [];
            foreach ($details as $round => $groups) foreach ($groups as $group) {
                $this->assertSame($group['count'], count($group['matches']));
                foreach ($group['matches'] as $match) {
                    $this->assertSame((int) $round, $rounds[$match['fixture_id']]);
                    $ids[] = $match['fixture_id'];
                }
            }
            $this->assertEqualsCanonicalizing($playable->pluck('id')->all(), $ids);
            $this->assertCount(count(array_unique($ids)), $ids);
            if ($size === 16 && ! $bye) {
                $this->assertContains('Main draw · Semifinals', array_column($details[3], 'label'));
                $this->assertContains('Positions 13–16 semifinals', array_column($details[3], 'label'));
                $this->assertContains('Positions 13–14 final', array_column($details[4], 'label'));
                $this->assertContains('Conditional play-off · Positions 2–3', array_column($details[6], 'label'));
            }
        }
    }

    public function test_position_group_and_match_overrides_preview_apply_and_restore_with_complete_coverage(): void
    {
        [$event, $draw, , $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $details = $service->programmeRoundDetails($draw);
        $placement = collect($details[4])->firstWhere('label', 'Positions 13–14 final')['matches'][0]['fixture_id'];
        $overrides = [['draw_id' => $draw->id, 'fixture_id' => $placement, 'day' => 3, 'sequence' => 1]];
        foreach ($details[6] as $group) foreach ($group['matches'] as $match) $overrides[] = ['draw_id' => $draw->id, 'fixture_id' => $match['fixture_id'], 'day' => 3, 'sequence' => 2];
        $options['programme']['matches'] = $overrides;
        $preview = $service->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount(43, $preview['matches']);
        $rows = collect($preview['matches'])->keyBy('fixture_id');
        $this->assertStringStartsWith('2026-11-01', $rows[$placement]['scheduled_at']);
        foreach ($overrides as $override) $this->assertSame(3, $rows[$override['fixture_id']]['programme_day']);
        $this->assertSame(43, $service->apply($event, $options, $preview['revision'])['count']);
        $stored = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        $this->assertSame($this->canonicalJson($preview['input']['programme']['matches']), $this->canonicalJson($stored['programme_settings']['individual:'.$draw->id]['programme']['matches']));
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $audit = app(\App\Services\Scheduling\ScheduleAuditService::class)->audit($event, ['draw_id' => $draw->id]);
        $this->assertNotContains('outside_programme', array_column($audit['issues'], 'code'));
        OrderOfPlay::where('fixture_id', $placement)->update(['time' => '2026-11-01 20:30:00']);
        $outside = app(\App\Services\Scheduling\ScheduleAuditService::class)->audit($event, ['draw_id' => $draw->id]);
        $this->assertContains('outside_programme', array_column($outside['issues'], 'code'));
    }

    public function test_match_override_rejects_foreign_duplicate_invalid_and_reversed_dependencies_without_writes(): void
    {
        [$event, $draw, , $options] = $this->scenario();
        $this->admin($event);
        $matches = collect((array) app(FlexibleMonradService::class)->state($draw)['matches']);
        $target = $matches->first(fn ($match) => $match['section'] === 'Main draw' && $match['round'] === 2);
        $row = ['draw_id' => $draw->id, 'fixture_id' => $target['id'], 'day' => 1, 'sequence' => 2];
        $foreign = Fixture::factory()->create(['draw_id' => Draw::factory()->create()->id]);
        $url = route('backend.event-venue-schedule.preview', $event);
        foreach ([[$row, $row], [array_replace($row, ['fixture_id' => $foreign->id])], [array_replace($row, ['day' => 4])], [array_replace($row, ['sequence' => 0])], [array_replace($row, ['fixture_kind' => 'team'])]] as $overrides) {
            $request = $options; $request['programme']['matches'] = $overrides;
            $this->postJson($url, $request)->assertUnprocessable();
        }
        $reversed = $options;
        $reversed['programme']['matches'] = [array_replace($row, ['fixture_id' => $matches->get($target['sources'][0]['match'])['id'], 'day' => 2])];
        $this->postJson($url, $reversed)->assertUnprocessable();
        $this->assertSame(0, OrderOfPlay::count());
    }

    public function test_same_phase_match_override_keeps_feeder_rest_and_changed_override_invalidates_revision(): void
    {
        [$event, $draw, , $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $matches = (array) app(FlexibleMonradService::class)->state($draw)['matches'];
        $target = collect($matches)->first(fn ($match) => $match['section'] === 'Main draw' && $match['round'] === 2);
        $options['programme']['matches'] = [['draw_id' => $draw->id, 'fixture_id' => $target['id'], 'day' => 1, 'sequence' => 1]];
        $preview = $service->preview($event, $options);
        $rows = collect($preview['matches'])->keyBy('fixture_id');
        foreach ($target['sources'] as $source) {
            $feeder = $rows[$matches[$source['match']]['id']];
            $this->assertGreaterThanOrEqual(strtotime($feeder['scheduled_at']) + 90 * 60, strtotime($rows[$target['id']]['scheduled_at']));
        }
        $changed = $options; $changed['programme']['matches'][0]['sequence'] = 2;
        $this->assertNotSame($preview['revision'], $service->preview($event, $changed)['revision']);
        try { $service->apply($event, $changed, $preview['revision']); $this->fail('Changed match override accepted with stale revision.'); }
        catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('fresh preview', $exception->getMessage()); }
        $this->assertSame(0, OrderOfPlay::count());
    }

    public function test_round_only_event_venues_allow_manual_moves_without_changing_draw_defaults(): void
    {
        [$event, $draw, $venue, $options] = $this->scenario();
        $this->admin($event);
        $draw->venues()->detach($venue->id);
        $event->venues()->attach($venue->id, ['num_courts' => 6]);
        $draw = $draw->fresh();
        $setups = array_map(fn ($round) => ['draw_id' => $draw->id, 'round' => $round['round'], 'venue_ids' => [$venue->id],
            'court_allocations' => [['venue_id' => $venue->id, 'court_labels' => ['1','2','3','4','5','6']]], 'rank_venue_preferences' => []], $options['programme']['rounds']);
        app(\App\Services\Scheduling\RoundVenueSetup::class)->save($event, $setups, auth()->id());
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertSame(43, $service->apply($event, $options, $preview['revision'])['count']);
        $target = collect($preview['matches'])->firstWhere('round', 6);
        $data = ['fixture_id' => $target['fixture_id'], 'scheduled_at' => '2026-11-01 08:00:00', 'venue_id' => $venue->id,
            'court' => '2', 'duration' => 60, 'court_gap' => 5, 'player_rest' => 30];
        $url = route('backend.event-venue-schedule.manual-assignment', $event);
        $this->postJson($url, array_replace($data, ['court' => '7']))->assertUnprocessable();
        $otherVenue = Venue::forceCreate(['name' => 'Unallocated event venue']);
        $event->venues()->attach($otherVenue->id, ['num_courts' => 6]);
        $this->postJson($url, array_replace($data, ['venue_id' => $otherVenue->id]))->assertUnprocessable();
        $this->postJson($url, $data)->assertOk();
        $this->assertSame('2026-11-01 08:00:00', (string) OrderOfPlay::where('fixture_id', $target['fixture_id'])->firstOrFail()->time);
        foreach (range(1, 6) as $court) DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id, 'label' => (string) $court, 'active' => $court !== 2, 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson($url, array_replace($data, ['scheduled_at' => '2026-11-01 09:30:00']))->assertUnprocessable();
        $this->assertSame(0, $draw->venues()->count());
        $this->assertSame(43, OrderOfPlay::count());
        $stored = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        $stored['round_venue_setups'] = array_values(array_filter($stored['round_venue_setups'], fn ($row) => $row['round'] !== 1));
        DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->update(['options' => json_encode($stored)]);
        $opening = collect($preview['matches'])->firstWhere('round', 1);
        $this->postJson($url, array_replace($data, ['fixture_id' => $opening['fixture_id'], 'scheduled_at' => $opening['scheduled_at'], 'court' => $opening['court']]))->assertUnprocessable();
        $this->assertSame(0, $draw->venues()->count());
    }
}
