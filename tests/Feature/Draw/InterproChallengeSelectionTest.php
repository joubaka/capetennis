<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw,Event,EventType,Fixture,OrderOfPlay,Registration,User,Venue};
use App\Services\Draw\{InterproChallengeSelectionService,InterproRoundRobinFormat};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus,DB,Http,Mail,Notification,Queue};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterproChallengeSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function draw(): Draw
    {
        Mail::fake(); Notification::fake(); Queue::fake(); Bus::fake(); Http::preventStrayRequests();
        $type=DB::table('eventtypes')->insertGetId(['name'=>'Fictional challenge trials','type'=>EventType::INDIVIDUAL,'code'=>EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event=Event::factory()->create(['eventType'=>$type,'result_notifications_enabled'=>false]);
        $draw=Draw::factory()->create(['event_id'=>$event->id,'published'=>false,'oop_published'=>false,'locked'=>false]);
        $draw->settings()->create(['workflow'=>'round_robin_playoffs','preset_key'=>InterproRoundRobinFormat::PRESET,
            'playoff_config'=>InterproRoundRobinFormat::configuration(),'score_format'=>'one_full_set','num_sets'=>1]);
        foreach (['A','B','C','D'] as $name) {
            $group=$draw->groups()->create(['name'=>$name]);
            $regs=Registration::factory()->count(4)->create();
            foreach ($regs as $index=>$reg) $group->registrations()->attach($reg->id,['seed'=>$index+1]);
            for ($i=0;$i<4;$i++) for ($j=$i+1;$j<4;$j++) {
                Fixture::factory()->create(['draw_id'=>$draw->id,'draw_group_id'=>$group->id,'stage'=>'RR',
                    'registration1_id'=>$regs[$i]->id,'registration2_id'=>$regs[$j]->id,'match_status'=>0,'winner_registration'=>null]);
            }
        }
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $draw->update(['published'=>true,'oop_published'=>true]);
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $admin=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);
        $this->actingAs($admin);
        return $draw->fresh();
    }

    private function submit(Draw $draw,array $pairs,?string $revision=null)
    {
        return $this->postJson(route('draw.interpro-challenges.append',$draw),['challenge_pairs'=>$pairs,
            'revision'=>$revision ?? app(InterproChallengeSelectionService::class)->revision($draw->fresh())]);
    }

    public function test_append_preserves_existing_records_publication_and_bookings_and_retry_is_idempotent(): void
    {
        $draw=$this->draw();
        $original=$draw->drawFixtures()->orderBy('id')->get();
        $rr=$original->firstWhere('stage','RR');
        $rr->update(['match_status'=>1,'winner_registration'=>$rr->registration1_id]);
        $rr->fixtureResults()->create(['set_nr'=>1,'registration1_score'=>6,'registration2_score'=>2,'winner_registration'=>$rr->registration1_id,'loser_registration'=>$rr->registration2_id]);
        $venue=Venue::forceCreate(['name'=>'Fictional challenge venue']);
        foreach ($original->where('stage','RR') as $fixture) {
            OrderOfPlay::create(['fixture_id'=>$fixture->id,'draw_id'=>$draw->id,'venue_id'=>$venue->id,'court'=>'1',
                'time'=>'2026-11-27 13:00','duration_minutes'=>45]);
            DB::table('published_schedule_assignments')->insert(['event_id'=>$draw->event_id,'draw_id'=>$draw->id,
                'fixture_kind'=>'individual','fixture_id'=>$fixture->id,'venue_id'=>$venue->id,'court'=>'1',
                'scheduled_at'=>'2026-11-27 13:00','published_at'=>now()]);
        }
        $bookings=DB::table('order_of_plays')->orderBy('id')->get()->toJson();
        $published=DB::table('published_schedule_assignments')->orderBy('id')->get()->toJson();
        $results=DB::table('fixture_results')->orderBy('id')->get()->toJson();
        $before=DB::table('fixtures')->where('draw_id',$draw->id)->orderBy('id')->get()->map(fn($row)=>(array)$row)->all();
        $revision=app(InterproChallengeSelectionService::class)->revision($draw->fresh());
        $html=view('backend.draw.roundrobin.interpro-challenge-settings',['draw'=>$draw->fresh(),
            'errors'=>new \Illuminate\Support\ViewErrorBag()])->render();
        $this->assertStringContainsString('Add selected challenges',$html);
        $this->assertStringContainsString('10 v 11',$html);
        $this->assertStringContainsString($revision,$html);
        $this->submit($draw,[2,4,6,8],$revision)->assertOk()->assertJsonPath('appended',4);
        $this->assertSame(46,$draw->drawFixtures()->count());
        $this->assertSame([5002,5004,5006,5008],$draw->drawFixtures()->where('stage','CHALLENGE')->orderBy('match_nr')->pluck('match_nr')->all());
        $this->assertSame($bookings,DB::table('order_of_plays')->orderBy('id')->get()->toJson());
        $this->assertSame($published,DB::table('published_schedule_assignments')->orderBy('id')->get()->toJson());
        $this->assertSame($results,DB::table('fixture_results')->orderBy('id')->get()->toJson());
        foreach ($before as $row) {
            $after=(array)DB::table('fixtures')->find($row['id']);
            foreach (['parent_fixture_id','loser_parent_fixture_id','updated_at'] as $field) {unset($row[$field],$after[$field]);}
            $this->assertSame($row,$after);
        }
        $this->assertTrue((bool)$draw->fresh()->published); $this->assertTrue((bool)$draw->fresh()->oop_published);
        $this->assertSame(1,DB::table('draw_audit_logs')->where('action','interpro_challenges_appended')->count());
        $this->submit($draw,[2,4,6,8],$revision)->assertOk()->assertJsonPath('appended',0);
        $this->assertSame(1,DB::table('draw_audit_logs')->where('action','interpro_challenges_appended')->count());
        $this->assertCount(0,app(InterproRoundRobinFormat::class)->prepare($draw->fresh()));
    }

    public function test_invalid_stale_foreign_unauthorized_and_removal_requests_have_no_writes(): void
    {
        $draw=$this->draw();
        $count=$draw->drawFixtures()->count();
        $this->submit($draw,[2,10,12])->assertUnprocessable();
        $this->submit($draw,[2,2])->assertUnprocessable();
        $this->submit($draw,[2],str_repeat('0',64))->assertConflict();
        $stale=app(InterproChallengeSelectionService::class)->revision($draw->fresh());
        $draw->settings()->update(['num_sets'=>3]);
        $this->submit($draw,[2],$stale)->assertConflict();
        $foreign=Draw::factory()->create();
        $this->submit($foreign,[2])->assertForbidden();
        $this->assertSame($count,$draw->drawFixtures()->count());
        $this->submit($draw,[2,4])->assertOk();
        $this->submit($draw,[2])->assertConflict();
        $this->assertSame($count+2,$draw->drawFixtures()->count());
        $user=User::factory()->create(); $this->actingAs($user);
        $this->submit($draw,[2,4,6])->assertForbidden();
        $this->assertSame($count+2,$draw->drawFixtures()->count());
    }

    public function test_route_collision_rolls_back_atomically_and_locked_draw_is_rejected(): void
    {
        $draw=$this->draw();
        $terminal=$draw->drawFixtures()->where('match_nr',3009)->firstOrFail();
        $terminal->update(['loser_parent_fixture_id'=>$draw->drawFixtures()->where('stage','RR')->value('id')]);
        $before=DB::table('fixtures')->where('draw_id',$draw->id)->orderBy('id')->get()->toJson();
        $settings=$draw->settings()->first()->getAttributes();
        $this->submit($draw,[2,4])->assertConflict();
        $this->assertSame($before,DB::table('fixtures')->where('draw_id',$draw->id)->orderBy('id')->get()->toJson());
        $this->assertSame($settings,$draw->settings()->first()->getAttributes());
        $draw->update(['locked'=>true]); $this->submit($draw,[2])->assertForbidden();
    }

    public function test_duplicate_graph_and_confirmed_qualification_are_rejected_without_writes(): void
    {
        $draw=$this->draw();
        $fixture=$draw->drawFixtures()->where('match_nr',3001)->firstOrFail();
        $duplicate=$fixture->replicate(); $duplicate->save();
        $before=DB::table('fixtures')->where('draw_id',$draw->id)->orderBy('id')->get()->toJson();
        $this->submit($draw,[2])->assertConflict();
        $this->assertSame($before,DB::table('fixtures')->where('draw_id',$draw->id)->orderBy('id')->get()->toJson());
        $duplicate->delete();
        $config=$draw->settings()->first()->playoff_config;
        $config[0]['trials_confirmed']=true; $draw->settings()->update(['playoff_config'=>$config]);
        $count=$draw->drawFixtures()->count();
        $this->submit($draw,[2])->assertConflict();
        $this->assertSame($count,$draw->drawFixtures()->count());
    }
}
