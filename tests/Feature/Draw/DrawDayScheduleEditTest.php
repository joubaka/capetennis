<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Registration, User, Venue};
use App\Services\Scheduling\{EventVenueScheduleService, SchedulePublicationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawDayScheduleEditTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $event = Event::factory()->create(['start_date'=>'2026-10-09', 'end_date'=>'2026-10-11']);
        $draw = Draw::factory()->create(['event_id'=>$event->id, 'published'=>true]);
        $source = Venue::forceCreate(['name'=>'Gericke']);
        $target = Venue::forceCreate(['name'=>'Charlie Hofmeyr']);
        $event->venues()->attach([$source->id=>['num_courts'=>2], $target->id=>['num_courts'=>2]]);
        $draw->venues()->attach($source->id, ['num_courts'=>2]);
        $fixture = $this->booking($draw, $source, '2026-10-11');
        return [$event, $draw, $source, $target, $fixture];
    }

    private function booking(Draw $draw, Venue $venue, string $date): Fixture
    {
        $fixture = Fixture::factory()->create(['draw_id'=>$draw->id, 'round'=>1, 'match_status'=>0,
            'registration1_id'=>Registration::factory()->create()->id, 'registration2_id'=>Registration::factory()->create()->id]);
        OrderOfPlay::create(['fixture_id'=>$fixture->id, 'draw_id'=>$draw->id, 'venue_id'=>$venue->id,
            'court'=>'1', 'time'=>$date.' 08:00:00', 'duration_minutes'=>60]);
        return $fixture;
    }

    private function dayOptions(Draw $draw, Venue $target): array
    {
        return ['day_scope'=>'2026-10-11', 'draw_ids'=>[$draw->id], 'venue_ids'=>[$target->id],
            'start'=>'2026-10-11 09:00:00', 'end'=>'2026-10-11 18:00:00', 'duration'=>60,
            'wave_minutes'=>0, 'player_rest'=>0, 'court_gap'=>0, 'rank_venue_preferences'=>[]];
    }

    public function test_preview_and_save_moves_only_existing_draw_day_and_preserves_configuration_and_public_times(): void
    {
        [$event,$draw,$source,$target,$fixture]=$this->context();
        $previous=$this->booking($draw,$source,'2026-10-10');
        $otherDraw=Draw::factory()->create(['event_id'=>$event->id]);
        $other=$this->booking($otherDraw,$target,'2026-10-11');
        $unplanned=Fixture::factory()->create(['draw_id'=>$draw->id,'round'=>2,'match_status'=>0]);
        $stored=['programme_settings'=>['12'=>['duration'=>120], '14'=>['duration'=>90]],
            'round_venue_setups'=>[['draw_id'=>$draw->id,'round'=>1,'venue_ids'=>[$source->id],
                'court_allocations'=>[['venue_id'=>$source->id,'court_labels'=>['1','2']]],'rank_venue_preferences'=>[]]]];
        DB::table('event_venue_schedule_drafts')->insert(['event_id'=>$event->id,'options'=>json_encode($stored),'created_at'=>now(),'updated_at'=>now()]);
        $publication=app(SchedulePublicationService::class);$publication->publish($event,['date'=>'2026-10-11','draw_id'=>$draw->id]);
        $scheduler=app(EventVenueScheduleService::class);$options=$this->dayOptions($draw,$target);
        $preview=$scheduler->preview($event,$options);
        $this->assertCount(1,$preview['matches']);
        $this->assertSame($fixture->id,$preview['matches'][0]['fixture_id']);
        $this->assertSame($target->id,$preview['matches'][0]['venue_id']);
        $this->assertSame($source->id,(int)$fixture->orderOfPlay()->first()->venue_id);
        $this->assertSame(1,$scheduler->apply($event,$options,$preview['revision'])['count']);
        $this->assertSame($target->id,(int)$fixture->orderOfPlay()->first()->venue_id);
        $this->assertSame('2026-10-10 08:00:00',$previous->orderOfPlay()->first()->time);
        $this->assertSame('2026-10-11 08:00:00',$other->orderOfPlay()->first()->time);
        $this->assertNull($unplanned->orderOfPlay);
        $this->assertDatabaseCount('order_of_plays',3);
        $this->assertDatabaseCount('fixtures',4);
        $this->assertEquals($stored,json_decode(DB::table('event_venue_schedule_drafts')->where('event_id',$event->id)->value('options'),true));
        $this->assertEquals([$source->id],$draw->venues()->pluck('venues.id')->all());
        $this->assertSame($source->id,(int)$publication->publishedAssignments($event)->first()['venue_id']);
    }

    public function test_day_edit_mixed_team_moves_preserve_scored_and_other_day_matches(): void
    {
        [$event,$draw,$source,$target,$individual]=$this->context();
        $team=\App\Models\TeamFixture::forceCreate(['draw_id'=>$draw->id,'match_nr'=>1,'round_nr'=>1,
            'scheduled_at'=>'2026-10-11 10:00:00','venue_id'=>$source->id,'court_label'=>'2','match_status'=>0]);
        $played=\App\Models\TeamFixture::forceCreate(['draw_id'=>$draw->id,'match_nr'=>2,'round_nr'=>1,
            'scheduled_at'=>'2026-10-11 12:00:00','venue_id'=>$source->id,'court_label'=>'2','match_status'=>1]);
        $other=\App\Models\TeamFixture::forceCreate(['draw_id'=>$draw->id,'match_nr'=>3,'round_nr'=>1,
            'scheduled_at'=>'2026-10-10 12:00:00','venue_id'=>$source->id,'court_label'=>'2','match_status'=>0]);
        $teamDraw=Draw::factory()->create(['event_id'=>$event->id,'team_category_id'=>1]);
        $teamDraw->venues()->attach($source->id,['num_courts'=>2]);
        foreach ([$team,$played,$other] as $rubber) $rubber->update(['draw_id'=>$teamDraw->id]);
        $service=app(EventVenueScheduleService::class);$options=$this->dayOptions($teamDraw,$target);
        $preview=$service->preview($event,$options);
        $this->assertCount(1,$preview['matches']);
        $this->assertSame($team->id,$preview['matches'][0]['fixture_id']);
        $service->apply($event,$options,$preview['revision']);
        $this->assertSame($target->id,(int)$team->fresh()->venue_id);
        $this->assertSame($source->id,(int)$played->fresh()->venue_id);
        $this->assertSame($source->id,(int)$other->fresh()->venue_id);
        $this->assertSame('2026-10-10',$other->fresh()->scheduled_at->toDateString());
        $this->assertDatabaseCount('team_fixtures',3);
    }

    public function test_scoped_edit_cannot_move_a_prerequisite_past_fixed_individual_match_or_save_partial_plan(): void
    {
        [$event,$draw,$source,$target,$fixture]=$this->context();
        $later=$this->booking($draw,$source,'2026-10-11');
        $later->orderOfPlay()->update(['time'=>'2026-10-11 10:00:00']);
        $fixture->update(['parent_fixture_id'=>$later->id]);
        $later->update(['match_status'=>1]);
        $options=array_replace($this->dayOptions($draw,$target),['start'=>'2026-10-11 11:00:00']);
        $service=app(EventVenueScheduleService::class);$preview=$service->preview($event,$options);
        $this->assertNotEmpty($preview['unscheduled']);
        try {$service->apply($event,$options,$preview['revision']);$this->fail('Expected unresolved dependency rejection');}
        catch (\InvalidArgumentException $exception) {$this->assertStringContainsString('unscheduled',$exception->getMessage());}
        $this->assertSame($source->id,(int)$fixture->orderOfPlay()->first()->venue_id);
        $this->assertDatabaseCount('order_of_plays',2);
    }

    public function test_day_bands_and_physical_court_subset_control_preview_and_revision_without_changing_defaults(): void
    {
        [$event,$draw,$source,$target]=$this->context();
        $draw->update(['team_category_id'=>1]);
        $category=\App\Models\CategoryEvent::factory()->create(['event_id'=>$event->id]);
        $teams=[\App\Models\Team::factory()->create(['category_event_id'=>$category->id]), \App\Models\Team::factory()->create(['category_event_id'=>$category->id])];
        $tie=\App\Models\TeamTie::factory()->create(['draw_id'=>$draw->id,'home_team_id'=>$teams[0]->id,'away_team_id'=>$teams[1]->id]);
        $rubber=\App\Models\TeamFixture::create(['draw_id'=>$draw->id,'team_tie_id'=>$tie->id,'round_nr'=>1,'match_nr'=>1,'fixture_type'=>1,'player_count_per_team'=>1,'match_status'=>0,'scheduled_at'=>'2026-10-11 08:00:00','venue_id'=>$source->id,'court_label'=>'1']);
        $slot=['team_fixture_id'=>$rubber->id,'slot_no'=>1];
        foreach ([2,5] as $side=>$rank) {
            $player=\App\Models\Player::factory()->create();
            \App\Models\TeamPlayer::create(['team_id'=>$teams[$side]->id,'player_id'=>$player->id,'rank'=>$rank,'pay_status'=>0]);
            $slot['team'.($side+1).'_id']=$player->id;
        }
        \App\Models\TeamFixturePlayer::create($slot);
        $stored=['round_venue_setups'=>[['draw_id'=>$draw->id,'round'=>1,'venue_ids'=>[$source->id],
            'court_allocations'=>[['venue_id'=>$source->id,'court_labels'=>['1']]],
            'rank_venue_preferences'=>[['draw_ids'=>[$draw->id],'min_rank'=>1,'max_rank'=>10,'venue_id'=>$source->id]]]]];
        DB::table('event_venue_schedule_drafts')->insert(['event_id'=>$event->id,'options'=>json_encode($stored),'created_at'=>now(),'updated_at'=>now()]);
        $options=array_replace($this->dayOptions($draw,$target),['venue_ids'=>[$source->id,$target->id],
            'day_court_labels'=>[$source->id=>['1'],$target->id=>['2']],
            'rank_venue_preferences'=>[['min_rank'=>1,'max_rank'=>4,'venue_id'=>$target->id],['min_rank'=>5,'max_rank'=>10,'venue_id'=>$source->id]],
            'cross_band_policy'=>'highest_ranked']);
        $service=app(EventVenueScheduleService::class);$preview=$service->preview($event,$options);
        $this->assertCount(1,$preview['matches']);$this->assertSame($target->id,$preview['matches'][0]['venue_id']);$this->assertSame('2',$preview['matches'][0]['court']);
        foreach ([['day_court_labels'=>[$source->id=>['1'],$target->id=>['1']]],['cross_band_policy'=>'manual'],
            ['rank_venue_preferences'=>[['min_rank'=>1,'max_rank'=>10,'venue_id'=>$source->id]]]] as $change) {
            $changed=array_replace($options,$change);$this->assertNotSame($preview['revision'],$service->preview($event,$changed)['revision']);
            try {$service->apply($event,$changed,$preview['revision']);$this->fail('Expected changed options rejection');}
            catch (\InvalidArgumentException $exception) {$this->assertStringContainsString('fresh preview',$exception->getMessage());}
        }
        $this->assertNotEmpty($service->preview($event,array_replace($options,['cross_band_policy'=>'manual']))['unscheduled']);
        $service->apply($event,$options,$preview['revision']);
        $this->assertSame('2',$rubber->fresh()->court_label);
        $this->assertEquals($stored,json_decode(DB::table('event_venue_schedule_drafts')->where('event_id',$event->id)->value('options'),true));
        $this->assertEquals([$source->id],$draw->venues()->pluck('venues.id')->all());
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);$admin=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);$this->actingAs($admin);
        $url=route('backend.event-venue-schedule.calendar.edit-day',$event);
        $this->get($url.'?'.http_build_query(['draw_id'=>$draw->id,'date'=>'2026-10-11']))->assertOk()->assertSee('last saved edit');
        $payload=['draw_id'=>$draw->id,'date'=>'2026-10-11','venue_ids'=>[$source->id,$target->id],
            'court_labels_present'=>1,'court_labels'=>[$source->id=>['1'],$target->id=>['2']],
            'start_time'=>'09:00','end_time'=>'18:00','duration'=>60,'player_rest'=>0,'court_gap'=>0,'action'=>'preview',
            'bands'=>[['min_rank'=>1,'max_rank'=>4,'venue_id'=>$target->id],['min_rank'=>5,'max_rank'=>6,'venue_id'=>$source->id],['min_rank'=>7,'max_rank'=>10,'venue_id'=>$source->id]]];
        $response=$this->post($url,$payload)->assertOk()->assertSee('Position bands for this day');
        if (getenv('CT_DAY_EDIT_BROWSER_FIXTURE') === '1') file_put_contents(storage_path('framework/testing/team-day-bands.html'),$response->getContent());
        $invalid=array_replace($payload,['action'=>'save','revision'=>str_repeat('0',64)]);unset($invalid['bands']);
        $this->post($url,$invalid)->assertRedirect()->assertSessionHasInput('bands',[]);
    }

    public function test_invalid_day_courts_and_bands_are_rejected_and_default_band_validation_stays_strict(): void
    {
        [$event,$draw,$source,$target]=$this->context();$draw->update(['team_category_id'=>1]);
        DB::table('event_venue_courts')->insert([
            ['event_id'=>$event->id,'venue_id'=>$target->id,'label'=>'A','active'=>true],
            ['event_id'=>$event->id,'venue_id'=>$target->id,'label'=>'B','active'=>false],
        ]);
        $base=$this->dayOptions($draw,$target);$service=app(EventVenueScheduleService::class);
        foreach ([['day_court_labels'=>[$target->id=>['B']]],['day_court_labels'=>[$target->id=>['A','A']]],
            ['day_court_labels'=>[$target->id=>['C']]],['day_court_labels'=>[$source->id=>['1']]],['day_court_labels'=>[]],
            ['rank_venue_preferences'=>[['min_rank'=>1,'max_rank'=>5,'venue_id'=>$target->id],['min_rank'=>5,'max_rank'=>8,'venue_id'=>$target->id]]],
            ['rank_venue_preferences'=>[['min_rank'=>1,'max_rank'=>101,'venue_id'=>$target->id]]],
            ['rank_venue_preferences'=>[['min_rank'=>1,'max_rank'=>5,'venue_id'=>$source->id]]]] as $change) {
            try {$service->preview($event,array_replace($base,$change));$this->fail('Expected invalid day allocation rejection');}
            catch (\InvalidArgumentException $exception) {$this->assertNotEmpty($exception->getMessage());}
        }
        try {app(\App\Services\Scheduling\RankVenuePreferences::class)->normalize($event,[['draw_ids'=>[$draw->id],'min_rank'=>1,'max_rank'=>5,'venue_id'=>$target->id]]);$this->fail('General rules must still require assigned venue');}
        catch (\InvalidArgumentException $exception) {$this->assertStringContainsString('assigned',$exception->getMessage());}
        $this->assertDatabaseCount('order_of_plays',1);
    }

    public function test_http_allows_same_court_label_at_two_venues_and_rejects_empty_selection(): void
    {
        [$event,$draw,$source,$target]=$this->context();
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);$admin=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);$this->actingAs($admin);
        $url=route('backend.event-venue-schedule.calendar.edit-day',$event);
        $payload=['date'=>'2026-10-11','draw_id'=>$draw->id,'venue_ids'=>[$source->id,$target->id],
            'court_labels_present'=>1,'court_labels'=>[$source->id=>['1'],$target->id=>['1']],
            'start_time'=>'09:00','end_time'=>'18:00','duration'=>60,'player_rest'=>0,'court_gap'=>0,'action'=>'preview'];
        $this->post($url,$payload)->assertOk()->assertSee('Number of courts for this day');
        $this->post($url,array_replace($payload,['court_labels'=>[]]))->assertRedirect()->assertSessionHasErrors('schedule');
        $invalid=array_replace($payload,['duration'=>999]);unset($invalid['court_labels']);
        $this->post($url,$invalid)->assertRedirect()->assertSessionHasErrors('duration')->assertSessionHasInput('bands',[])->assertSessionHasInput('court_labels',[]);
        $this->assertDatabaseCount('order_of_plays',1);
    }

    public function test_scoped_edit_rejects_foreign_venues_other_days_and_multiple_draws(): void
    {
        [$event,$draw,$source,$target]=$this->context();$service=app(EventVenueScheduleService::class);
        foreach ([['venue_ids'=>[Venue::forceCreate(['name'=>'Foreign'])->id]], ['end'=>'2026-10-12 18:00:00'],
            ['draw_ids'=>[$draw->id,Draw::factory()->create(['event_id'=>$event->id])->id]]] as $change) {
            try {$service->preview($event,array_replace($this->dayOptions($draw,$target),$change));$this->fail('Expected scope rejection');}
            catch (\InvalidArgumentException $exception) {$this->assertNotEmpty($exception->getMessage());}
        }
        $this->assertDatabaseCount('order_of_plays',1);
    }

    public function test_editor_requires_event_authorization_and_preview_revision(): void
    {
        [$event,$draw,$source,$target,$fixture]=$this->context();
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);$admin=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);
        $url=route('backend.event-venue-schedule.calendar.edit-day',$event);
        $payload=['date'=>'2026-10-11','draw_id'=>$draw->id,'venue_ids'=>[$target->id],'start_time'=>'09:00','end_time'=>'18:00',
            'duration'=>60,'player_rest'=>0,'court_gap'=>0,'action'=>'preview'];
        $this->actingAs($admin)->get(route('backend.event-venue-schedule.calendar',['event'=>$event->id,'draw_id'=>$draw->id,'date'=>'all']))->assertOk()->assertDontSee('Change venues / reschedule this draw');
        $this->actingAs($admin)->get($url.'?'.http_build_query(['date'=>'2026-10-11','draw_id'=>$draw->id]))->assertOk()->assertSee('Gericke')->assertSee('Charlie Hofmeyr')->assertSee('Current saved schedule');
        $response=$this->post($url,$payload)->assertOk()->assertSee('Proposed schedule')->assertSee('Save preview');
        if (getenv('CT_DAY_EDIT_BROWSER_FIXTURE') === '1') {
            file_put_contents(storage_path('framework/testing/draw-day-edit.html'), $response->getContent());
        }
        $this->post($url,array_replace($payload,['action'=>'save','revision'=>str_repeat('0',64)]))->assertRedirect()->assertSessionHasErrors('schedule');
        $this->assertSame($source->id,(int)$fixture->orderOfPlay()->first()->venue_id);
        $this->get(route('backend.event-venue-schedule.calendar.edit-day',['event'=>Event::factory()->create()->id,'date'=>'2026-10-11','draw_id'=>$draw->id]))->assertForbidden();
        $revision=app(EventVenueScheduleService::class)->preview($event,$this->dayOptions($draw,$target))['revision'];
        $this->post($url,array_replace($payload,['action'=>'save','revision'=>$revision]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($target->id,(int)$fixture->orderOfPlay()->first()->venue_id);
    }
}

