<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, EventConvenor, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnscheduledScoringPrerequisitesTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        foreach (['admin','score-keeper'] as $role) Role::firstOrCreate(['name'=>$role,'guard_name'=>'web']);
        $event=Event::factory()->create(['eventType'=>6,'published'=>true]);
        $actor=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$actor->id]);
        $this->actingAs($actor);
        $category=CategoryEvent::factory()->create(['event_id'=>$event->id]);
        $draw=Draw::factory()->create(['event_id'=>$event->id,'category_event_id'=>$category->id,'published'=>true,'oop_published'=>false,'locked'=>false]);
        $draw->settings()->create(['workflow'=>'round_robin_playoffs','score_format'=>'timed','num_sets'=>1,
            'match_duration_minutes'=>15,'changeover_minutes'=>0,'playoff_config'=>[]]);
        $registrations=collect(range(1,3))->map(function($i)use($category){
            $registration=Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create(['name'=>'Prerequisite '.$i,'surname'=>'Example'])->id);
            CategoryEventRegistration::factory()->create(['category_event_id'=>$category->id,'registration_id'=>$registration->id,'payment_status_id'=>1]);
            return $registration;
        });
        $parent=Fixture::factory()->create(['draw_id'=>$draw->id,'stage'=>'CONS','round'=>2,'match_nr'=>1002,
            'playoff_type'=>'Positions 5–7 final','registration1_id'=>null,'registration2_id'=>$registrations[2]->id,'winner_registration'=>null,'match_status'=>0]);
        $preliminary=Fixture::factory()->create(['draw_id'=>$draw->id,'stage'=>'CONS','round'=>1,'match_nr'=>1003,
            'parent_fixture_id'=>$parent->id,'registration1_id'=>$registrations[0]->id,'registration2_id'=>$registrations[1]->id,
            'winner_registration'=>null,'match_status'=>0]);
        $completed=Fixture::factory()->create(['draw_id'=>$draw->id,'stage'=>'MAIN','round'=>1,'match_nr'=>1,
            'registration1_id'=>$registrations[0]->id,'registration2_id'=>$registrations[2]->id,
            'winner_registration'=>$registrations[0]->id,'match_status'=>1]);
        $completed->fixtureResults()->create(['set_nr'=>1,'registration1_score'=>6,'registration2_score'=>4,
            'winner_registration'=>$registrations[0]->id,'loser_registration'=>$registrations[2]->id]);
        $venue=Venue::forceCreate(['name'=>'Prerequisite courts']);
        $event->venues()->attach($venue->id,['num_courts'=>3]); $draw->venues()->attach($venue->id,['num_courts'=>3]);
        foreach ([$parent,$completed] as $fixture) OrderOfPlay::create(['fixture_id'=>$fixture->id,'draw_id'=>$draw->id,
            'venue_id'=>$venue->id,'court'=>'3','time'=>'2026-11-14 10:45:00','duration_minutes'=>15,'gap_minutes'=>0]);
        return [$event,$draw,$venue,$preliminary,$parent,$completed,$actor];
    }

    private function url(Event $event, Venue $venue, array $extra=[]): string
    {
        return route('frontend.scoring.workspace',['event'=>$event,'venue'=>$venue->id,'date'=>'2026-11-14','schedule_source'=>'working']+$extra);
    }

    private function snapshot(): array
    {
        return collect(['fixtures','fixture_results','order_of_plays','schedules','published_schedule_assignments'])
            ->mapWithKeys(fn($table)=>[$table=>DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_working_venue_shows_a_separate_prerequisite_without_queue_or_schedule_changes_and_normal_score_advances_parent(): void
    {
        [$event,$draw,$venue,$preliminary,$parent,$completed]=$this->scenario(); $before=$this->snapshot();
        $page=$this->get($this->url($event,$venue))->assertOk()->assertSee('Unscheduled prerequisites')
            ->assertSee('data-unscheduled-prerequisite="'.$preliminary->id.'"',false)->assertSee('Positions 5–7 final');
        $this->assertSame([$preliminary->id],$page->viewData('unscheduledPrerequisites')->pluck('id')->all());
        $this->assertSame([$completed->id,$parent->id],$page->viewData('matches')->sortBy('match_nr')->pluck('id')->all());
        $this->assertSame(1,$page->viewData('completed')); $this->assertSame(1,$page->viewData('ready'));
        $this->assertCount(0,$page->viewData('nextMatches'));
        $this->assertSame($before,$this->snapshot());
        $document=new \DOMDocument(); $previous=libxml_use_internal_errors(true); $document->loadHTML($page->getContent(),LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($previous); $xpath=new \DOMXPath($document);
        $card=$xpath->query('//*[@data-unscheduled-prerequisite="'.$preliminary->id.'"]')->item(0);
        $this->assertSame(0,$xpath->query('.//*[contains(@class,"js-toggle-match")]',$card)->length);
        $button=$xpath->query('.//button[contains(@class,"js-open-score")]',$card)->item(0);
        foreach (['data-walkover-winner','data-retired-registration','data-deciding-winner','data-home-registration','data-away-registration','data-match-minutes'] as $attribute) $this->assertTrue($button->hasAttribute($attribute));
        $this->assertSame('15',$button->getAttribute('data-match-minutes'));
        $booking=OrderOfPlay::where('fixture_id',$parent->id)->firstOrFail()->getAttributes();
        $originalScore=$completed->fixtureResults()->firstOrFail()->getAttributes();
        $this->postJson(route('api.draws.fixtures.score.store',['draw'=>$draw,'fixture'=>$preliminary]),['sets'=>['12-10']])->assertOk();
        $this->assertSame($preliminary->registration1_id,$parent->fresh()->registration1_id);
        $this->assertSame($booking,OrderOfPlay::where('fixture_id',$parent->id)->firstOrFail()->getAttributes());
        $this->assertSame($originalScore,$completed->fixtureResults()->firstOrFail()->getAttributes());
        $this->assertSame($before['order_of_plays'],$this->snapshot()['order_of_plays']);
        $this->get($this->url($event,$venue))->assertOk()->assertViewHas('unscheduledPrerequisites',fn($fixtures)=>$fixtures->isEmpty());
    }

    public function test_ancestor_discovery_is_transitive_but_excludes_unrelated_completed_booked_and_cross_draw_or_event_matches(): void
    {
        [$event,$draw,$venue,$preliminary,$parent]=$this->scenario();
        $new=function(array $extra=[])use($draw,$preliminary,$parent){ return Fixture::factory()->create(array_replace([
            'draw_id'=>$draw->id,'stage'=>'CONS','parent_fixture_id'=>$parent->id,'registration1_id'=>$preliminary->registration1_id,
            'registration2_id'=>$preliminary->registration2_id,'winner_registration'=>null,'match_status'=>0],$extra)); };
        $preliminary->update(['registration1_id'=>null]);
        $leaf=$new(['parent_fixture_id'=>$preliminary->id]);
        $unrelated=$new(['parent_fixture_id'=>null]);
        $scored=$new(['winner_registration'=>$leaf->registration1_id,'match_status'=>1]);
        $scored->fixtureResults()->create(['set_nr'=>1,'registration1_score'=>6,'registration2_score'=>0,'winner_registration'=>$leaf->registration1_id]);
        $otherVenue=Venue::forceCreate(['name'=>'Elsewhere courts']); $event->venues()->attach($otherVenue->id,['num_courts'=>1]);
        $booked=$new(); OrderOfPlay::create(['draw_id'=>$draw->id,'fixture_id'=>$booked->id,'venue_id'=>$otherVenue->id,
            'court'=>'1','time'=>'2026-11-14 09:00','duration_minutes'=>15,'gap_minutes'=>0]);
        $legacy=$new(); DB::table('schedules')->insert(['draw_id'=>$draw->id,'fixture_id'=>$legacy->id,'venue_id'=>$otherVenue->id,'time'=>'2026-11-14 09:00']);
        $published=$new(); DB::table('published_schedule_assignments')->insert(['event_id'=>$event->id,'draw_id'=>$draw->id,'fixture_kind'=>'individual',
            'fixture_id'=>$published->id,'scheduled_at'=>'2026-11-14 09:00','venue_id'=>$otherVenue->id,'published_at'=>now()]);
        $other=Draw::factory()->create(['event_id'=>$event->id]);
        $other->settings()->create(['workflow'=>'round_robin_playoffs','score_format'=>'timed','num_sets'=>1,'match_duration_minutes'=>15,'changeover_minutes'=>0]);
        $anchor=$new(['draw_id'=>$other->id,'parent_fixture_id'=>null]);
        OrderOfPlay::create(['draw_id'=>$other->id,'fixture_id'=>$anchor->id,'venue_id'=>$venue->id,'time'=>'2026-11-14 11:00','duration_minutes'=>15,'gap_minutes'=>0]);
        $crossDraw=$new(['draw_id'=>$other->id]);
        $foreign=$new(['draw_id'=>Draw::factory()->create(['event_id'=>Event::factory()->create()->id])->id]);
        $before=$this->snapshot(); $page=$this->get($this->url($event,$venue))->assertOk();
        $this->assertSame([$leaf->id,$preliminary->id],$page->viewData('unscheduledPrerequisites')->pluck('id')->all());
        foreach ([$unrelated,$scored,$booked,$legacy,$published,$crossDraw,$foreign] as $excluded) $page->assertDontSee('data-unscheduled-prerequisite="'.$excluded->id.'"',false);
        $this->assertSame($before,$this->snapshot());
        $this->get($this->url($event,$otherVenue))->assertOk()->assertViewHas('unscheduledPrerequisites',fn($fixtures)=>$fixtures->isEmpty());
        $this->get(route('frontend.scoring.workspace',['event'=>$event,'venue'=>$venue->id,'date'=>'2026-11-15']))->assertOk()
            ->assertViewHas('unscheduledPrerequisites',fn($fixtures)=>$fixtures->isEmpty());
    }

    public function test_published_views_and_restricted_scorekeepers_do_not_gain_unscheduled_actions(): void
    {
        [$event,$draw,$venue,$preliminary,$parent]=$this->scenario();
        $draw->update(['oop_published'=>true]);
        DB::table('published_schedule_assignments')->insert(['event_id'=>$event->id,'draw_id'=>$draw->id,'fixture_kind'=>'individual',
            'fixture_id'=>$parent->id,'scheduled_at'=>'2026-11-14 10:45','venue_id'=>$venue->id,'court'=>'3','duration'=>15,'published_at'=>now()]);
        $before=$this->snapshot();
        $this->get(route('frontend.scoring.workspace',['event'=>$event,'venue'=>$venue->id,'schedule_source'=>'published']))->assertOk()
            ->assertViewHas('unscheduledPrerequisites',fn($fixtures)=>$fixtures->isEmpty())->assertDontSee('data-unscheduled-prerequisites',false);
        $keeper=User::factory()->create()->assignRole('score-keeper');
        EventConvenor::create(['event_id'=>$event->id,'user_id'=>$keeper->id,'role'=>'score-keeper','venue_id'=>$venue->id]);
        $this->actingAs($keeper)->get($this->url($event,$venue))->assertOk()->assertViewHas('unscheduledPrerequisites',fn($fixtures)=>$fixtures->isEmpty())
            ->assertDontSee('data-fixture="'.$preliminary->id.'"',false);
        $this->postJson(route('api.draws.fixtures.score.store',['draw'=>$draw,'fixture'=>$preliminary]),['sets'=>['12-10']])->assertForbidden();
        $this->assertSame($before,$this->snapshot());
    }

    public function test_invalid_unbooked_timed_defaults_show_settings_notice_without_breaking_the_booked_queue(): void
    {
        [$event,$draw,$venue,$preliminary]=$this->scenario();
        $draw->settings->update(['match_duration_minutes'=>null,'changeover_minutes'=>null]); $before=$this->snapshot();
        $page=$this->get($this->url($event,$venue))->assertOk()->assertSee('Review timed settings before entering this unscheduled match.')
            ->assertSee('Open draw settings')->assertDontSee('data-fixture="'.$preliminary->id.'"',false);
        $this->assertCount(2,$page->viewData('matches')); $this->assertSame($before,$this->snapshot());
    }
}
