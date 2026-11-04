<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, TeamFixture, User, Venue};
use App\Services\Scheduling\{TeamAgeVenueSetup, EventVenueScheduleService};
use App\Services\TeamScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamAgeVenueSetupTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $event = Event::factory()->create(['start_date' => '2026-11-06', 'end_date' => '2026-11-08']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);
        $venue = Venue::forceCreate(['name'=>'Age ten courts']);
        $other = Venue::forceCreate(['name'=>'Age twelve courts']);
        $event->venues()->attach([$venue->id=>['num_courts'=>3],$other->id=>['num_courts'=>2]]);
        $draws = collect();
        foreach ([10,12] as $age) foreach (['singles','reverse_singles','doubles','mixed_doubles'] as $code) {
            $draw = Draw::factory()->create(['event_id'=>$event->id,'drawName'=>'Under '.$age.' '.$code]);
            $draw->forceFill(['team_category_id'=>1,'team_draw_selection'=>['rubber_code'=>$code]])->save();
            $draw->venues()->attach($other->id,['num_courts'=>2]);
            foreach ([1,2] as $round) TeamFixture::create(['draw_id'=>$draw->id,'round_nr'=>$round,'match_nr'=>1,'fixture_type'=>1,'rubber_code'=>$code,'rubber_sequence'=>1,'player_count_per_team'=>1]);
            $draws->push($draw);
        }
        $this->actingAs($admin);
        $data = ['age'=>10,'revision'=>app(TeamAgeVenueSetup::class)->state($event,10)['revision'],
            'court_allocations'=>[['venue_id'=>$venue->id,'court_labels'=>['Court 1','2']]],
            'rank_venue_preferences'=>[['min_rank'=>1,'max_rank'=>10,'venue_id'=>$venue->id]]];
        return [$event,$draws,$venue,$other,$data];
    }

    public function test_age_setup_expands_every_discipline_round_and_preserves_other_ages_bookings_and_publication(): void
    {
        [$event,$draws,$venue,$other,$data] = $this->scenario();
        $fixture=$draws->first()->fixtures()->first();
        $fixture->update(['scheduled_at'=>'2026-11-06 08:00','venue_id'=>$other->id,'court_label'=>'1','duration_min'=>30]);
        $data['revision']=app(TeamAgeVenueSetup::class)->state($event,10)['revision'];
        $before=$fixture->fresh()->getAttributes();
        $response=$this->postJson(route('backend.event-venue-schedule.assignments',$event),['age_setup'=>$data])->assertOk();
        $rows=$response->json('round_venue_setups');
        $this->assertCount(8,$rows);
        foreach($rows as $row) {
            $this->assertContains($row['draw_id'],$draws->take(4)->pluck('id')->all());
            $this->assertSame([$venue->id],$row['venue_ids']);
            $this->assertSame(['1','2'],$row['court_allocations'][0]['court_labels']);
            $this->assertSame(1,$row['rank_venue_preferences'][0]['min_rank']);
        }
        $this->assertSame($before,$fixture->fresh()->getAttributes());
        $this->assertCount(0,$draws->skip(4)->flatMap(fn($draw)=>$draw->fixtures)->filter(fn($row)=>$row->scheduled_at));
        $this->assertSame(['1','2'],$response->json('state.setup.court_allocations.0.court_labels'));
        $this->assertDatabaseCount('published_schedule_assignments',0);
        $again=$data; $again['revision']=$response->json('state.revision');
        $this->postJson(route('backend.event-venue-schedule.assignments',$event),['age_setup'=>$again])->assertOk()->assertJsonCount(8,'round_venue_setups');
    }

    public function test_foreign_venue_invalid_bands_and_missing_rounds_leave_no_partial_setup(): void
    {
        [$event,$draws,,,$data]=$this->scenario();
        $foreign=Venue::forceCreate(['name'=>'Foreign courts']);
        $data['court_allocations'][0]['venue_id']=$foreign->id;
        $this->postJson(route('backend.event-venue-schedule.assignments',$event),['age_setup'=>$data])->assertStatus(422);
        $this->assertDatabaseCount('event_venue_schedule_drafts',0);
        $draws[2]->fixtures()->delete();
        $data['revision']=app(TeamAgeVenueSetup::class)->state($event,10)['revision'];
        $this->postJson(route('backend.event-venue-schedule.assignments',$event),['age_setup'=>$data])->assertStatus(422)->assertJsonFragment(['message'=>'Generate every discipline in this age group before saving its shared setup.']);
        $this->assertDatabaseCount('event_venue_schedule_drafts',0);
    }

    public function test_stale_and_unauthorized_saves_are_rejected(): void
    {
        [$event,$draws,,,$data]=$this->scenario();
        $draws[0]->forceFill(['locked'=>true])->save();
        $this->postJson(route('backend.event-venue-schedule.assignments',$event),['age_setup'=>$data])->assertStatus(409);
        $this->assertDatabaseCount('event_venue_schedule_drafts',0);
        $this->actingAs(User::factory()->create());
        $this->postJson(route('backend.event-venue-schedule.assignments',$event),['age_setup'=>$data])->assertForbidden();
    }

    public function test_preview_missing_ranks_stays_in_age_pool_and_legacy_auto_cannot_bypass_it(): void
    {
        [$event,$draws,$venue,,$data]=$this->scenario();
        app(TeamAgeVenueSetup::class)->save($event,$data,auth()->id());
        $options=['draw_ids'=>$draws->pluck('id')->all(),'start'=>'2026-11-06 08:00','end'=>'2026-11-08 18:00','duration'=>30,'wave_minutes'=>30,'court_gap'=>0,'player_rest'=>0];
        $preview=app(EventVenueScheduleService::class)->preview($event,$options);
        $ageRows=collect($preview['matches'])->whereIn('draw_id',$draws->take(4)->pluck('id'));
        $this->assertCount(8,$ageRows);
        $this->assertSame([$venue->id],$ageRows->pluck('venue_id')->unique()->all());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(TeamScheduleService::class)->automatic($draws->take(4),['start'=>'2026-11-06 08:00','end'=>'2026-11-08 18:00','duration'=>30]);
    }

    public function test_page_exposes_shared_age_editor_before_days_and_order_bands_do_not_overlap(): void
    {
        [$event,,,,$data]=$this->scenario();
        $this->get(route('backend.event-venue-schedule.index',$event))->assertOk()->assertSeeInOrder(['Age group','Choose this age group','Assign playing-order numbers','3. Arrange playing days'],false);
        $data['rank_venue_preferences'][]=$data['rank_venue_preferences'][0];
        $this->postJson(route('backend.event-venue-schedule.assignments',$event),['age_setup'=>$data])->assertStatus(422);
        $this->assertDatabaseCount('event_venue_schedule_drafts',0);
    }

    public function test_manual_legacy_assignment_obeys_shared_age_court_pool(): void
    {
        [$event,$draws,,$other,$data]=$this->scenario();
        app(TeamAgeVenueSetup::class)->save($event,$data,auth()->id());
        $fixture=$draws[0]->fixtures()->first();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(TeamScheduleService::class)->save($draws[0],['fixture_id'=>$fixture->id,'scheduled_at'=>'2026-11-06 08:00','venue_id'=>$other->id,'court_label'=>'1','duration_min'=>30]);
    }

    public function test_other_age_setup_and_nonempty_published_history_are_preserved(): void
    {
        [$event,$draws,$venue,$other,$data]=$this->scenario();
        $service=app(TeamAgeVenueSetup::class);
        $otherData=['age'=>12,'revision'=>$service->state($event,12)['revision'],
            'court_allocations'=>[['venue_id'=>$other->id,'court_labels'=>['1']]],'rank_venue_preferences'=>[]];
        $service->save($event,$otherData,auth()->id());
        $fixture=$draws[0]->fixtures()->first();
        DB::table('published_schedule_assignments')->insert(['event_id'=>$event->id,'draw_id'=>$draws[0]->id,'fixture_kind'=>'team','fixture_id'=>$fixture->id,
            'scheduled_at'=>'2026-11-06 08:00','venue_id'=>$venue->id,'court'=>'1','duration'=>30,'published_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $published=DB::table('published_schedule_assignments')->get()->toJson();
        $before=json_decode(DB::table('event_venue_schedule_drafts')->where('event_id',$event->id)->value('options'),true);
        $data['revision']=$service->state($event,10)['revision'];$service->save($event,$data,auth()->id());
        $after=json_decode(DB::table('event_venue_schedule_drafts')->where('event_id',$event->id)->value('options'),true);
        $this->assertSame($before['team_age_venue_setups']['12'],$after['team_age_venue_setups']['12']);
        $this->assertSame(collect($before['round_venue_setups'])->whereIn('draw_id',$draws->skip(4)->pluck('id'))->values()->all(),collect($after['round_venue_setups'])->whereIn('draw_id',$draws->skip(4)->pluck('id'))->values()->all());
        $this->assertSame($published,DB::table('published_schedule_assignments')->get()->toJson());
    }

    public function test_legacy_unschedule_uses_fixture_and_draw_identity_when_numeric_ids_collide(): void
    {
        [$event,$draws,$venue,$other,$data]=$this->scenario();
        app(TeamAgeVenueSetup::class)->save($event,$data,auth()->id());
        $selected=$draws[2]->fixtures()->orderByDesc('id')->first();
        $unrelatedDraw=$draws->firstWhere('id',$selected->id);
        $this->assertNotNull($unrelatedDraw);
        $this->assertNotSame($draws[2]->id,$unrelatedDraw->id);
        $unrelated=$unrelatedDraw->fixtures()->first();
        $selected->update(['scheduled_at'=>'2026-11-06 10:00','venue_id'=>$venue->id,'court_label'=>'1','duration_min'=>30]);
        $unrelated->update(['scheduled_at'=>'2026-11-06 10:00','venue_id'=>$other->id,'court_label'=>'1','duration_min'=>30]);
        $before=$unrelated->fresh()->getAttributes();
        app(TeamScheduleService::class)->save($draws[2],['fixture_id'=>$selected->id,'scheduled_at'=>null]);
        $this->assertNull($selected->fresh()->scheduled_at);
        $this->assertSame($before,$unrelated->fresh()->getAttributes());
    }

    public function test_advanced_draw_setup_retains_shared_age_metadata_and_flags_round_exceptions(): void
    {
        [$event,$draws,$venue,$other,$data]=$this->scenario();
        $service=app(TeamAgeVenueSetup::class);$saved=$service->save($event,$data,auth()->id());
        foreach ([[$venue,3],[$other,2]] as [$physical,$count]) foreach (range(1,$count) as $label) {
            DB::table('event_venue_courts')->insert(['event_id'=>$event->id,'venue_id'=>$physical->id,'label'=>(string)$label,'active'=>true,'ball_type'=>'standard','created_at'=>now(),'updated_at'=>now()]);
        }
        $response=$this->postJson(route('backend.event-venue-schedule.assignments',$event),[
            'setup_only'=>true,'venues'=>[['id'=>$venue->id,'courts'=>3],['id'=>$other->id,'courts'=>2]],
            'schedule'=>['rank_preference_draw_ids'=>[$draws[4]->id],'rank_venue_preferences'=>[]],
            'assignments'=>[['draw_id'=>$draws[4]->id,'venue_ids'=>[$other->id],'court_allocations'=>[['venue_id'=>$other->id,'court_labels'=>['1']]]]],
        ]);
        $this->assertSame(200,$response->status(),$response->getContent());
        $this->assertSame($saved['state']['setup'],$service->state($event,10)['setup']);
        $row=$saved['round_venue_setups'][0];$row['court_allocations'][0]['court_labels']=['2'];
        $this->postJson(route('backend.event-venue-schedule.assignments',$event),['setup_only'=>true,'round_venue_setups'=>[$row]])->assertOk();
        $this->assertTrue($service->state($event,10)['exceptions']);
        $this->assertSame($saved['state']['setup'],$service->state($event,10)['setup']);
    }
}
