<?php

namespace Tests\Feature\Draw;

use App\Models\{Category, CategoryEvent, Draw, Event, OrderOfPlay, Registration, Venue};
use App\Services\Draw\FlexibleMonradService;
use App\Services\Scheduling\{EventVenueScheduleService, ScheduleProgramme};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndividualMonradProgrammeTest extends TestCase
{
    use RefreshDatabase;

    private function canonicalJson(array $value): array
    {
        foreach ($value as &$item) if (is_array($item)) $item = $this->canonicalJson($item);
        unset($item);
        if (! array_is_list($value)) ksort($value);
        return $value;
    }

    private function scenario(int $size = 8, bool $bye = false): array
    {
        $event = Event::factory()->create(['start_date' => '2026-10-09', 'end_date' => '2026-10-11']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'Under 19 Girls'])->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'drawName' => 'Selection trial', 'gender' => null]);
        $draw->settings()->create(['workflow' => 'monrad']);
        $venue = Venue::forceCreate(['name' => 'Synthetic trial courts']);
        $draw->venues()->attach($venue->id, ['num_courts' => $size / 2]);
        $registrations = Registration::factory()->count($size)->create();
        $slots = [];
        foreach ($registrations as $i => $registration) {
            $registration->categoryEvents()->attach($category->id, ['status' => 'registered', 'payment_status_id' => 1]);
            $path = strtr(str_pad(decbin($i), (int) log($size, 2), '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = $bye && $i === $size - 1 ? ['type' => 'bye'] : ['type' => 'player', 'id' => $registration->id];
        }
        $compiler = app(FlexibleMonradService::class);
        $compiler->save($draw, ['size' => $size, 'slots' => $slots], 0);
        $record = $compiler->generate($draw, 1);
        $draw = $draw->fresh();
        $roundCount = (int) log($size, 2);
        $days = array_map(fn ($date) => ['start' => '2026-10-'.$date.' 08:00:00', 'end' => '2026-10-'.$date.' 18:00:00'], ['09', '10', '11']);
        $rounds = array_map(fn ($round) => ['draw_id' => $draw->id, 'round' => $round, 'day' => min(3, $round), 'sequence' => $round], range(1, $roundCount));
        return [$event, $draw, $record, ['start' => $days[0]['start'], 'end' => $days[2]['end'], 'duration' => 30, 'wave_minutes' => 30, 'court_gap' => 0, 'player_rest' => 20, 'draw_ids' => [$draw->id], 'programme' => compact('days', 'rounds')]];
    }

    public function test_full_8_16_32_draws_include_placement_rounds_and_persist_without_publication(): void
    {
        foreach ([8 => 12, 16 => 32, 32 => 80] as $size => $count) {
            [$event, $draw, $record, $options] = $this->scenario($size);
            $service = app(EventVenueScheduleService::class);
            $preview = $service->preview($event, $options);
            $this->assertCount($count, $preview['matches']);
            $this->assertSame([], $preview['unscheduled']);
            $this->assertSame(range(1, (int) log($size, 2)), $service->availableDrawRounds(collect([$draw]), true)[$draw->id]);
            $matches = collect($preview['matches'])->keyBy('fixture_id');
            $state = app(FlexibleMonradService::class)->state($draw);
            foreach ($state['matches'] as $match) foreach ($match['sources'] as $source) {
                if (!isset($source['match'])) continue;
                $feeder = $matches[$record->fixture_map[$source['match']]];
                $target = $matches[$match['id']];
                $this->assertGreaterThan($feeder['round'], $target['round']);
                $this->assertGreaterThanOrEqual(strtotime($feeder['scheduled_at']) + 50 * 60, strtotime($target['scheduled_at']));
            }
            $this->assertSame(0, OrderOfPlay::whereIn('fixture_id', $matches->keys())->count());
            $this->assertSame($count, $service->apply($event, $options, $preview['revision'])['count']);
            $saved = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
            $this->assertSame($this->canonicalJson($preview['input']['programme']), $this->canonicalJson($saved['programme_settings']['monrad:19']['programme']));
            $this->assertFalse((bool) $draw->fresh()->oop_published);
            $this->assertSame([], $service->preview($event, $options)['matches']);
        }
    }

    public function test_admin_can_load_builder_and_save_monrad_round_courts_without_publication(): void
    {
        [$event, $draw, $record, $options] = $this->scenario();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = \App\Models\User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $url = route('backend.event-venue-schedule.index', $event);
        $this->actingAs($admin);
        $response = $this->get($url)->assertOk()->assertSee('Individual Monrad · Under 19')->assertSee('programme_rounds', false);
        if (getenv('MONRAD_PROGRAMME_SNAPSHOT')) {
            file_put_contents(getenv('MONRAD_PROGRAMME_SNAPSHOT'), $response->getContent());
        }
        $venue = $draw->venues->first();
        $setup = ['draw_id' => $draw->id, 'round' => 3, 'venue_ids' => [$venue->id], 'court_allocations' => [['venue_id' => $venue->id, 'court_labels' => ['1', '2']]], 'rank_venue_preferences' => []];
        $assignmentUrl = route('backend.event-venue-schedule.assignments', $event);
        $this->postJson($assignmentUrl, ['setup_only' => true, 'round_venue_setups' => [$setup]])->assertOk();
        $this->assertSame(0, OrderOfPlay::count());
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $setup['rank_venue_preferences'] = [['min_rank' => 1, 'max_rank' => 4, 'venue_id' => $venue->id]];
        $this->postJson($assignmentUrl, ['setup_only' => true, 'round_venue_setups' => [$setup]])->assertUnprocessable();
        $draw->update(['locked' => true]);
        $setup['rank_venue_preferences'] = [];
        $this->postJson($assignmentUrl, ['setup_only' => true, 'round_venue_setups' => [$setup]])->assertUnprocessable();
    }

    public function test_byes_do_not_receive_court_bookings(): void
    {
        [$event, $draw, $record, $options] = $this->scenario(8, true);
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $automatic = collect(app(FlexibleMonradService::class)->state($draw)['matches'])->where('automatic', true)->pluck('id');
        $this->assertLessThan(12, count($preview['matches']));
        $this->assertSame([], $preview['unscheduled']);
        $this->assertEmpty(collect($preview['matches'])->pluck('fixture_id')->intersect($automatic));
    }

    public function test_round_courts_follow_placement_depth_while_general_round_filters_keep_fixture_rounds(): void
    {
        [$event, $draw, $record, $options] = $this->scenario();
        $venue = $draw->venues->first();
        app(\App\Services\Scheduling\RoundVenueSetup::class)->save($event, [['draw_id' => $draw->id, 'round' => 3, 'venue_ids' => [$venue->id], 'court_allocations' => [['venue_id' => $venue->id, 'court_labels' => ['4']]], 'rank_venue_preferences' => []]], null);
        $service = app(EventVenueScheduleService::class);
        $depths = $service->programmeFixtureRounds($draw);
        $programme = $service->preview($event, $options);
        foreach ($programme['matches'] as $row) if ($depths[$row['fixture_id']] === 3) $this->assertSame('4', $row['court']);
        unset($options['programme']);
        $options['end'] = '2026-10-09 18:00:00';
        $options['draw_rounds'] = [['draw_id' => $draw->id, 'rounds' => [1]]];
        $general = $service->preview($event, $options);
        $raw = $draw->drawFixtures->keyBy('id');
        foreach ($general['matches'] as $row) {
            $this->assertSame(1, (int) $raw[$row['fixture_id']]->round);
            if ($depths[$row['fixture_id']] === 3) $this->assertSame('4', $row['court']);
        }
        $this->assertNotEmpty($general['matches']);
    }

    public function test_individual_group_excludes_same_age_team_and_rejects_unsupported_workflow(): void
    {
        [$event, $draw, $record, $options] = $this->scenario();
        $team = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Under 19 Boys']);
        $team->forceFill(['team_category_id' => 1])->save();
        $this->assertSame('19', app(ScheduleProgramme::class)->key($team));
        $this->assertSame('monrad:19', app(ScheduleProgramme::class)->key($draw));
        $this->assertCount(12, app(EventVenueScheduleService::class)->preview($event, $options)['matches']);
        $draw->settings()->update(['workflow' => 'playoffs']);
        $this->expectException(\InvalidArgumentException::class);
        app(EventVenueScheduleService::class)->preview($event, $options);
    }

    public function test_stale_preview_and_foreign_draw_leave_schedule_unchanged(): void
    {
        [$event, $draw, $record, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $options['programme']['days'][2]['end'] = '2026-10-11 17:00:00';
        try { $service->apply($event, $options, $preview['revision']); $this->fail('Stale preview accepted'); }
        catch (\InvalidArgumentException $exception) { $this->assertSame(0, OrderOfPlay::count()); }
        $foreign = Draw::factory()->create();
        $options['programme']['rounds'][0]['draw_id'] = $foreign->id;
        $this->expectException(\InvalidArgumentException::class);
        $service->preview($event, $options);
    }
}
