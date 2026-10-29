<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Registration, TeamFixture, User, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleDayBatchTest extends TestCase
{
    use RefreshDatabase;

    private function signSlots(array $rows): array
    {
        return collect($rows)->mapWithKeys(fn ($row)=>[$row['fixture_key']=>[$row['scheduled_at'],$row['venue_id'],$row['court']]])->sortKeys()->all();
    }

    private function signInAdmin(Event $event): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $admin=User::factory()->create()->assignRole('admin');DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);$this->actingAs($admin);
    }

    private function context(bool $team = false): array
    {
        $event = Event::factory()->create(['start_date'=>'2026-10-30','end_date'=>'2026-11-01']);
        $venue = Venue::forceCreate(['name'=>'Shared day courts']);
        $event->venues()->attach($venue->id, ['num_courts'=>2]);
        $draws = Draw::factory()->count(2)->create(['event_id'=>$event->id] + ($team ? ['team_category_id'=>1] : []));
        $fixtures = collect();
        foreach ($draws as $draw) {
            if ($team) $fixture = TeamFixture::create(['draw_id'=>$draw->id,'round_nr'=>1,'tie_nr'=>1,'match_nr'=>1,'match_status'=>0]);
            else $fixture = Fixture::factory()->create(['draw_id'=>$draw->id,'round'=>1,'match_nr'=>1,'bracket_id'=>1,
                'registration1_id'=>Registration::factory()->create()->id,'registration2_id'=>Registration::factory()->create()->id]);
            $fixtures->push($fixture);
        }
        $options = ['day_batch'=>true,'full_day_scope'=>'2026-10-30','start'=>'2026-10-30 08:00','end'=>'2026-10-30 18:00',
            'draw_ids'=>$draws->pluck('id')->all(),'draw_order'=>$draws->pluck('id')->all(),'draw_order_mode'=>'mixed',
            'draw_rounds'=>$draws->map(fn ($draw)=>['draw_id'=>$draw->id,'rounds'=>[1]])->all(),
            'selected_fixture_keys'=>$fixtures->map(fn ($fixture)=>($team?'team:':'individual:').$fixture->id)->all(),
            'venue_ids'=>[$venue->id],'day_court_labels'=>[$venue->id=>['1','2']],
            'duration'=>60,'player_rest'=>30,'court_gap'=>0,'reschedule_existing'=>false];
        return [$event,$draws,$venue,$fixtures,$options];
    }

    public function test_mixed_uses_available_courts_but_sequential_waits_for_match_finish_not_unrelated_player_rest(): void
    {
        [$event,$draws,,$fixtures,$options] = $this->context();
        $service = app(EventVenueScheduleService::class);
        $mixed = $service->preview($event,$options);
        $this->assertSame([], $mixed['unscheduled']);
        $this->assertSame(['2026-10-30 08:00:00'],collect($mixed['matches'])->pluck('scheduled_at')->unique()->values()->all());
        $strict = $service->preview($event,array_replace($options,['draw_order_mode'=>'sequential','gender_waves'=>'boys_then_girls']));
        $times = collect($strict['matches'])->keyBy('fixture_id');
        $this->assertSame('2026-10-30 08:00:00',$times[$fixtures[0]->id]['scheduled_at']);
        $this->assertSame('2026-10-30 09:00:00',$times[$fixtures[1]->id]['scheduled_at']);
        $this->assertSame('combined',$strict['input']['gender_waves']);
        $reverse = $service->preview($event,array_replace($options,['draw_order_mode'=>'sequential','draw_order'=>$draws->pluck('id')->reverse()->values()->all()]));
        $this->assertNotSame($strict['revision'],$reverse['revision']);
        $this->assertSame('2026-10-30 08:00:00',collect($reverse['matches'])->keyBy('fixture_id')[$fixtures[1]->id]['scheduled_at']);
        $this->assertDatabaseCount('order_of_plays',0);
    }

    public function test_page_selected_venue_from_another_event_draw_and_exact_courts_preview_and_apply_without_assigning_draws(): void
    {
        [$event,$draws,,$fixtures,$options] = $this->context();
        $venue = Venue::forceCreate(['name'=>'Venue attached through another draw']);
        $otherDraw = Draw::factory()->create(['event_id'=>$event->id]);
        $otherDraw->venues()->attach($venue->id, ['num_courts'=>3]);
        $this->assertSame(['1','2','3'], app(EventVenueScheduleService::class)->fullDayCourtLabels($event)[$venue->id]);
        foreach (['A'=>true,'B'=>true,'Closed'=>false] as $label=>$active) {
            DB::table('event_venue_courts')->insert(['event_id'=>$event->id,'venue_id'=>$venue->id,'label'=>$label,'active'=>$active]);
        }
        $options = array_replace($options, ['wave_minutes'=>90, 'venue_ids'=>[$venue->id], 'day_court_labels'=>[$venue->id=>['B']]]);
        $this->signInAdmin($event);
        $preview = $this->postJson(route('backend.event-venue-schedule.preview',$event),$options)->assertOk()->json();
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount(2, $preview['matches']);
        foreach ($preview['matches'] as $row) {
            $this->assertSame($venue->id, $row['venue_id']);
            $this->assertSame('B', $row['court']);
        }
        $this->assertDatabaseCount('order_of_plays',0);
        $this->postJson(route('backend.event-venue-schedule.apply',$event),array_replace($options,[
            'day_court_labels'=>[$venue->id=>['A']], 'revision'=>$preview['revision'],
        ]))->assertStatus(422);
        $this->postJson(route('backend.event-venue-schedule.apply',$event),$options+['revision'=>$preview['revision']])->assertOk()->assertJsonPath('count',2);
        foreach ($fixtures as $fixture) {
            $this->assertDatabaseHas('order_of_plays',['fixture_id'=>$fixture->id,'venue_id'=>$venue->id,'court'=>'B']);
        }
        foreach ($draws as $draw) $this->assertSame(0,$draw->venues()->count());
        $this->assertDatabaseCount('published_schedule_assignments',0);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment',$event),[
            'fixture_id'=>$fixtures[0]->id, 'scheduled_at'=>'2026-10-31 09:00',
            'venue_id'=>$venue->id, 'court'=>'B', 'duration'=>60, 'player_rest'=>30, 'court_gap'=>0,
        ])->assertOk();

        $this->postJson(route('backend.event-venue-schedule.preview',$event),array_replace($options,[
            'day_court_labels'=>[$venue->id=>['Closed']],
        ]))->assertStatus(422);
        $foreignEvent = Event::factory()->create();
        $foreignVenue = Venue::forceCreate(['name'=>'Another event only']);
        Draw::factory()->create(['event_id'=>$foreignEvent->id])->venues()->attach($foreignVenue->id,['num_courts'=>2]);
        $this->postJson(route('backend.event-venue-schedule.preview',$event),array_replace($options,[
            'venue_ids'=>[$foreignVenue->id], 'day_court_labels'=>[$foreignVenue->id=>['1']],
        ]))->assertStatus(422);
        $this->assertDatabaseCount('order_of_plays',2);
    }

    public function test_sequential_replanning_uses_new_finish_in_both_directions_and_default_preserves_saved_booking(): void
    {
        [$event,$draws,$venue,$fixtures,$options] = $this->context();
        $slot = OrderOfPlay::create(['fixture_id'=>$fixtures[0]->id,'draw_id'=>$draws[0]->id,'time'=>'2026-10-30 08:00','venue_id'=>$venue->id,'court'=>'1','duration_minutes'=>60,'gap_minutes'=>0]);
        $service = app(EventVenueScheduleService::class);
        $keep = $service->preview($event,array_replace($options,['start'=>'2026-10-30 10:00','draw_order_mode'=>'sequential']));
        $this->assertCount(1,$keep['matches']);
        $this->assertStringStartsWith('2026-10-30 08:00',$slot->fresh()->time);
        foreach ([['start'=>'2026-10-30 10:00','old'=>'08:00','expected'=>'11:00'],['start'=>'2026-10-30 08:00','old'=>'12:00','expected'=>'09:00']] as $case) {
            $slot->update(['time'=>'2026-10-30 '.$case['old']]);
            $preview = $service->preview($event,array_replace($options,['start'=>$case['start'],'draw_order_mode'=>'sequential','reschedule_existing'=>true]));
            $this->assertSame([], $preview['unscheduled']);
            $this->assertSame('2026-10-30 '.$case['expected'].':00',collect($preview['matches'])->keyBy('fixture_id')[$fixtures[1]->id]['scheduled_at']);
        }
        $this->assertDatabaseCount('order_of_plays',1);
    }

    public function test_apply_persists_day_choices_and_allows_manual_move_at_event_only_venue_without_changing_defaults(): void
    {
        foreach ([false,true] as $team) {
            [$event,$draws,$venue,$fixtures,$options] = $this->context($team);
            \Spatie\Permission\Models\Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
            $admin=User::factory()->create()->assignRole('admin');
            DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);
            $this->actingAs($admin);
            $service=app(EventVenueScheduleService::class);
            $preview=$service->preview($event,$options);
            $this->assertSame([], $preview['unscheduled']);
            $this->assertSame(2,$service->apply($event,$options,$preview['revision'])['count']);
            $stored=json_decode(DB::table('event_venue_schedule_drafts')->where('event_id',$event->id)->value('options'),true);
            $this->assertSame($options['draw_order'],$stored['day_batch_settings']['2026-10-30']['draw_order']);
            $key=($team?'team:':'individual:').$fixtures[0]->id;
            $this->assertSame([$venue->id],$stored['day_batch_allocations'][$key]['venue_ids']);
            $this->postJson(route('backend.event-venue-schedule.manual-assignment',$event),[
                'fixture_id'=>$fixtures[0]->id,'fixture_kind'=>$team?'team':'individual','scheduled_at'=>'2026-10-31 09:00',
                'venue_id'=>$venue->id,'court'=>'2','duration'=>60,'player_rest'=>30,'court_gap'=>0,
            ])->assertOk();
            $this->assertSame(0,$draws[0]->venues()->count());
            $this->assertDatabaseCount('published_schedule_assignments',0);
        }
    }

    public function test_forged_or_incomplete_day_scope_never_falls_back_to_all_matches(): void
    {
        [$event,$draws,,$fixtures,$options]=$this->context();
        $service=app(EventVenueScheduleService::class);
        $cases=[['draw_order'=>[$draws[0]->id]],['draw_order'=>[$draws[0]->id,$draws[0]->id]],
            ['draw_rounds'=>[['draw_id'=>$draws[0]->id,'rounds'=>[1]]]],
            ['selected_fixture_keys'=>['individual:'.$fixtures[0]->id]],['selected_fixture_keys'=>['team:'.$fixtures[0]->id]],
            ['full_day_scope'=>'2026-12-01'],['day_court_labels'=>[]],['day_court_labels'=>[99999=>['1']]]];
        foreach ($cases as $changes) {
            try {$service->preview($event,array_replace($options,$changes));$this->fail('Invalid selection was accepted: '.json_encode($changes));}
            catch (\InvalidArgumentException $error) {$this->assertNotEmpty($error->getMessage());}
        }
        $this->assertDatabaseCount('order_of_plays',0);
    }

    public function test_graph_round_selection_keeps_omitted_feeders_blocked_and_saved_positional_round_survives_manual_refresh(): void
    {
        [$event,$draws,$venue,,$options]=$this->context();
        $draw=$draws[0];
        Fixture::where('draw_id',$draw->id)->delete();
        $category=\App\Models\CategoryEvent::factory()->create(['event_id'=>$event->id,'category_id'=>\App\Models\Category::factory()->create()->id]);
        $draw->update(['category_event_id'=>$category->id]);
        $draw->settings()->create(['workflow'=>'feed_in_monrad']);
        $slots=[];
        foreach (Registration::factory()->count(8)->create() as $index=>$registration) {
            $registration->categoryEvents()->attach($category->id,['status'=>'registered','payment_status_id'=>1]);
            $registration->players()->attach(\App\Models\Player::factory()->create()->id);
            $path=strtr(str_pad(decbin($index),3,'0',STR_PAD_LEFT),['0'=>'a','1'=>'b']);
            $slots[$path]=['type'=>'player','id'=>$registration->id];
        }
        $compiler=app(\App\Services\Draw\FlexibleMonradService::class);
        $compiler->save($draw,['size'=>8,'slots'=>$slots],0);$compiler->generate($draw,1);
        $draw=$draw->fresh();$service=app(EventVenueScheduleService::class);
        $metadata=$service->dayRoundDetails($draw);
        $rounds=array_map('intval',array_keys($metadata));
        $keys=collect($metadata)->flatten(1)->flatMap(fn ($group)=>array_column($group['matches'],'key'))->all();
        $options=array_replace($options,['draw_ids'=>[$draw->id],'draw_order'=>[$draw->id],
            'draw_rounds'=>[['draw_id'=>$draw->id,'rounds'=>$rounds]],'selected_fixture_keys'=>$keys]);
        $last=max($rounds);$lastKeys=collect($metadata[$last])->flatMap(fn ($group)=>array_column($group['matches'],'key'))->all();
        $onlyLast=$service->preview($event,array_replace($options,['draw_rounds'=>[['draw_id'=>$draw->id,'rounds'=>[$last]]],'selected_fixture_keys'=>$lastKeys]));
        $this->assertSame([],$onlyLast['matches']);$this->assertNotEmpty($onlyLast['unscheduled']);
        $this->assertStringContainsString('must be scheduled first',implode(' ',array_column($onlyLast['unscheduled'],'reason')));
        $preview=$service->preview($event,$options);$this->assertSame([],$preview['unscheduled']);
        $positional=collect($preview['matches'])->first(fn ($row)=>$row['round']===$last);
        $forced=['fixture_key'=>$positional['fixture_key'],'scheduled_at'=>$positional['scheduled_at'],'venue_id'=>$venue->id,'court'=>$positional['court']==='1'?'2':'1'];
        $shifted=$service->preview($event,$options+['preview_moves'=>[$forced]]);
        $actual=collect($shifted['matches'])->firstWhere('fixture_key',$forced['fixture_key']);
        $this->assertSame($forced['scheduled_at'],$actual['scheduled_at']);$this->assertSame($forced['court'],$actual['court']);
        $this->assertDatabaseCount('order_of_plays',0);
        $service->apply($event,$options,$preview['revision']);
        $fixture=$draw->drawFixtures()->get()->first(fn ($fixture)=>(int) $fixture->round!==$last && in_array('individual:'.$fixture->id,$lastKeys,true));
        $this->assertNotNull($fixture,'Positional fixtures must exercise differing raw and graph rounds.');
        $saved=$service->preview($event,$options);
        $this->assertSame($last,collect($saved['existing_matches'])->firstWhere('fixture_id',$fixture->id)['round']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $admin=User::factory()->create()->assignRole('admin');DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);$this->actingAs($admin);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment',$event),['fixture_id'=>$fixture->id,'scheduled_at'=>'2026-10-31 09:00',
            'venue_id'=>$venue->id,'court'=>'2','duration'=>60,'player_rest'=>30,'court_gap'=>0])->assertOk();
        $refresh=array_replace($options,['full_day_scope'=>'2026-10-31','start'=>'2026-10-31 00:00','end'=>'2026-10-31 23:59',
            'draw_rounds'=>[['draw_id'=>$draw->id,'rounds'=>[$last]]],'selected_fixture_keys'=>['individual:'.$fixture->id]]);
        $result=$service->preview($event,$refresh);
        $this->assertSame([],$result['matches']);$this->assertSame([],$result['unscheduled']);
        $this->assertSame('2026-10-31 09:00:00',collect($result['existing_matches'])->firstWhere('fixture_id',$fixture->id)['scheduled_at']);
        $this->assertDatabaseCount('published_schedule_assignments',0);
    }

    public function test_day_http_adapter_keeps_typed_scope_and_order_and_enforces_current_revision_and_event_authority(): void
    {
        [$event,$draws,,$fixtures,$options]=$this->context();
        $options['wave_minutes']=90;
        \Spatie\Permission\Models\Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $admin=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);$this->actingAs($admin);
        $preview=$this->postJson(route('backend.event-venue-schedule.preview',$event),$options)->assertOk()->json();
        $this->assertTrue($preview['input']['day_batch']);
        $this->assertSame($options['selected_fixture_keys'],$preview['input']['selected_fixture_keys']);
        $changed=array_replace($options,['draw_order'=>array_reverse($options['draw_order'])]);
        $this->postJson(route('backend.event-venue-schedule.apply',$event),$changed+['revision'=>$preview['revision']])->assertStatus(422);
        $this->assertDatabaseCount('order_of_plays',0);
        $other=Event::factory()->create();
        $this->postJson(route('backend.event-venue-schedule.preview',$other),$options)->assertForbidden();
        $this->postJson(route('backend.event-venue-schedule.apply',$event),$options+['revision'=>$preview['revision']])->assertOk()->assertJsonPath('count',2);
        $this->assertDatabaseCount('order_of_plays',2);
        $this->assertDatabaseCount('fixtures',2);
        $this->assertDatabaseCount('published_schedule_assignments',0);
    }

    public function test_sequential_round_order_finishes_a_then_b_before_next_round_and_shared_players_keep_rest(): void
    {
        [$event,$draws,,$fixtures,$options]=$this->context();
        foreach ($draws as $index=>$draw) {
            $fixture=Fixture::factory()->create(['draw_id'=>$draw->id,'round'=>2,'match_nr'=>2,'bracket_id'=>1,
                'registration1_id'=>Registration::factory()->create()->id,'registration2_id'=>Registration::factory()->create()->id]);
            $fixtures->push($fixture);$options['selected_fixture_keys'][]='individual:'.$fixture->id;
            $options['draw_rounds'][$index]['rounds']=[1,2];
        }
        $preview=app(EventVenueScheduleService::class)->preview($event,array_replace($options,['draw_order_mode'=>'sequential']));
        $this->assertSame([],$preview['unscheduled']);
        $this->assertSame([$draws[0]->id,$draws[1]->id,$draws[0]->id,$draws[1]->id],array_column($preview['matches'],'draw_id'));
        $this->assertSame(['08:00','09:00','10:00','11:00'],array_map(fn ($row)=>substr($row['scheduled_at'],11,5),$preview['matches']));
        $player=\App\Models\Player::factory()->create();
        $fixtures[0]->registration1->players()->attach($player->id);$fixtures[1]->registration1->players()->attach($player->id);
        $rested=app(EventVenueScheduleService::class)->preview($event,array_replace($options,['draw_order_mode'=>'sequential']));
        $this->assertSame('09:30',substr(collect($rested['matches'])->firstWhere('fixture_id',$fixtures[1]->id)['scheduled_at'],11,5));
    }

    public function test_saved_bands_on_one_draw_do_not_relax_another_draws_fixed_whole_tie_venue(): void
    {
        [$event,$draws,$venue,$fixtures,$options]=$this->context(true);
        $event->venues()->updateExistingPivot($venue->id,['num_courts'=>1]);
        $other=Venue::forceCreate(['name'=>'Second day venue']);$event->venues()->attach($other->id,['num_courts'=>1]);
        $draws[0]->venues()->attach($other->id,['num_courts'=>1]);
        $fixtures[1]->update(['scheduled_at'=>'2026-10-30 08:00','venue_id'=>$venue->id,'court_label'=>'1','duration_min'=>120]);
        $extra=TeamFixture::create(['draw_id'=>$draws[1]->id,'round_nr'=>1,'tie_nr'=>1,'match_nr'=>2,'match_status'=>0]);
        $options['selected_fixture_keys'][]='team:'.$extra->id;
        $options['venue_ids'][]=$other->id;$options['day_court_labels']=[$venue->id=>['1'],$other->id=>['1']];
        $rule=['draw_ids'=>[$draws[0]->id],'min_rank'=>1,'max_rank'=>100,'venue_id'=>$other->id];
        DB::table('event_venue_schedule_drafts')->insert(['event_id'=>$event->id,'options'=>json_encode(['rank_venue_preferences'=>[$rule],'cross_band_policy'=>'manual']),'created_at'=>now(),'updated_at'=>now()]);
        $service=app(EventVenueScheduleService::class);$preview=$service->preview($event,$options);
        $this->assertSame([],$preview['unscheduled']);
        $this->assertSame('manual',$preview['input']['cross_band_policy']);
        $this->assertCount(1,$preview['input']['rank_venue_preferences']);
        $new=collect($preview['matches'])->firstWhere('fixture_id',$extra->id);
        $this->assertSame($venue->id,$new['venue_id']);$this->assertSame('2026-10-30 10:00:00',$new['scheduled_at']);
        $service->apply($event,$options,$preview['revision']);
        $stored=json_decode(DB::table('event_venue_schedule_drafts')->where('event_id',$event->id)->value('options'),true);
        $this->assertSame([$venue->id],$stored['day_batch_allocations']['team:'.$extra->id]['venue_ids']);
        $this->assertDatabaseCount('published_schedule_assignments',0);
    }

    public function test_suggested_dependent_can_move_between_venues_without_saving_and_apply_keeps_the_valid_day_pool(): void
    {
        [$event,$draws,$venue,$fixtures,$options]=$this->context();
        $other=Venue::forceCreate(['name'=>'Other venue']);$event->venues()->attach($other->id,['num_courts'=>1]);
        $options['venue_ids'][]=$other->id;$options['day_court_labels']=[$venue->id=>['1'],$other->id=>['1']];$options['wave_minutes']=90;
        $final=Fixture::factory()->create(['draw_id'=>$draws[0]->id,'round'=>2,'match_nr'=>2,'bracket_id'=>1,
            'registration1_id'=>null,'registration2_id'=>Registration::factory()->create()->id]);
        $fixtures[0]->update(['parent_fixture_id'=>$final->id,'feeder_slot'=>1]);
        $options['draw_rounds'][0]['rounds']=[1,2];$options['selected_fixture_keys'][]='individual:'.$final->id;
        \Spatie\Permission\Models\Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);$admin=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);$this->actingAs($admin);
        $url=route('backend.event-venue-schedule.preview',$event);
        $preview=$this->postJson($url,$options)->assertOk()->json();
        $move=['fixture_key'=>'individual:'.$final->id,'scheduled_at'=>'2026-10-30 09:30','venue_id'=>$other->id,'court'=>'1'];
        foreach ([['scheduled_at'=>'2026-10-30 09:00'],['venue_id'=>999999],['court'=>'missing'],['fixture_key'=>'team:'.$final->id]] as $invalid) {
            $this->postJson($url,$options+['revision'=>$preview['revision'],'preview_move'=>array_replace($move,$invalid)])->assertStatus(422);
            $this->assertDatabaseCount('order_of_plays',0);
        }
        $moved=$this->postJson($url,$options+['revision'=>$preview['revision'],'preview_move'=>$move])->assertOk()->json();
        $row=collect($moved['matches'])->firstWhere('fixture_id',$final->id);
        $this->assertSame('2026-10-30 09:30:00',$row['scheduled_at']);$this->assertSame($other->id,$row['venue_id']);$this->assertSame('1',$row['court']);
        $this->assertDatabaseCount('order_of_plays',0);$this->assertDatabaseCount('published_schedule_assignments',0);
        $movedOptions=$options+['preview_moves'=>$moved['input']['preview_moves']];
        $this->postJson(route('backend.event-venue-schedule.apply',$event),$movedOptions+['revision'=>$preview['revision']])->assertStatus(422);
        $this->postJson(route('backend.event-venue-schedule.apply',$event),$movedOptions+['revision'=>$moved['revision']])->assertOk()->assertJsonPath('count',3);
        $stored=json_decode(DB::table('event_venue_schedule_drafts')->where('event_id',$event->id)->value('options'),true);
        $this->assertEqualsCanonicalizing([$venue->id,$other->id],$stored['day_batch_allocations']['individual:'.$final->id]['venue_ids']);
        $this->assertArrayNotHasKey('preview_moves',$stored['day_batch_settings']['2026-10-30']);
        $this->postJson(route('backend.event-venue-schedule.manual-assignment',$event),['fixture_id'=>$final->id,'scheduled_at'=>'2026-10-30 12:00',
            'venue_id'=>$venue->id,'court'=>'1','duration'=>60,'player_rest'=>30,'court_gap'=>0])->assertOk();
        $this->assertDatabaseCount('order_of_plays',3);$this->assertDatabaseCount('published_schedule_assignments',0);
    }

    public function test_moving_a_suggested_team_rubber_keeps_the_whole_unbanded_tie_at_its_chosen_venue(): void
    {
        [$event,$draws,$venue,$fixtures,$options]=$this->context(true);
        $other=Venue::forceCreate(['name'=>'Another team venue']);$event->venues()->attach($other->id,['num_courts'=>2]);
        $options['venue_ids'][]=$other->id;$options['day_court_labels'][$other->id]=['1','2'];
        $extra=TeamFixture::create(['draw_id'=>$draws[0]->id,'round_nr'=>1,'tie_nr'=>1,'match_nr'=>2,'match_status'=>0]);
        $options['selected_fixture_keys'][]='team:'.$extra->id;
        $move=['fixture_key'=>'team:'.$extra->id,'scheduled_at'=>'2026-10-30 08:00','venue_id'=>$other->id,'court'=>'2'];
        $service=app(EventVenueScheduleService::class);$result=$service->preview($event,$options+['preview_moves'=>[$move]]);
        $this->assertSame([],$result['unscheduled']);
        $this->assertSame([$other->id],collect($result['matches'])->where('draw_id',$draws[0]->id)->pluck('venue_id')->unique()->values()->all());
        $this->assertSame('2',collect($result['matches'])->firstWhere('fixture_key','team:'.$extra->id)['court']);
        $this->assertSame(0,TeamFixture::whereNotNull('scheduled_at')->count());
        $conflict=['fixture_key'=>'team:'.$fixtures[0]->id,'scheduled_at'=>'2026-10-30 08:00','venue_id'=>$venue->id,'court'=>'1'];
        try {$service->preview($event,$options+['preview_moves'=>[$move,$conflict]]);$this->fail('One tie was split across venues.');}
        catch (\InvalidArgumentException $error) {$this->assertStringContainsString('same venue',$error->getMessage());}
        $this->assertDatabaseCount('published_schedule_assignments',0);
    }

    public function test_manual_move_preserves_every_other_suggestion_and_leaves_vacancy_and_unallocated_matches(): void
    {
        [$event,$draws,$venue,$fixtures,$options]=$this->context();$this->signInAdmin($event);$options['wave_minutes']=90;
        $waiting=Fixture::factory()->create(['draw_id'=>$draws[0]->id,'round'=>1,'match_nr'=>3,'bracket_id'=>1,
            'registration1_id'=>Registration::factory()->create()->id,'registration2_id'=>Registration::factory()->create()->id]);
        $waitingKey='individual:'.$waiting->id;$options['selected_fixture_keys'][]=$waitingKey;$options['preview_frozen_keys']=[$waitingKey];
        $url=route('backend.event-venue-schedule.preview',$event);$base=$this->postJson($url,$options)->assertOk()->json();
        $key='individual:'.$fixtures[0]->id;$original=collect($base['matches'])->firstWhere('fixture_key',$key);
        $move=['fixture_key'=>$key,'scheduled_at'=>'2026-10-30 10:00','venue_id'=>$venue->id,'court'=>'1'];
        $result=$this->postJson($url,$options+['revision'=>$base['revision'],'preview_move'=>$move])->assertOk()->json();
        $this->assertSame($this->signSlots(collect($base['matches'])->reject(fn ($row)=>$row['fixture_key']===$key)->all()),$this->signSlots(collect($result['matches'])->reject(fn ($row)=>$row['fixture_key']===$key)->all()));
        $this->assertSame([$waitingKey],array_column($result['unscheduled'],'fixture_key'));
        $this->assertFalse(collect($result['matches'])->contains(fn ($row)=>$row['scheduled_at']===$original['scheduled_at']&&$row['venue_id']===$original['venue_id']&&$row['court']===$original['court']));
        $preserved=array_replace($options,['preview_moves'=>$result['input']['preview_moves'],'preview_frozen_keys'=>$result['input']['preview_frozen_keys']]);
        $again=$this->postJson($url,$preserved)->assertOk()->json();$this->assertSame($this->signSlots($result['matches']),$this->signSlots($again['matches']));
        $this->postJson(route('backend.event-venue-schedule.apply',$event),$preserved+['revision'=>$result['revision']])->assertStatus(422);
        $this->assertDatabaseCount('order_of_plays',0);$this->assertDatabaseCount('published_schedule_assignments',0);
    }

    public function test_dependent_move_rejection_names_the_unchanged_match_and_needed_rest_release(): void
    {
        [$event,$draws,$venue,$fixtures,$options]=$this->context();$this->signInAdmin($event);$options['wave_minutes']=90;
        $fixtures[0]->update(['match_nr'=>5]);
        $final=Fixture::factory()->create(['draw_id'=>$draws[0]->id,'round'=>2,'match_nr'=>6,'bracket_id'=>1,'registration1_id'=>null,'registration2_id'=>Registration::factory()->create()->id]);
        $fixtures[0]->update(['parent_fixture_id'=>$final->id,'feeder_slot'=>1]);$options['draw_rounds'][0]['rounds']=[1,2];$options['selected_fixture_keys'][]='individual:'.$final->id;
        $url=route('backend.event-venue-schedule.preview',$event);$base=$this->postJson($url,$options)->assertOk()->json();
        $response=$this->postJson($url,$options+['revision'=>$base['revision'],'preview_move'=>['fixture_key'=>'individual:'.$fixtures[0]->id,'scheduled_at'=>'2026-10-30 09:05','venue_id'=>$venue->id,'court'=>'1']])->assertStatus(422);
        foreach (['Match 5 was not moved','Match 6 stays at 09:30','30 minutes player rest','10:35','Other match slots were not changed'] as $text) $this->assertStringContainsString($text,$response->json('message'));
        $this->assertDatabaseCount('order_of_plays',0);
    }

    public function test_saved_move_preserves_pending_slots_and_rolls_back_if_an_unchanged_dependent_would_break(): void
    {
        [$event,$draws,$venue,$fixtures,$options]=$this->context();$this->signInAdmin($event);$options['wave_minutes']=90;
        $draws[0]->venues()->attach($venue->id,['num_courts'=>2]);$fixtures[0]->update(['match_nr'=>5]);
        $final=Fixture::factory()->create(['draw_id'=>$draws[0]->id,'round'=>2,'match_nr'=>6,'bracket_id'=>1,'registration1_id'=>null,'registration2_id'=>Registration::factory()->create()->id]);
        $fixtures[0]->update(['parent_fixture_id'=>$final->id,'feeder_slot'=>1]);$options['draw_rounds'][0]['rounds']=[1,2];$options['selected_fixture_keys'][]='individual:'.$final->id;
        $slot=OrderOfPlay::create(['fixture_id'=>$fixtures[0]->id,'draw_id'=>$draws[0]->id,'venue_id'=>$venue->id,'court'=>'1','time'=>'2026-10-30 08:00','duration_minutes'=>60]);
        $url=route('backend.event-venue-schedule.preview',$event);$base=$this->postJson($url,$options)->assertOk()->json();
        $request=['fixture_id'=>$fixtures[0]->id,'venue_id'=>$venue->id,'court'=>'1','scheduled_at'=>'2026-10-30 09:05','duration'=>60,'player_rest'=>30,'court_gap'=>0,'preview_context'=>$options,'preview_revision'=>$base['revision']];
        $auditCount=\App\Models\DrawAuditLog::count();
        $rejected=$this->postJson(route('backend.event-venue-schedule.manual-assignment',$event),$request)->assertStatus(422);
        $this->assertStringContainsString('Match 6',$rejected->json('message'));$this->assertStringContainsString('10:35',$rejected->json('message'));
        $this->assertStringStartsWith('2026-10-30 08:00',$slot->fresh()->time);$this->assertSame($auditCount,\App\Models\DrawAuditLog::count());
        $saved=$this->postJson(route('backend.event-venue-schedule.manual-assignment',$event),array_replace($request,['scheduled_at'=>'2026-10-30 07:00']))->assertOk()->json();
        $this->assertSame($this->signSlots($base['matches']),$this->signSlots($saved['preview']['matches']));
        $this->assertStringStartsWith('2026-10-30 07:00',$slot->fresh()->time);
        $this->assertDatabaseCount('order_of_plays',1);$this->assertDatabaseCount('published_schedule_assignments',0);
    }
}
