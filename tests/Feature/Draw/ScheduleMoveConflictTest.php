<?php

namespace Tests\Feature\Draw;

use App\Models\{Category, CategoryEvent, Draw, Event, Fixture, OrderOfPlay, Registration, User, Venue};
use App\Services\Draw\FlexibleMonradService;
use App\Services\Scheduling\{EventVenueScheduleService, ScheduleMoveConflictService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleMoveConflictTest extends TestCase
{
    use RefreshDatabase;

    private function interpro(): array
    {
        $event = Event::factory()->create(['start_date'=>'2026-10-30', 'end_date'=>'2026-11-01']);
        $category = CategoryEvent::factory()->create(['event_id'=>$event->id, 'category_id'=>Category::factory()->create(['name'=>'Under 19 Boys'])->id]);
        $draw = Draw::factory()->create(['event_id'=>$event->id, 'category_event_id'=>$category->id, 'drawName'=>'U/19 Boys Draw']);
        $draw->settings()->create(['workflow'=>'feed_in_monrad', 'playoff_config'=>['challenge_playoffs'=>true, 'challenge_pairs'=>[2,4,6,8,10,12,14]]]);
        $venue = Venue::forceCreate(['name'=>'U/19 test courts']);
        $event->venues()->attach($venue->id, ['num_courts'=>8]);
        $draw->venues()->attach($venue->id, ['num_courts'=>8]);
        $slots = [];
        foreach (Registration::factory()->count(16)->create() as $index => $registration) {
            $registration->categoryEvents()->attach($category->id, ['status'=>'registered', 'payment_status_id'=>1]);
            $path = strtr(str_pad(decbin($index), 4, '0', STR_PAD_LEFT), ['0'=>'a','1'=>'b']);
            $slots[$path] = ['type'=>'player','id'=>$registration->id];
        }
        app(FlexibleMonradService::class)->save($draw, ['size'=>16,'slots'=>$slots], 0);
        app(FlexibleMonradService::class)->generate($draw, 1);
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);
        $this->actingAs($admin);
        $options = ['day_batch'=>true, 'full_day_scope'=>'2026-10-30', 'start'=>'2026-10-30 08:00', 'end'=>'2026-10-30 18:00',
            'draw_ids'=>[$draw->id], 'draw_order'=>[$draw->id], 'draw_order_mode'=>'mixed',
            'draw_rounds'=>[['draw_id'=>$draw->id,'rounds'=>[1,2,3,4,5,6]]],
            'selected_fixture_keys'=>$draw->drawFixtures()->pluck('id')->map(fn ($id)=>'individual:'.$id)->all(),
            'venue_ids'=>[$venue->id], 'day_court_labels'=>[$venue->id=>array_map('strval',range(1,8))],
            'duration'=>60,'player_rest'=>30,'court_gap'=>5,'wave_minutes'=>90,'reschedule_existing'=>false];
        return [$event, $draw->fresh(), $venue, $options];
    }

    public function test_interpro_move_names_the_later_match_required_start_and_only_offers_valid_preserved_slots(): void
    {
        [$event, $draw, $venue, $options] = $this->interpro();
        $url = route('backend.event-venue-schedule.preview', $event);
        $preview = $this->postJson($url, $options)->assertOk()->json();
        $this->assertCount(43, $preview['matches']);
        $moving = collect($preview['matches'])->firstWhere('match', 5);
        $move = ['fixture_key'=>$moving['fixture_key'], 'scheduled_at'=>'2026-10-30 09:05', 'venue_id'=>$venue->id, 'court'=>$moving['court']];
        $response = $this->postJson($url, $options+['revision'=>$preview['revision'],'preview_move'=>$move])->assertStatus(422);
        $report = $response->json('move_conflict');
        $this->assertSame(5, $report['moving']['match']);
        $dependent = collect($report['issues'])->firstWhere('type', 'dependent');
        $this->assertNotNull($dependent);
        $this->assertSame('U/19 Boys Draw', $dependent['match']['draw_name']);
        $this->assertSame('2026-10-30 10:35:00', $dependent['required_at']);
        $this->assertStringContainsString('first', $dependent['message']);
        $this->assertNotEmpty($report['suggestions']);
        foreach ($report['suggestions'] as $slot) {
            $candidate = array_intersect_key($slot, array_flip(['fixture_key','scheduled_at','venue_id','court']));
            $accepted = $this->postJson($url, $options+['revision'=>$preview['revision'],'preview_move'=>$candidate])->assertOk()->json();
            foreach ($preview['matches'] as $original) {
                if ($original['fixture_key'] === $moving['fixture_key']) continue;
                $retained = collect($accepted['matches'])->firstWhere('fixture_key', $original['fixture_key']);
                foreach (['scheduled_at','venue_id','court'] as $field) $this->assertSame($original[$field], $retained[$field]);
            }
        }
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
    }

    public function test_external_court_booking_is_explained_without_private_identifiers(): void
    {
        [$event, , $venue, $options] = $this->interpro();
        $foreign = Draw::factory()->create(['event_id'=>Event::factory()->create()->id,'drawName'=>'PRIVATE FOREIGN DRAW']);
        $fixture = Fixture::factory()->create(['draw_id'=>$foreign->id,'registration1_id'=>Registration::factory()->create()->id,'registration2_id'=>Registration::factory()->create()->id]);
        OrderOfPlay::create(['fixture_id'=>$fixture->id,'draw_id'=>$foreign->id,'venue_id'=>$venue->id,'court'=>'1','time'=>'2026-10-30 08:00','duration_minutes'=>60]);
        $url = route('backend.event-venue-schedule.preview', $event);
        $preview = $this->postJson($url, $options)->assertOk()->json();
        $moving = collect($preview['matches'])->first();
        $response = $this->postJson($url, $options+['revision'=>$preview['revision'],'preview_move'=>['fixture_key'=>$moving['fixture_key'],'scheduled_at'=>'2026-10-30 08:00','venue_id'=>$venue->id,'court'=>'1']])->assertStatus(422);
        $report = $response->json('move_conflict');
        $issue = collect($report['issues'])->first(fn ($row)=>$row['type']==='court' && $row['match']===null);
        $this->assertNotNull($issue);
        $this->assertStringNotContainsString('PRIVATE FOREIGN DRAW', json_encode($report));
        $this->assertStringNotContainsString('individual:'.$fixture->id.'"', json_encode($report));
        $this->assertDatabaseCount('order_of_plays', 1);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_requested_duration_and_programme_dependency_modes_control_the_explanation(): void
    {
        [$event, $draw, $venue] = $this->interpro();
        $moving = ['fixture_key'=>'individual:11','draw_id'=>$draw->id,'draw_name'=>'U/19','match'=>5,'scheduled_at'=>'2026-10-30 08:00','venue_id'=>$venue->id,'court'=>'1','duration'=>60,'dependencies'=>[],'participant_ids'=>[]];
        $later = array_replace($moving, ['fixture_key'=>'individual:12','match'=>6,'scheduled_at'=>'2026-10-30 09:30','court'=>'2','dependencies'=>[11]]);
        $preview = ['matches'=>[$moving,$later], 'existing_matches'=>[], 'unscheduled'=>[], 'venues'=>[['id'=>$venue->id,'name'=>'Courts']]];
        $move = ['fixture_key'=>'individual:11','scheduled_at'=>'2026-10-30 09:05','venue_id'=>$venue->id,'court'=>'1'];
        $service = app(ScheduleMoveConflictService::class);
        $options = ['duration'=>120,'player_rest'=>30];
        $report = $service->report($event,$options,$preview,$move,'Rejected',false);
        $this->assertSame('2026-10-30 11:35:00', $report['issues'][0]['required_at']);
        $preview['matches'][1]['day_finish_dependencies'] = [11];
        $report = $service->report($event,$options,$preview,$move,'Rejected',false);
        $this->assertSame('2026-10-30 11:05:00', $report['issues'][0]['required_at']);
        $preview['matches'][1]['day_finish_dependencies'] = [];
        $preview['matches'][1]['programme_start_dependencies'] = [11];
        $report = $service->report($event,$options,$preview,$move,'Rejected',false);
        $this->assertNotContains('dependent', array_column($report['issues'],'type'));
        $this->assertSame([], $report['suggestions']);
    }

    public function test_stale_revision_returns_no_conflict_details_or_suggestions(): void
    {
        [$event, , $venue, $options] = $this->interpro();
        $preview = app(EventVenueScheduleService::class)->preview($event,$options);
        $row = $preview['matches'][0];
        $response = $this->postJson(route('backend.event-venue-schedule.preview',$event),$options+['revision'=>str_repeat('0',64),'preview_move'=>['fixture_key'=>$row['fixture_key'],'scheduled_at'=>'2026-10-30 09:05','venue_id'=>$venue->id,'court'=>'1']])->assertStatus(422);
        $this->assertArrayNotHasKey('move_conflict', $response->json());
        $this->assertDatabaseCount('order_of_plays', 0);
    }
}
