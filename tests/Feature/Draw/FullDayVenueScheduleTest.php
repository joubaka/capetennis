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

    public function test_manual_tie_destinations_use_chosen_venues_and_groups_and_are_revision_bound(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        $scheduler = app(EventVenueScheduleService::class);
        $category = \App\Models\CategoryEvent::factory()->create(['event_id'=>$event->id]);
        $home = \App\Models\Team::factory()->create(['category_event_id'=>$category->id]);
        $away = \App\Models\Team::factory()->create(['category_event_id'=>$category->id]);
        $realTie = \App\Models\TeamTie::factory()->create(['draw_id'=>$draws[0]->id, 'home_team_id'=>$home->id, 'away_team_id'=>$away->id]);
        TeamFixture::where('draw_id', $draws[0]->id)->update(['team_tie_id'=>$realTie->id]);
        $ties = collect($scheduler->fullDayTies($event))->keyBy('draw_id');
        $this->assertSame('draw:'.$draws[0]->id.':tie:'.$realTie->id, $ties[$draws[0]->id]['key']);
        $options['full_day_tie_destinations'] = [
            $ties[$draws[0]->id]['key']=>['venue_id'=>$venue->id, 'group'=>'B'],
            $ties[$draws[1]->id]['key']=>['venue_id'=>$venue->id, 'group'=>'A'],
        ];
        $preview = $scheduler->preview($event, $options);
        $this->assertEmpty($preview['unscheduled']);
        foreach ($preview['matches'] as $row) $this->assertSame($row['draw_id']===$draws[0]->id ? 'B' : 'A', $row['venue_group']);
        $automatic = array_replace($options, ['full_day_tie_destinations'=>[]]);
        $this->assertNotSame($preview['revision'], $scheduler->preview($event, $automatic)['revision']);
        try { $scheduler->apply($event, $automatic, $preview['revision']); $this->fail('Expected changed destination rejection'); }
        catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('fresh preview', $exception->getMessage()); }
        $scheduler->apply($event, $options, $preview['revision']);
        $audit = \App\Models\DrawAuditLog::where('draw_id', $draws[0]->id)->latest('id')->first()->payload;
        $this->assertEquals($options['full_day_tie_destinations'], $audit['full_day_options']['full_day_tie_destinations']);
    }

    public function test_manual_ties_can_use_primary_venue_while_other_ties_remain_automatic(): void
    {
        [$event, $draws, $venues, $options] = $this->context();
        $scheduler = app(EventVenueScheduleService::class);
        $ties = collect($scheduler->fullDayTies($event))->keyBy('draw_id');
        $options['full_day_tie_destinations'] = [$ties[$draws[0]->id]['key']=>['venue_id'=>$venues[2]->id, 'group'=>null]];
        $preview = $scheduler->preview($event, $options);
        $this->assertEmpty($preview['unscheduled']);
        $this->assertCount(6, $preview['matches']);
        foreach (collect($preview['matches'])->where('draw_id', $draws[0]->id) as $row) $this->assertSame($venues[2]->id, $row['venue_id']);
    }

    public function test_manual_destinations_reject_foreign_unselected_round_day_keys_and_invalid_groups(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        $scheduler = app(EventVenueScheduleService::class);
        $ties = collect($scheduler->fullDayTies($event))->keyBy('draw_id');
        $key = $ties[$draws[0]->id]['key'];
        $round = TeamFixture::create(['draw_id'=>$draws[0]->id, 'round_nr'=>2, 'tie_nr'=>2, 'match_nr'=>3, 'match_status'=>0]);
        $otherDay = TeamFixture::create(['draw_id'=>$draws[0]->id, 'round_nr'=>1, 'tie_nr'=>3, 'match_nr'=>4, 'match_status'=>0,
            'scheduled_at'=>'2026-10-24 08:00', 'venue_id'=>$venue->id, 'court_label'=>'1']);
        $newKeys = collect($scheduler->fullDayTies($event))->pluck('key');
        $invalidKeys = ['draw:999999:tie:999999', $ties[$draws[2]->id]['key'],
            $newKeys->first(fn ($value) => str_contains($value, ':round:2:')), $newKeys->first(fn ($value) => str_contains($value, ':legacy:3:'))];
        foreach ($invalidKeys as $invalidKey) {
            try { $scheduler->preview($event, array_replace($options, ['full_day_tie_destinations'=>[$invalidKey=>['venue_id'=>$venue->id, 'group'=>'A']]])); $this->fail('Expected invalid tie scope'); }
            catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('selected draws, rounds and day', $exception->getMessage()); }
        }
        foreach ([['venue_id'=>999999, 'group'=>null], ['venue_id'=>$venue->id, 'group'=>null], ['venue_id'=>$venue->id, 'group'=>'C']] as $destination) {
            try { $scheduler->preview($event, array_replace($options, ['full_day_tie_destinations'=>[$key=>$destination]])); $this->fail('Expected invalid destination'); }
            catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('selected venue', $exception->getMessage()); }
        }
        $this->assertNull($round->fresh()->scheduled_at);
        $this->assertSame('2026-10-24', $otherDay->fresh()->scheduled_at->toDateString());
    }

    public function test_manual_destination_cannot_override_a_played_rubbers_group_or_venue(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        $scheduler = app(EventVenueScheduleService::class);
        $tie = collect($scheduler->fullDayTies($event))->firstWhere('draw_id', $draws[0]->id);
        $played = TeamFixture::where('draw_id', $draws[0]->id)->first();
        $played->update(['match_status'=>1, 'scheduled_at'=>'2026-10-23 08:00', 'venue_id'=>$venue->id, 'court_label'=>'1', 'duration_min'=>60]);
        $options['full_day_tie_destinations'] = [$tie['key']=>['venue_id'=>$venue->id, 'group'=>'B']];
        try { $scheduler->preview($event, $options); $this->fail('Expected fixed group conflict'); }
        catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('conflicts with a fixed rubber', $exception->getMessage()); }
        $options['full_day_tie_destinations'][$tie['key']]['group'] = 'A';
        $this->assertEmpty($scheduler->preview($event, $options)['unscheduled']);
        $this->assertSame('1', $played->fresh()->court_label);
    }

    public function test_http_tie_board_saves_manual_destinations_and_reloads_them(): void
    {
        [$event, $draws, $venue, $options] = $this->splitContext();
        Role::firstOrCreate(['name'=>'admin', 'guard_name'=>'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id, 'user_id'=>$admin->id]);
        $this->actingAs($admin);
        $ties = collect(app(EventVenueScheduleService::class)->fullDayTies($event))->keyBy('draw_id');
        $choices = [$ties[$draws[0]->id]['key']=>$venue->id.':B', $ties[$draws[1]->id]['key']=>$venue->id.':A'];
        $selectedVenues = $event->venues()->get();
        $selectedCourts = $selectedVenues->mapWithKeys(fn ($destination) => [$destination->id=>$destination->id===$venue->id ? ['1', '2'] : ['1']])->all();
        $url = route('backend.event-venue-schedule.calendar.full-day', ['event'=>$event->id, 'date'=>'2026-10-23']);
        $payload = ['date'=>'2026-10-23', 'draw_ids'=>$options['draw_ids'],
            'rounds'=>collect($options['draw_rounds'])->mapWithKeys(fn ($row) => [$row['draw_id']=>$row['rounds']])->all(),
            'venue_ids'=>$selectedVenues->pluck('id')->all(), 'court_labels'=>$selectedCourts, 'venue_groups'=>$options['day_venue_groups'],
            'tie_destinations'=>$choices, 'start_time'=>'08:00', 'end_time'=>'10:00', 'duration'=>60, 'player_rest'=>0, 'court_gap'=>0, 'action'=>'preview'];
        $response = $this->post($url, $payload)->assertOk()->assertSee('Place ties at venues')->assertSee('Automatic / unassigned')->assertSee('draggable="true"', false);
        $this->assertCount(3, $response->viewData('tieOptions'));
        if (getenv('CT_DAY_EDIT_BROWSER_FIXTURE') === '1') file_put_contents(storage_path('framework/testing/full-day-tie-board.html'), $response->getContent());
        $this->post($url, array_replace($payload, ['action'=>'save', 'revision'=>$response->viewData('preview')['revision']]))->assertRedirect();
        $response = $this->get($url)->assertOk();
        $this->assertEquals($choices, $response->viewData('values')['tie_destinations']);
        $this->assertDatabaseCount('venues', 3);
    }

    private function agePoolContext(): array
    {
        [$event, $draws, $venues, $options] = $this->context();
        $draws[0]->update(['drawName'=>'U/15 Boys']);
        $draws[1]->update(['drawName'=>'U/15 Girls']);
        $draws[2]->update(['drawName'=>'U/19 round robin']);
        $venues[0]->forceFill(['name'=>'Sports Club'])->save();
        $venues[1]->forceFill(['name'=>'High School'])->save();
        $venues[2]->forceFill(['name'=>'Primary School'])->save();
        $event->venues()->updateExistingPivot($venues[0]->id, ['num_courts'=>2]);
        $options['day_court_labels'][$venues[0]->id] = ['1', '2'];
        $options['day_venue_groups'] = [$venues[0]->id=>['A'=>['1'], 'B'=>['2']]];
        $options['full_day_destination_pools'] = ['age:15'=>[(string) $venues[1]->id, (string) $venues[2]->id],
            'age:19'=>[$venues[0]->id.':A', $venues[0]->id.':B']];
        return [$event, $draws, $venues, $options];
    }

    public function test_age_group_pools_share_across_disciplines_and_restrict_automatic_ties_to_the_chosen_venues(): void
    {
        [$event, $draws, $venues, $options] = $this->agePoolContext();
        $scheduler = app(EventVenueScheduleService::class);
        $definitions = collect($scheduler->fullDayDestinationPools($event))->keyBy('key');
        $this->assertSame([$draws[0]->id, $draws[1]->id], $definitions['age:15']['draw_ids']);
        $this->assertSame([$draws[2]->id], $definitions['age:19']['draw_ids']);
        $preview = $scheduler->preview($event, $options);
        $this->assertEmpty($preview['unscheduled']);
        $this->assertCount(6, $preview['matches']);
        foreach ($preview['matches'] as $row) {
            if ($row['draw_id']===$draws[2]->id) {
                $this->assertSame($venues[0]->id, $row['venue_id']);
                $this->assertContains($row['venue_group'], ['A', 'B']);
            } else $this->assertContains($row['venue_id'], [$venues[1]->id, $venues[2]->id]);
        }
        $this->assertNotSame($preview['revision'], $scheduler->preview($event, array_replace($options, ['full_day_destination_pools'=>[]]))['revision']);
        $reordered = $options;
        $reordered['full_day_destination_pools']['age:15'] = array_reverse($reordered['full_day_destination_pools']['age:15']);
        $this->assertSame($preview['matches'], $scheduler->preview($event, $reordered)['matches']);
        $this->assertSame($preview['revision'], $scheduler->preview($event, $reordered)['revision']);
        $scheduler->apply($event, $options, $preview['revision']);
        $audit = \App\Models\DrawAuditLog::where('draw_id', $draws[0]->id)->latest('id')->first()->payload;
        $this->assertEquals($options['full_day_destination_pools'], $audit['full_day_options']['full_day_destination_pools']);
    }

    public function test_age_group_pools_reject_empty_foreign_unselected_destinations_and_manual_overrides_outside_the_pool(): void
    {
        [$event, $draws, $venues, $options] = $this->agePoolContext();
        $scheduler = app(EventVenueScheduleService::class);
        foreach ([['age:15'=>[]], ['age:99'=>[(string) $venues[1]->id]], ['age:15'=>['999999']],
            ['age:15'=>[(string) $venues[0]->id]], ['age:15'=>[$venues[0]->id.':C']],
            ['age:15'=>[(string) $venues[1]->id, (string) $venues[1]->id]]] as $pools) {
            try { $scheduler->preview($event, array_replace($options, ['full_day_destination_pools'=>$pools])); $this->fail('Expected invalid pool rejection'); }
            catch (\InvalidArgumentException $exception) { $this->assertNotEmpty($exception->getMessage()); }
        }
        $tie = collect($scheduler->fullDayTies($event))->firstWhere('draw_id', $draws[0]->id);
        $options['full_day_tie_destinations'] = [$tie['key']=>['venue_id'=>$venues[0]->id, 'group'=>'A']];
        try { $scheduler->preview($event, $options); $this->fail('Expected manual pool conflict'); }
        catch (\InvalidArgumentException $exception) { $this->assertStringContainsString('outside its age group', $exception->getMessage()); }
        $options['full_day_tie_destinations'][$tie['key']] = ['venue_id'=>$venues[2]->id, 'group'=>null];
        $this->assertEmpty($scheduler->preview($event, $options)['unscheduled']);
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_age_group_pool_cannot_exclude_the_venue_of_a_played_rubber_in_its_tie(): void
    {
        [$event, $draws, $venues, $options] = $this->agePoolContext();
        $played = TeamFixture::where('draw_id', $draws[0]->id)->first();
        $played->update(['match_status'=>1, 'scheduled_at'=>'2026-10-23 08:00', 'venue_id'=>$venues[1]->id, 'court_label'=>'1', 'duration_min'=>60]);
        $options['full_day_destination_pools']['age:15'] = [(string) $venues[2]->id];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pool conflicts with a fixed rubber');
        app(EventVenueScheduleService::class)->preview($event, $options);
    }

    public function test_unknown_age_falls_back_to_its_draw_pool_and_unselected_age_pools_are_rejected(): void
    {
        [$event, $draws, $venues, $options] = $this->agePoolContext();
        $draws[2]->update(['drawName'=>'Open mixed teams']);
        $scheduler = app(EventVenueScheduleService::class);
        $definitions = collect($scheduler->fullDayDestinationPools($event))->keyBy('key');
        $this->assertSame('Open mixed teams', $definitions['draw:'.$draws[2]->id]['label']);
        $options['draw_ids'] = [$draws[0]->id];
        $options['draw_rounds'] = [['draw_id'=>$draws[0]->id, 'rounds'=>[1]]];
        $options['full_day_destination_pools'] = ['draw:'.$draws[2]->id=>[(string) $venues[1]->id]];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('selected age groups or draws');
        $scheduler->preview($event, $options);
    }

    public function test_http_age_group_pools_preview_save_reload_and_reject_an_explicit_empty_pool(): void
    {
        [$event, $draws, $venues, $options] = $this->agePoolContext();
        Role::firstOrCreate(['name'=>'admin', 'guard_name'=>'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id, 'user_id'=>$admin->id]);
        $this->actingAs($admin);
        $url = route('backend.event-venue-schedule.calendar.full-day', ['event'=>$event->id, 'date'=>'2026-10-23']);
        $payload = ['date'=>'2026-10-23', 'draw_ids'=>$options['draw_ids'],
            'rounds'=>collect($options['draw_rounds'])->mapWithKeys(fn ($row) => [$row['draw_id']=>$row['rounds']])->all(),
            'venue_ids'=>$options['venue_ids'], 'court_labels'=>$options['day_court_labels'], 'venue_groups'=>$options['day_venue_groups'],
            'destination_pools'=>$options['full_day_destination_pools'], 'start_time'=>'08:00', 'end_time'=>'10:00', 'duration'=>60, 'player_rest'=>0, 'court_gap'=>0, 'action'=>'preview'];
        $response = $this->post($url, $payload)->assertOk()->assertSee('Assign venues to age groups')->assertSee('Venues for U/15')->assertSee('Venues for U/19')->assertSee('All selected venues');
        if (getenv('CT_DAY_EDIT_BROWSER_FIXTURE') === '1') file_put_contents(storage_path('framework/testing/full-day-age-pools.html'), $response->getContent());
        $this->post($url, array_replace($payload, ['action'=>'save', 'revision'=>$response->viewData('preview')['revision']]))->assertRedirect();
        $response = $this->get($url)->assertOk();
        $this->assertEquals($options['full_day_destination_pools'], $response->viewData('values')['destination_pools']);
        $payload['destination_pools']['age:15'] = [''];
        $this->post($url, $payload)->assertRedirect()->assertSessionHasErrors('schedule');
        $this->assertDatabaseCount('team_fixtures', 6);
        $this->assertSame(6, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_playoff_group_choices_split_both_ages_between_schools_and_club_court_groups(): void
    {
        [$event, $draws, $venues, $options] = $this->agePoolContext();
        $selected = collect([$draws[0], $draws[2]]);
        $choices = [];
        foreach ($selected as $index => $draw) {
            $age = $index === 0 ? 15 : 19;
            $draw->forceFill(['drawName'=>'U/'.$age.' placement playoffs', 'team_draw_selection'=>[
                'competition'=>['phase'=>'playoff', 'group'=>'U'.$age, 'qualification_pending'=>true]]])->save();
            foreach ([1, 2] as $number) {
                $tie = \App\Models\TeamTie::create(['draw_id'=>$draw->id, 'round_nr'=>1, 'tie_nr'=>$number, 'status'=>'draft']);
                if ($number === 1) TeamFixture::where('draw_id', $draw->id)->update(['team_tie_id'=>$tie->id]);
                else foreach ([1, 2] as $rubber) TeamFixture::create(['draw_id'=>$draw->id, 'team_tie_id'=>$tie->id,
                    'round_nr'=>1, 'tie_nr'=>$number, 'match_nr'=>2+$rubber, 'rubber_sequence'=>$rubber, 'match_status'=>0]);
                $choices['draw:'.$draw->id.':tie:'.$tie->id] = $number === 1
                    ? (string) $venues[$age === 15 ? 2 : 1]->id : $venues[0]->id.($age === 15 ? ':B' : ':A');
            }
        }
        Role::firstOrCreate(['name'=>'admin', 'guard_name'=>'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id, 'user_id'=>$admin->id]);
        $this->actingAs($admin);
        $url = route('backend.event-venue-schedule.calendar.full-day', ['event'=>$event->id, 'date'=>'2026-10-23']);
        $payload = ['date'=>'2026-10-23', 'draw_ids'=>$selected->pluck('id')->all(),
            'rounds'=>$selected->mapWithKeys(fn ($draw) => [$draw->id=>[1]])->all(),
            'venue_ids'=>$options['venue_ids'], 'court_labels'=>$options['day_court_labels'], 'venue_groups'=>$options['day_venue_groups'],
            'destination_pools'=>['age:15'=>[(string) $venues[2]->id, $venues[0]->id.':B'],
                'age:19'=>[(string) $venues[1]->id, $venues[0]->id.':A']],
            'tie_destinations'=>$choices, 'start_time'=>'08:00', 'end_time'=>'10:00', 'duration'=>60, 'player_rest'=>0, 'court_gap'=>0, 'action'=>'preview'];
        $response = $this->post($url, $payload)->assertOk()->assertSee('Assign playoff ties to venues')
            ->assertSee('1st vs 2nd')->assertSee('3rd vs 4th')->assertSee('Each choice assigns all matches');
        $playoffs = collect($response->viewData('tieOptions'))->whereNotNull('playoff_group');
        $this->assertCount(4, $playoffs);
        foreach ($playoffs as $tie) $this->assertSame(1, substr_count($response->getContent(), 'name="tie_destinations['.$tie['key'].']"'));
        $preview = $response->viewData('preview');
        $this->assertCount(8, $preview['matches']);
        $this->assertEmpty($preview['unscheduled']);
        foreach ($preview['matches'] as $row) {
            $fixture = TeamFixture::findOrFail($row['fixture_id']);
            $destination = explode(':', $choices['draw:'.$fixture->draw_id.':tie:'.$fixture->team_tie_id]);
            $this->assertSame((int) $destination[0], $row['venue_id']);
            $this->assertSame($destination[1] ?? null, $row['venue_group']);
        }
        if (getenv('CT_DAY_EDIT_BROWSER_FIXTURE') === '1') file_put_contents(storage_path('framework/testing/competition-group-venues.html'), $response->getContent());
        $changed = $payload;
        $changed['tie_destinations'][array_key_first($choices)] = $venues[0]->id.':B';
        $this->post($url, array_replace($changed, ['action'=>'save', 'revision'=>$preview['revision']]))->assertRedirect()->assertSessionHasErrors('schedule');
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
        session()->forget('errors');
        $this->post($url, array_replace($payload, ['action'=>'save', 'revision'=>$preview['revision']]))->assertRedirect();
        $this->assertSame(8, TeamFixture::whereNotNull('scheduled_at')->count());
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $reloaded = $this->get($url)->assertOk();
        $this->assertEquals($choices, $reloaded->viewData('values')['tie_destinations']);
        $audit = \App\Models\DrawAuditLog::where('draw_id', $draws[0]->id)->latest('id')->first()->payload;
        $this->assertCount(4, $audit['full_day_options']['full_day_tie_destinations']);
        $invalid = $payload;
        $invalid['tie_destinations'][array_key_first($choices)] = (string) $venues[1]->id;
        $this->post($url, $invalid)->assertRedirect()->assertSessionHasErrors('schedule');
        $this->assertSame(8, TeamFixture::whereNotNull('scheduled_at')->count());
    }
}
