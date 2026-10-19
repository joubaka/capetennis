<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, TeamFixture, User, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FullDayVenueScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $event = Event::factory()->create();
        $venues = collect(['School', 'Club', 'Primary'])->map(fn ($name) => Venue::forceCreate(['name'=>$name]));
        foreach ($venues as $venue) $event->venues()->attach($venue->id, ['num_courts'=>1]);
        $draws = collect([1, 2, 3])->map(fn ($i) => Draw::factory()->create(['event_id'=>$event->id, 'team_category_id'=>1]));
        foreach ($draws as $draw) {
            $draw->venues()->attach($venues[0]->id, ['num_courts'=>1]);
            foreach ([1, 2] as $rubber) TeamFixture::create(['draw_id'=>$draw->id, 'round_nr'=>1, 'tie_nr'=>1,
                'match_nr'=>$rubber, 'rubber_sequence'=>$rubber, 'match_status'=>0]);
        }
        $options = ['full_day_scope'=>'2026-10-23', 'draw_ids'=>$draws->pluck('id')->all(), 'venue_ids'=>$venues->pluck('id')->all(),
            'draw_rounds'=>$draws->map(fn ($draw) => ['draw_id'=>$draw->id, 'rounds'=>[1]])->all(),
            'day_court_labels'=>$venues->mapWithKeys(fn ($venue) => [$venue->id=>['1']])->all(),
            'start'=>'2026-10-23 08:00', 'end'=>'2026-10-23 10:00', 'duration'=>60, 'player_rest'=>0, 'court_gap'=>0];
        return [$event, $draws, $venues, $options];
    }

    public function test_full_day_distributes_complete_ties_across_venues_without_changing_other_days_or_defaults(): void
    {
        [$event, $draws, $venues, $options] = $this->context();
        $other = TeamFixture::create(['draw_id'=>$draws[0]->id, 'round_nr'=>2, 'tie_nr'=>2, 'match_nr'=>3, 'match_status'=>0,
            'scheduled_at'=>'2026-10-24 09:00', 'venue_id'=>$venues[0]->id, 'court_label'=>'1']);
        $draft = ['rank_venue_preferences'=>[['draw_ids'=>$draws->pluck('id')->all(), 'min_rank'=>1, 'max_rank'=>100, 'venue_id'=>$venues[0]->id]],
            'round_venue_setups'=>$draws->map(fn ($draw) => ['draw_id'=>$draw->id, 'round'=>1, 'venue_ids'=>[$venues[0]->id],
                'court_allocations'=>[['venue_id'=>$venues[0]->id, 'court_labels'=>['1']]], 'rank_venue_preferences'=>[]])->all()];
        DB::table('event_venue_schedule_drafts')->insert(['event_id'=>$event->id, 'options'=>json_encode($draft), 'created_at'=>now(), 'updated_at'=>now()]);
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $options);
        $this->assertCount(6, $preview['matches']);
        $this->assertEmpty($preview['unscheduled']);
        $this->assertCount(3, collect($preview['matches'])->pluck('venue_id')->unique());
        foreach (collect($preview['matches'])->groupBy('draw_id') as $tie) $this->assertCount(1, $tie->pluck('venue_id')->unique());
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->whereDate('scheduled_at', '2026-10-23')->count());
        $this->assertSame(6, $scheduler->apply($event, $options, $preview['revision'])['count']);
        $this->assertSame('2026-10-24 09:00', $other->fresh()->scheduled_at->format('Y-m-d H:i'));
        $this->assertDatabaseCount('team_fixtures', 7);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->assertEquals($draft, json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true));
    }

    public function test_incomplete_day_cannot_save_even_when_partial_save_is_requested_and_revision_changes(): void
    {
        [$event, $draws, $venues, $options] = $this->context();
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $options);
        $options['end'] = '2026-10-23 09:00';
        $options['allow_partial'] = true;
        $this->assertNotEmpty($scheduler->preview($event, $options)['unscheduled']);
        try { $scheduler->apply($event, $options, $preview['revision']); $this->fail('Expected stale preview rejection'); }
        catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('fresh preview', $exception->getMessage()); }
        $preview = $scheduler->preview($event, $options);
        try { $scheduler->apply($event, $options, $preview['revision']); $this->fail('Expected incomplete day rejection'); }
        catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('unscheduled', $exception->getMessage()); }
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_unselected_historical_split_tie_does_not_block_the_day_and_played_rubber_anchors_its_tie(): void
    {
        [$event, $draws, $venues, $options] = $this->context();
        foreach ([0, 1] as $index) TeamFixture::create(['draw_id'=>$draws[0]->id, 'round_nr'=>2, 'tie_nr'=>2,
            'match_nr'=>3+$index, 'match_status'=>1, 'scheduled_at'=>'2026-10-22 08:00', 'venue_id'=>$venues[$index]->id, 'court_label'=>'1']);
        $played = TeamFixture::where('draw_id', $draws[0]->id)->where('round_nr', 1)->first();
        $played->update(['match_status'=>1, 'scheduled_at'=>'2026-10-23 08:00', 'venue_id'=>$venues[2]->id, 'court_label'=>'1', 'duration_min'=>60]);
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $options);
        $this->assertEmpty($preview['unscheduled']);
        $this->assertCount(5, $preview['matches']);
        $this->assertSame($venues[2]->id, collect($preview['matches'])->firstWhere('draw_id', $draws[0]->id)['venue_id']);
        $scheduler->apply($event, $options, $preview['revision']);
        $this->assertSame(2, TeamFixture::whereDate('scheduled_at', '2026-10-22')->count());
        $this->assertSame('2026-10-23 08:00', $played->fresh()->scheduled_at->format('Y-m-d H:i'));
    }

    public function test_editor_requires_event_access_and_validates_rounds_and_foreign_venues(): void
    {
        [$event, $draws, $venues, $options] = $this->context();
        Role::firstOrCreate(['name'=>'admin', 'guard_name'=>'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id, 'user_id'=>$admin->id]);
        $url = route('backend.event-venue-schedule.calendar.full-day', ['event'=>$event->id, 'date'=>'2026-10-23']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Every tie stays at one venue');
        $payload = ['date'=>'2026-10-23', 'draw_ids'=>$options['draw_ids'], 'rounds'=>$draws->mapWithKeys(fn ($draw) => [$draw->id=>[1]])->all(),
            'venue_ids'=>$options['venue_ids'], 'court_labels'=>$options['day_court_labels'], 'start_time'=>'08:00', 'end_time'=>'10:00',
            'duration'=>60, 'player_rest'=>0, 'court_gap'=>0, 'action'=>'preview'];
        $response = $this->post($url, $payload)->assertOk()->assertSee('Proposed full day');
        $response->assertSee('Tie / matchup')->assertSee('Tie 1')->assertSee('Whole tie');
        if (getenv('CT_DAY_EDIT_BROWSER_FIXTURE') === '1') file_put_contents(storage_path('framework/testing/full-day.html'), $response->getContent());
        $revision = $response->viewData('preview')['revision'];
        $this->post($url, array_replace($payload, ['action'=>'save', 'revision'=>str_repeat('0', 64)]))->assertRedirect()->assertSessionHasErrors('schedule');
        $this->post($url, array_replace($payload, ['venue_ids'=>[Venue::forceCreate(['name'=>'Foreign'])->id]]))->assertRedirect()->assertSessionHasErrors('schedule');
        $this->post($url, array_replace($payload, ['rounds'=>[$draws[0]->id=>[1]]]))->assertRedirect()->assertSessionHasErrors('schedule');
        $this->get(route('backend.event-venue-schedule.calendar.full-day', ['event'=>Event::factory()->create()->id, 'date'=>'2026-10-23']))->assertForbidden();
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
        $this->post($url, array_replace($payload, ['action'=>'save', 'revision'=>$revision]))->assertRedirect(route('backend.event-venue-schedule.calendar', ['event'=>$event->id, 'date'=>'2026-10-23']));
        $this->assertSame(6, TeamFixture::whereDate('scheduled_at', '2026-10-23')->count());
    }

    public function test_day_court_pool_uses_event_physical_capacity_consistently_for_any_draw_subset(): void
    {
        [$event, $draws, $venues, $options] = $this->context();
        $draws[1]->venues()->updateExistingPivot($venues[0]->id, ['num_courts'=>4]);
        $scheduler = app(EventVenueScheduleService::class);
        $this->assertSame(['1', '2', '3', '4'], $scheduler->fullDayCourtLabels($event)[$venues[0]->id]);
        $options['draw_ids'] = [$draws[0]->id];
        $options['draw_rounds'] = [['draw_id'=>$draws[0]->id, 'rounds'=>[1]]];
        $options['venue_ids'] = [$venues[0]->id];
        $options['day_court_labels'] = [$venues[0]->id=>['3', '4']];
        $preview = $scheduler->preview($event, $options);
        $this->assertEmpty($preview['unscheduled']);
        $this->assertSame(['3', '4'], collect($preview['matches'])->pluck('court')->sort()->values()->all());
        DB::table('event_venue_courts')->insert(['event_id'=>$event->id, 'venue_id'=>$venues[0]->id, 'label'=>'Closed', 'active'=>false]);
        $this->assertSame([], $scheduler->fullDayCourtLabels($event)[$venues[0]->id]);
    }

    private function splitContext(): array
    {
        [$event, $draws, $venues, $options] = $this->context();
        $venue = $venues[0];
        $event->venues()->updateExistingPivot($venue->id, ['num_courts'=>2]);
        $options['draw_ids'] = $draws->take(2)->pluck('id')->all();
        $options['draw_rounds'] = $draws->take(2)->map(fn ($draw) => ['draw_id'=>$draw->id, 'rounds'=>[1]])->all();
        $options['venue_ids'] = [$venue->id];
        $options['day_court_labels'] = [$venue->id=>['1', '2']];
        $options['day_venue_groups'] = [$venue->id=>['A'=>['1'], 'B'=>['2']]];
        return [$event, $draws, $venue, $options];
    }

    public function test_two_court_groups_schedule_simultaneous_whole_ties_on_one_physical_venue_and_record_groups(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $options);
        $this->assertEmpty($preview['unscheduled']);
        $this->assertCount(4, $preview['matches']);
        $this->assertSame([$venue->id], collect($preview['matches'])->pluck('venue_id')->unique()->all());
        $this->assertEqualsCanonicalizing(['A', 'B'], collect($preview['matches'])->pluck('venue_group')->unique()->all());
        $this->assertCount(2, collect($preview['matches'])->where('scheduled_at', '2026-10-23 08:00:00'));
        foreach (collect($preview['matches'])->groupBy('draw_id') as $tie) {
            $this->assertCount(1, $tie->pluck('venue_group')->unique());
            $this->assertCount(1, $tie->pluck('court')->unique());
        }
        $this->assertSame(4, $scheduler->apply($event, $options, $preview['revision'])['count']);
        $this->assertDatabaseCount('venues', 3);
        $this->assertDatabaseCount('team_fixtures', 6);
        $audit = \App\Models\DrawAuditLog::where('draw_id', $draws[0]->id)->latest('id')->first()->payload;
        $this->assertEquals($options['day_venue_groups'], $audit['full_day_options']['day_venue_groups']);
        $this->assertNotNull($audit['assignments'][0]['after']['venue_group']);
        $this->assertSame(0, TeamFixture::where('draw_id', $draws[2]->id)->whereNotNull('scheduled_at')->count());
    }

    public function test_split_groups_reject_overlap_missing_foreign_unselected_and_empty_courts_and_change_revision(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        $scheduler = app(EventVenueScheduleService::class);
        foreach ([['A'=>['1'], 'B'=>['1', '2']], ['A'=>[], 'B'=>['1', '2']], ['A'=>['1'], 'B'=>['3']],
            ['A'=>['1'], 'C'=>['2']], ['A'=>['1'], 'B'=>['2', '2']]] as $groups) {
            try { $scheduler->preview($event, array_replace($options, ['day_venue_groups'=>[$venue->id=>$groups]])); $this->fail('Expected invalid groups rejection'); }
            catch (\InvalidArgumentException $exception) { $this->assertNotEmpty($exception->getMessage()); }
        }
        try { $scheduler->preview($event, array_replace($options, ['day_venue_groups'=>[Venue::forceCreate(['name'=>'Foreign'])->id=>['A'=>['1'], 'B'=>['2']]]])); $this->fail('Expected foreign venue rejection'); }
        catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('selected', $exception->getMessage()); }
        $preview = $scheduler->preview($event, $options);
        $changed = array_replace($options, ['day_venue_groups'=>[$venue->id=>['A'=>['2'], 'B'=>['1']]]]);
        $this->assertNotSame($preview['revision'], $scheduler->preview($event, $changed)['revision']);
        try { $scheduler->apply($event, $changed, $preview['revision']); $this->fail('Expected group revision rejection'); }
        catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('fresh preview', $exception->getMessage()); }
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_fixed_rubbers_anchor_the_remaining_tie_to_its_group_and_split_fixed_ties_are_rejected(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        $fixtures = TeamFixture::where('draw_id', $draws[0]->id)->orderBy('id')->get();
        $fixtures[0]->update(['match_status'=>1, 'scheduled_at'=>'2026-10-23 08:00', 'venue_id'=>$venue->id, 'court_label'=>'2', 'duration_min'=>60]);
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $options);
        $this->assertEmpty($preview['unscheduled']);
        $remaining = collect($preview['matches'])->firstWhere('draw_id', $draws[0]->id);
        $this->assertSame('B', $remaining['venue_group']);
        $this->assertSame('2', $remaining['court']);
        $extra = TeamFixture::create(['draw_id'=>$draws[0]->id, 'round_nr'=>1, 'tie_nr'=>1, 'match_nr'=>3, 'match_status'=>1,
            'scheduled_at'=>'2026-10-23 08:00', 'venue_id'=>$venue->id, 'court_label'=>'1', 'duration_min'=>60]);
        try { $scheduler->preview($event, $options); $this->fail('Expected fixed group split rejection'); }
        catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('split between court groups', $exception->getMessage()); }
        $this->assertSame('1', $extra->fresh()->court_label);
        $this->assertNull($fixtures[1]->fresh()->scheduled_at);
    }

    public function test_group_partition_rejects_two_labels_for_the_same_physical_court(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        DB::table('event_venue_courts')->insert([
            ['event_id'=>$event->id, 'venue_id'=>$venue->id, 'label'=>'1', 'active'=>true],
            ['event_id'=>$event->id, 'venue_id'=>$venue->id, 'label'=>'01', 'active'=>true],
        ]);
        $options['day_court_labels'] = [$venue->id=>['1', '01']];
        $options['day_venue_groups'] = [$venue->id=>['A'=>['1'], 'B'=>['01']]];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot overlap');
        app(EventVenueScheduleService::class)->preview($event, $options);
    }

    public function test_grouped_planning_respects_existing_physical_bookings_and_reload_warns_when_saved_courts_disappear(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        $fixed = TeamFixture::where('draw_id', $draws[2]->id)->first();
        $fixed->update(['scheduled_at'=>'2026-10-23 08:00', 'venue_id'=>$venue->id, 'court_label'=>'1', 'duration_min'=>60]);
        $scheduler = app(EventVenueScheduleService::class);
        $this->assertNotEmpty($scheduler->preview($event, $options)['unscheduled']);
        $options['end'] = '2026-10-23 11:00';
        $preview = $scheduler->preview($event, $options);
        $this->assertEmpty($preview['unscheduled']);
        $this->assertCount(0, collect($preview['matches'])->filter(fn ($row) => $row['court']==='1' && $row['scheduled_at']==='2026-10-23 08:00:00'));
        $scheduler->apply($event, $options, $preview['revision']);
        Role::firstOrCreate(['name'=>'admin', 'guard_name'=>'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id, 'user_id'=>$admin->id]);
        $url = route('backend.event-venue-schedule.calendar.full-day', ['event'=>$event->id, 'date'=>'2026-10-23']);
        $response = $this->actingAs($admin)->get($url)->assertOk()->assertSee('Loaded the last saved full-day choices');
        $this->assertEquals($options['day_venue_groups'], $response->viewData('values')['venue_groups']);
        DB::table('event_venue_courts')->insert(['event_id'=>$event->id, 'venue_id'=>$venue->id, 'label'=>'2', 'active'=>false]);
        $response = $this->get($url)->assertOk()->assertSee('Court groups have not been restored');
        $this->assertSame([], $response->viewData('values')['venue_groups']);
        $this->assertSame('2026-10-23 08:00', $fixed->fresh()->scheduled_at->format('Y-m-d H:i'));
    }

    public function test_venue_hours_apply_to_both_groups_and_incomplete_hours_cannot_save(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        $options['end'] = '2026-10-23 12:00';
        $options['full_day_venue_windows'] = [$venue->id=>['start'=>'2026-10-23 09:00', 'end'=>'2026-10-23 11:00']];
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $options);
        $this->assertEmpty($preview['unscheduled']);
        $this->assertCount(4, $preview['matches']);
        $this->assertSame('2026-10-23 09:00:00', collect($preview['matches'])->min('scheduled_at'));
        $this->assertSame('2026-10-23 10:00:00', collect($preview['matches'])->max('scheduled_at'));
        $options['full_day_venue_windows'][$venue->id]['end'] = '2026-10-23 10:30';
        $short = $scheduler->preview($event, $options);
        $this->assertNotSame($preview['revision'], $short['revision']);
        $this->assertNotEmpty($short['unscheduled']);
        try { $scheduler->apply($event, $options, $short['revision']); $this->fail('Expected incomplete venue hours rejection'); }
        catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('unscheduled', $exception->getMessage()); }
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_venue_hours_reject_foreign_venues_other_dates_reversed_and_outside_day_windows(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        foreach ([['start'=>'2026-10-23 07:00', 'end'=>'2026-10-23 09:00'], ['start'=>'2026-10-23 09:00', 'end'=>'2026-10-23 11:00'],
            ['start'=>'2026-10-23 09:00', 'end'=>'2026-10-23 08:00'], ['start'=>'2026-10-24 08:00', 'end'=>'2026-10-24 10:00']] as $window) {
            try { app(EventVenueScheduleService::class)->preview($event, array_replace($options, ['full_day_venue_windows'=>[$venue->id=>$window]])); $this->fail('Expected invalid venue hours'); }
            catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('Venue hours', $exception->getMessage()); }
        }
        $options['full_day_venue_windows'] = [Venue::forceCreate(['name'=>'Foreign hours'])->id=>['start'=>'2026-10-23 08:00', 'end'=>'2026-10-23 10:00']];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('selected day venues');
        app(EventVenueScheduleService::class)->preview($event, $options);
    }

    public function test_http_split_groups_and_optional_venue_hours_preview_save_and_reload(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        Role::firstOrCreate(['name'=>'admin', 'guard_name'=>'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id, 'user_id'=>$admin->id]);
        $this->actingAs($admin);
        $url = route('backend.event-venue-schedule.calendar.full-day', ['event'=>$event->id, 'date'=>'2026-10-23']);
        $payload = ['date'=>'2026-10-23', 'draw_ids'=>$options['draw_ids'],
            'rounds'=>collect($options['draw_rounds'])->mapWithKeys(fn ($row) => [$row['draw_id']=>$row['rounds']])->all(),
            'venue_ids'=>[$venue->id], 'court_labels'=>$options['day_court_labels'], 'venue_groups'=>$options['day_venue_groups'],
            'venue_windows'=>[$venue->id=>['end_time'=>'11:00']], 'start_time'=>'08:00', 'end_time'=>'12:00',
            'duration'=>60, 'player_rest'=>0, 'court_gap'=>0, 'action'=>'preview'];
        $response = $this->post($url, $payload)->assertOk()->assertSee('Group A (courts 1)')->assertSee('Group B (courts 2)')->assertSee('08:00–11:00');
        if (getenv('CT_DAY_EDIT_BROWSER_FIXTURE') === '1') file_put_contents(storage_path('framework/testing/full-day-split.html'), $response->getContent());
        $revision = $response->viewData('preview')['revision'];
        $this->post($url, array_replace($payload, ['action'=>'save', 'revision'=>$revision]))->assertRedirect();
        $response = $this->get($url)->assertOk()->assertSee('including court groups and venue hours');
        $this->assertEquals($options['day_venue_groups'], $response->viewData('values')['venue_groups']);
        $this->assertEquals([$venue->id=>['start_time'=>'08:00', 'end_time'=>'11:00']], $response->viewData('values')['venue_windows']);
        $payload['venue_groups'][$venue->id]['B'] = ['1', '2'];
        $this->post($url, $payload)->assertRedirect()->assertSessionHasErrors('schedule');
        $this->assertSame(4, TeamFixture::whereNotNull('scheduled_at')->count());
    }
}
