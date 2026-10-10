<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, DrawAuditLog, Event, Fixture, OrderOfPlay, Registration, User, Venue};
use App\Services\Scheduling\{EventVenueScheduleService, SchedulePublicationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchedulePublicationTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $event = Event::factory()->create(['start_date'=>'2026-10-09','end_date'=>'2026-10-11']);
        $draw = Draw::factory()->create(['event_id'=>$event->id,'published'=>true,'oop_published'=>false]);
        $venue = Venue::forceCreate(['name'=>'Weekend courts']);
        $event->venues()->attach($venue->id,['num_courts'=>1]); $draw->venues()->attach($venue->id,['num_courts'=>1]);
        $fixture = Fixture::factory()->create(['draw_id'=>$draw->id,'registration1_id'=>Registration::factory()->create()->id,'registration2_id'=>Registration::factory()->create()->id,'round'=>1,'match_nr'=>1,'match_status'=>0]);
        OrderOfPlay::create(['fixture_id'=>$fixture->id,'draw_id'=>$draw->id,'venue_id'=>$venue->id,'court'=>'1','time'=>'2026-10-10 08:00:00','duration_minutes'=>60]);
        return [$event,$draw,$venue,$fixture];
    }

    public function test_day_clear_preserves_other_scopes_and_public_snapshot(): void
    {
        [$event, $draw, $venue, $fixture] = $this->context();
        $publication = app(SchedulePublicationService::class);
        $publication->publish($event, ['date' => '2026-10-10']);
        $otherDraw = Draw::factory()->create(['event_id' => $event->id]);
        foreach ([[$draw, '2026-10-11'], [$otherDraw, '2026-10-10']] as [$targetDraw, $date]) {
            $other = Fixture::factory()->create(['draw_id' => $targetDraw->id, 'round' => 1, 'match_status' => 0]);
            OrderOfPlay::create(['fixture_id' => $other->id, 'draw_id' => $targetDraw->id, 'venue_id' => $venue->id, 'court' => '2', 'time' => $date.' 10:00:00']);
        }
        $scope = ['date' => '2026-10-10', 'draw_id' => $draw->id];
        $result = app(EventVenueScheduleService::class)->clearDay($event, $scope, $publication->revision($event));
        $this->assertSame(1, $result['count']);
        $this->assertDatabaseCount('order_of_plays', 2);
        $this->assertDatabaseCount('fixtures', 3);
        $this->assertDatabaseCount('published_schedule_assignments', 1);
        $this->assertSame('2026-10-10 08:00:00', $publication->publishedRows($event)->first()['scheduled_at']);
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'schedule_day_cleared']);
        $this->assertSame(0, app(EventVenueScheduleService::class)->clearDay($event, $scope, $publication->revision($event))['count']);
    }

    public function test_day_clear_rejects_played_locked_and_stale_selections_without_changes(): void
    {
        [$event, $draw, $venue, $fixture] = $this->context();
        $scheduler = app(EventVenueScheduleService::class);
        $publication = app(SchedulePublicationService::class);
        $scope = ['date' => '2026-10-10'];
        foreach (['played', 'locked', 'stale'] as $reason) {
            $fixture->update(['match_status' => $reason === 'played' ? 1 : 0]);
            $draw->update(['locked' => $reason === 'locked']);
            try {
                $scheduler->clearDay($event, $scope, $reason === 'stale' ? str_repeat('0', 64) : $publication->revision($event));
                $this->fail('Expected '.$reason.' rejection');
            } catch (\InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $this->assertDatabaseCount('order_of_plays', 1);
        }
    }

    public function test_day_editor_and_clear_endpoint_are_authorized_and_event_scoped(): void
    {
        [$event, $draw, $venue, $fixture] = $this->context();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $scope = ['date' => '2026-10-10', 'draw_id' => $draw->id];
        $this->actingAs($admin)->get(route('backend.event-venue-schedule.calendar', ['event' => $event->id] + $scope))
            ->assertOk()->assertSee('Day 1')->assertSee('Day 2')->assertSee('Day 3')->assertSee('08:00')->assertSee('Clear this day')->assertSee('Add matches to this day');
        $this->get(route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => [$draw->id], 'date' => '2026-10-11']))
            ->assertOk()->assertSee('2026-10-11T08:00', false)->assertSee('2026-10-11T18:00', false);
        $url = route('backend.event-venue-schedule.calendar.clear', $event);
        $payload = $scope + ['revision' => app(SchedulePublicationService::class)->revision($event)];
        $this->post($url, array_replace($payload, ['draw_id' => Draw::factory()->create()->id]))->assertUnprocessable();
        $this->post($url, array_replace($payload, ['date' => 'all']))->assertSessionHasErrors('date');
        $this->post(route('backend.event-venue-schedule.calendar.clear', Event::factory()->create()), $payload)->assertForbidden();
        $this->post($url, $payload)->assertRedirect(route('backend.event-venue-schedule.calendar', ['event' => $event->id] + $scope));
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->get(route('backend.event-venue-schedule.calendar', ['event' => $event->id] + $scope))->assertOk()->assertSee('Day 1')->assertSee('Day 2')->assertSee('Day 3');
    }

    public function test_day_clear_team_protection_rolls_back_mixed_selection_and_then_clears_both(): void
    {
        [$event, $draw, $venue, $individual] = $this->context();
        $tie = \App\Models\TeamTie::factory()->create(['draw_id' => $draw->id]);
        $team = \App\Models\TeamFixture::forceCreate(['match_nr' => 1, 'draw_id' => $draw->id,
            'team_tie_id' => $tie->id, 'scheduled_at' => '2026-10-10 09:00:00', 'venue_id' => $venue->id,
            'court_label' => '2', 'match_status' => 0]);
        $service = app(EventVenueScheduleService::class);
        $publication = app(SchedulePublicationService::class);
        foreach (['in_progress', 'completed', 'result'] as $state) {
            $team->update(['match_status' => $state === 'in_progress' ? 1 : 0]);
            $tie->update(['status' => $state === 'completed' ? \App\Models\TeamTie::STATUS_COMPLETED : \App\Models\TeamTie::STATUS_DRAFT]);
            if ($state === 'result') \App\Models\TeamFixtureResult::create(['team_fixture_id' => $team->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 4]);
            try {
                $service->clearDay($event, ['date' => '2026-10-10'], $publication->revision($event));
                $this->fail('Expected protected team match rejection');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('Played', $exception->getMessage());
            }
            $this->assertDatabaseCount('order_of_plays', 1);
            $this->assertNotNull($team->fresh()->scheduled_at);
        }
        \App\Models\TeamFixtureResult::where('team_fixture_id', $team->id)->delete();
        $this->assertSame(2, $service->clearDay($event, ['date' => '2026-10-10'], $publication->revision($event))['count']);
        $this->assertNull($team->fresh()->scheduled_at);
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertDatabaseCount('team_fixtures', 1);
        $this->assertDatabaseCount('fixtures', 1);
    }

    public function test_day_clear_venue_filter_and_foreign_event_isolation(): void
    {
        [$event, $draw, $venue, $fixture] = $this->context();
        $otherVenue = Venue::forceCreate(['name' => 'Other courts']);
        $otherEvent = Event::factory()->create();
        $foreignDraw = Draw::factory()->create(['event_id' => $otherEvent->id]);
        foreach ([[$draw, $otherVenue], [$foreignDraw, $venue]] as [$targetDraw, $targetVenue]) {
            $other = Fixture::factory()->create(['draw_id' => $targetDraw->id, 'round' => 1, 'match_status' => 0]);
            OrderOfPlay::create(['fixture_id' => $other->id, 'draw_id' => $targetDraw->id, 'venue_id' => $targetVenue->id, 'court' => '3', 'time' => '2026-10-10 10:00:00']);
        }
        $publication = app(SchedulePublicationService::class);
        $this->assertSame(1, app(EventVenueScheduleService::class)->clearDay($event,
            ['date' => '2026-10-10', 'venue_id' => $venue->id], $publication->revision($event))['count']);
        $this->assertDatabaseCount('order_of_plays', 2);
        $this->assertDatabaseHas('order_of_plays', ['draw_id' => $foreignDraw->id]);
        $this->assertDatabaseHas('order_of_plays', ['venue_id' => $otherVenue->id]);
    }

    public function test_day_clear_keeps_prerequisite_when_later_day_depends_on_it(): void
    {
        [$event, $draw, $venue, $fixture] = $this->context();
        $later = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 2, 'match_status' => 0]);
        $fixture->update(['parent_fixture_id' => $later->id]);
        OrderOfPlay::create(['fixture_id' => $later->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-11 10:00:00']);
        try {
            app(EventVenueScheduleService::class)->clearDay($event, ['date' => '2026-10-10'], app(SchedulePublicationService::class)->revision($event));
            $this->fail('Expected dependent day protection');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('depends', $exception->getMessage());
        }
        $this->assertDatabaseCount('order_of_plays', 2);
    }

    public function test_private_edits_hold_previous_public_time_and_weekend_republish_moves_one_pointer(): void
    {
        [$event,$draw,$venue,$fixture]=$this->context();$service=app(SchedulePublicationService::class);
        $this->assertCount(0,$service->publishedRows($event));
        $scope=['date'=>'2026-10-10','venue_id'=>$venue->id]; $service->publish($event,$scope);
        $fixture->orderOfPlay()->update(['time'=>'2026-10-11 10:00:00']);
        $this->assertSame('2026-10-10 08:00:00',$service->publishedRows($event)->first()['scheduled_at']);
        $service->publish($event,['date'=>'2026-10-11','venue_id'=>$venue->id,'draw_id'=>$draw->id]);
        $this->assertSame('2026-10-11 10:00:00',$service->publishedRows($event)->first()['scheduled_at']);
        $this->assertDatabaseCount('published_schedule_assignments',1);
        $service->publish($event,['date'=>'2026-10-11','venue_id'=>$venue->id]);
        $this->assertDatabaseCount('published_schedule_assignments',1);
        $service->hide($event,['date'=>'2026-10-11','venue_id'=>$venue->id]);
        $this->assertCount(0,$service->publishedRows($event));
        $audit=DrawAuditLog::where('draw_id',$draw->id)->where('action','schedule_scope_hidden')->latest('id')->first();
        $this->assertSame('2026-10-11 10:00:00',$audit->payload['before'][0]['scheduled_at']);
        $this->assertSame([],$audit->payload['after']);
    }

    public function test_scoped_publish_preserves_other_day_and_other_draw_and_rejects_foreign_draw(): void
    {
        [$event,$draw,$venue,$fixture]=$this->context();$service=app(SchedulePublicationService::class);
        $other=Draw::factory()->create(['event_id'=>$event->id,'published'=>true]);
        $second=Fixture::factory()->create(['draw_id'=>$other->id]);
        OrderOfPlay::create(['fixture_id'=>$second->id,'draw_id'=>$other->id,'venue_id'=>$venue->id,'court'=>'1','time'=>'2026-10-11 08:00:00']);
        $service->publish($event,['date'=>'2026-10-10','venue_id'=>$venue->id,'draw_id'=>$draw->id]);
        $this->assertDatabaseCount('published_schedule_assignments',1);
        $this->assertFalse((bool) $other->fresh()->oop_published);
        $this->expectException(\InvalidArgumentException::class);
        $service->publish($event,['draw_id'=>Draw::factory()->create()->id]);
    }

    public function test_stale_calendar_revision_does_not_publish_or_change_working_bookings(): void
    {
        [$event,$draw,$venue,$fixture]=$this->context();$service=app(SchedulePublicationService::class);$revision=$service->revision($event);
        $fixture->orderOfPlay()->update(['time'=>'2026-10-10 09:00:00']);
        try {$service->publish($event,['date'=>'2026-10-10','venue_id'=>$venue->id,'revision'=>$revision]);$this->fail('Expected stale revision rejection');}
        catch (\InvalidArgumentException $e) {$this->assertStringContainsString('Refresh',$e->getMessage());}
        $this->assertDatabaseCount('published_schedule_assignments',0);
        $this->assertSame('2026-10-10 09:00:00',$fixture->orderOfPlay()->first()->time);
    }

    public function test_calendar_is_read_only_event_scoped_and_public_preview_excludes_private_edits(): void
    {
        [$event,$draw,$venue,$fixture]=$this->context();
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);$admin=User::factory()->create()->assignRole('admin');DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);
        $this->actingAs($admin)->get(route('backend.event-venue-schedule.calendar',['event'=>$event->id,'date'=>'2026-10-10','venue_id'=>$venue->id]))->assertOk()->assertSee('Private')->assertSee('08:00');
        $this->assertDatabaseCount('published_schedule_assignments',0);
        app(SchedulePublicationService::class)->publish($event,['date'=>'2026-10-10','venue_id'=>$venue->id]);$fixture->orderOfPlay()->update(['time'=>'2026-10-10 11:30:00']);
        $previewResponse=$this->get(route('backend.event-venue-schedule.calendar.preview',$event))->assertOk()->assertSee('08:00')->assertDontSee('11:30');
        if (getenv('CT_WEEKEND_BROWSER_FIXTURE') === '1') {
            $extra=Fixture::factory()->create(['draw_id'=>$draw->id]);
            OrderOfPlay::create(['fixture_id'=>$extra->id,'draw_id'=>$draw->id,'venue_id'=>$venue->id,'court'=>'1','time'=>'2026-10-11 09:00:00']);
            $calendarResponse=$this->get(route('backend.event-venue-schedule.calendar',['event'=>$event->id,'date'=>'all']));
            file_put_contents(storage_path('framework/testing/weekend-calendar.html'),$calendarResponse->getContent());
            file_put_contents(storage_path('framework/testing/weekend-calendar-day.html'),$this->get(route('backend.event-venue-schedule.calendar',['event'=>$event->id,'date'=>'2026-10-10']))->getContent());
            file_put_contents(storage_path('framework/testing/weekend-public-preview.html'),$previewResponse->getContent());
        }
        $this->get(route('backend.event-venue-schedule.calendar',Event::factory()->create()))->assertForbidden();
        $this->post(route('backend.event-venue-schedule.calendar.publish',Event::factory()->create()),['date'=>'2026-10-10','revision'=>str_repeat('0',64)])->assertForbidden();
        $this->post(route('backend.event-venue-schedule.calendar.publish',$event),['date'=>'2026-10-10','venue_id'=>Venue::forceCreate(['name'=>'Foreign venue'])->id,'revision'=>str_repeat('0',64)])->assertUnprocessable();
        $this->get(route('backend.event-venue-schedule.calendar',['event'=>$event->id,'draw_id'=>Draw::factory()->create()->id]))->assertUnprocessable();
    }

    public function test_migration_retry_preserves_snapshot_and_captures_unpublished_draw_preview(): void
    {
        [$event,$draw,$venue,$fixture]=$this->context();
        $draw->update(['published'=>false,'oop_published'=>true]);
        $migration=require database_path('migrations/2026_10_05_040000_create_published_schedule_assignments.php');$migration->up();
        $this->assertDatabaseCount('published_schedule_assignments',1);
        $fixture->orderOfPlay()->update(['time'=>'2026-10-10 12:00:00']);$migration->up();
        $this->assertSame('2026-10-10 08:00:00',DB::table('published_schedule_assignments')->value('scheduled_at'));
        $this->assertCount(0,app(SchedulePublicationService::class)->publishedRows($event));
        DB::table('published_schedule_assignments')->where('draw_id',$draw->id)->delete();
        $migration->up();
        $this->assertDatabaseCount('published_schedule_assignments',0);
    }

    public function test_partial_batch_saves_fitting_matches_leaves_fixed_and_unscheduled_bookings(): void
    {
        [$event,$draw,$venue,$fixed]=$this->context();
        foreach(range(2,3) as $match) Fixture::factory()->create(['draw_id'=>$draw->id,'round'=>1,'match_nr'=>$match,'registration1_id'=>Registration::factory()->create()->id,'registration2_id'=>Registration::factory()->create()->id,'match_status'=>0]);
        $options=['start'=>'2026-10-10 09:00:00','end'=>'2026-10-10 10:00:00','duration'=>60,'wave_minutes'=>60,'court_gap'=>0,'player_rest'=>0,'allow_partial'=>true];
        $scheduler=app(EventVenueScheduleService::class);$preview=$scheduler->preview($event,$options);
        $this->assertNotEmpty($preview['unscheduled']);$this->assertNotEmpty($preview['matches']);
        $applied=$scheduler->apply($event,$options,$preview['revision']);$this->assertSame(1,$applied['count']);
        $this->assertSame('2026-10-10 08:00:00',$fixed->orderOfPlay()->first()->time);
        $this->assertDatabaseCount('order_of_plays',2);$this->assertDatabaseCount('published_schedule_assignments',0);
        $this->assertNotSame($preview['revision'],$scheduler->preview($event,array_replace($options,['allow_partial'=>false]))['revision']);
    }
    public function test_partial_replan_cannot_overlap_the_original_slot_of_an_unplaced_match(): void
    {
        [$event,$draw,$venue,$first]=$this->context();
        $first->orderOfPlay()->update(['time'=>'2026-10-10 09:00:00']);
        $second=Fixture::factory()->create(['draw_id'=>$draw->id,'round'=>1,'match_nr'=>2,'registration1_id'=>Registration::factory()->create()->id,'registration2_id'=>Registration::factory()->create()->id,'match_status'=>0]);
        OrderOfPlay::create(['fixture_id'=>$second->id,'draw_id'=>$draw->id,'venue_id'=>$venue->id,'court'=>'1','time'=>'2026-10-10 08:00:00','duration_minutes'=>60]);
        $options=['start'=>'2026-10-10 08:00:00','end'=>'2026-10-10 09:00:00','duration'=>60,'wave_minutes'=>60,'court_gap'=>0,'player_rest'=>0,'allow_partial'=>true,'replan_venue_ids'=>[$venue->id]];
        $scheduler=app(EventVenueScheduleService::class);$preview=$scheduler->preview($event,$options);
        $scheduler->apply($event,$options,$preview['revision']);
        $this->assertSame('2026-10-10 09:00:00',$first->orderOfPlay()->first()->time);
        $this->assertSame('2026-10-10 08:00:00',$second->orderOfPlay()->first()->time);
        $this->assertDatabaseCount('order_of_plays',2);
    }

    public function test_hiding_empty_legacy_draw_clears_its_publication_flag(): void
    {
        [$event,$draw,$venue,$fixture]=$this->context();$fixture->orderOfPlay()->delete();$draw->update(['oop_published'=>true]);
        app(\App\Domain\Draws\Services\DrawSchedulePublicationService::class)->unpublish($draw);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
    }

    public function test_whole_day_publication_can_cover_all_venues_and_keeps_next_day_private(): void
    {
        [$event,$draw,$venue,$first]=$this->context();$otherVenue=Venue::forceCreate(['name'=>'Other venue']);$event->venues()->attach($otherVenue->id,['num_courts'=>1]);
        $second=Fixture::factory()->create(['draw_id'=>$draw->id]);OrderOfPlay::create(['fixture_id'=>$second->id,'draw_id'=>$draw->id,'venue_id'=>$otherVenue->id,'court'=>'1','time'=>'2026-10-10 09:00:00']);
        $third=Fixture::factory()->create(['draw_id'=>$draw->id]);OrderOfPlay::create(['fixture_id'=>$third->id,'draw_id'=>$draw->id,'venue_id'=>$otherVenue->id,'court'=>'1','time'=>'2026-10-11 09:00:00']);
        app(SchedulePublicationService::class)->publish($event,['date'=>'2026-10-10']);
        $this->assertDatabaseCount('published_schedule_assignments',2);
        $this->assertDatabaseMissing('published_schedule_assignments',['fixture_id'=>$third->id]);
        $migration=require database_path('migrations/2026_10_05_040000_create_published_schedule_assignments.php');$migration->up();
        $this->assertDatabaseCount('published_schedule_assignments',2);
        $this->assertDatabaseMissing('published_schedule_assignments',['fixture_id'=>$third->id]);
    }

    public function test_individual_and_team_fixture_ids_do_not_collide_in_publication_or_projection(): void
    {
        [$event,$draw,$venue,$individual]=$this->context();
        $team=\App\Models\TeamFixture::forceCreate(['id'=>$individual->id,'match_nr'=>1,'draw_id'=>$draw->id,'scheduled_at'=>'2026-10-11 13:00:00','venue_id'=>$venue->id,'court_label'=>'2','duration_min'=>null,'match_status'=>0]);
        app(SchedulePublicationService::class)->publish($event,['draw_id'=>$draw->id]);
        $this->assertDatabaseCount('published_schedule_assignments',2);
        $this->assertSame(120,(int) app(SchedulePublicationService::class)->publishedRows($event)->firstWhere('fixture_kind','team')['duration']);
        $team->update(['scheduled_at'=>'2026-10-11 15:00:00']);
        $projected=app(SchedulePublicationService::class)->projectFixtures(collect([$individual->fresh(),$team->fresh()]));
        $this->assertSame('2026-10-10 08:00:00',$projected[0]->orderOfPlay->time);
        $this->assertSame('2026-10-11 13:00:00',$projected[1]->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_one_venue_replan_rejects_shared_player_overlap_with_a_withheld_venue(): void
    {
        [$event,$draw,$venue,$first]=$this->context();$first->orderOfPlay()->update(['time'=>'2026-10-10 11:00:00']);
        $otherVenue=Venue::forceCreate(['name'=>'Second courts']);$event->venues()->attach($otherVenue->id,['num_courts'=>1]);
        $otherDraw=Draw::factory()->create(['event_id'=>$event->id,'drawName'=>'Second draw']);$otherDraw->venues()->attach($otherVenue->id,['num_courts'=>1]);
        $second=Fixture::factory()->create(['draw_id'=>$otherDraw->id,'round'=>1,'match_nr'=>1,'registration1_id'=>$first->registration1_id,'registration2_id'=>Registration::factory()->create()->id,'match_status'=>0]);
        OrderOfPlay::create(['fixture_id'=>$second->id,'draw_id'=>$otherDraw->id,'venue_id'=>$otherVenue->id,'court'=>'1','time'=>'2026-10-10 08:00:00','duration_minutes'=>60]);
        $options=['start'=>'2026-10-10 08:00:00','end'=>'2026-10-10 12:00:00','duration'=>60,'wave_minutes'=>60,'court_gap'=>0,'player_rest'=>0,'allow_partial'=>true,'replan_venue_ids'=>[$venue->id,$otherVenue->id]];
        $scheduler=app(EventVenueScheduleService::class);$preview=$scheduler->preview($event,$options);
        $this->assertSame('2026-10-10 08:00:00',collect($preview['matches'])->firstWhere('fixture_id',$first->id)['scheduled_at']);
        try {$scheduler->apply($event,$options+['apply_venue_ids'=>[$venue->id]],$preview['revision']);$this->fail('Expected retained booking conflict');}
        catch (\InvalidArgumentException $e) {$this->assertStringContainsString('booking that is being kept',$e->getMessage());}
        $this->assertSame('2026-10-10 11:00:00',$first->orderOfPlay()->first()->time);
        $scheduler->apply($event,$options,$preview['revision']);
        $this->assertSame('2026-10-10 08:00:00',$first->orderOfPlay()->first()->time);
        $this->assertSame('2026-10-10 09:00:00',$second->orderOfPlay()->first()->time);
    }

}
