<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\TennisScoreFormat;
use App\Models\{Draw,Event,EventType,Fixture,OrderOfPlay,Registration,User,Venue};
use App\Services\Draw\{IndividualScoreRevision,InterproRoundRobinFormat,InterproScoringCorrectionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus,DB,Http,Mail,Notification,Queue};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterproMatchTiebreakCorrectionTest extends TestCase
{
    use RefreshDatabase;
    private function context(): Draw
    {
        Mail::fake();Notification::fake();Queue::fake();Bus::fake();Http::preventStrayRequests();
        $type=DB::table('eventtypes')->insertGetId(['name'=>'Fictional tiebreak trials','type'=>EventType::INDIVIDUAL,'code'=>EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event=Event::factory()->create(['eventType'=>$type,'published'=>true,'result_notifications_enabled'=>false]);
        $draw=Draw::factory()->create(['event_id'=>$event->id,'published'=>true,'oop_published'=>true,'locked'=>false]);
        $draw->settings()->create(['workflow'=>'round_robin_playoffs','preset_key'=>InterproRoundRobinFormat::PRESET,
            'playoff_config'=>InterproRoundRobinFormat::configuration(),'score_format'=>TennisScoreFormat::BEST_OF_3_FULL_START_2,
            'playoff_score_format'=>TennisScoreFormat::BEST_OF_3_FULL,'num_sets'=>3]);
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $admin=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);$this->actingAs($admin);
        return $draw;
    }
    private function fixture(Draw $draw,array $sets=[]): Fixture
    {
        $regs=Registration::factory()->count(2)->create();
        $fixture=Fixture::factory()->create(['draw_id'=>$draw->id,'stage'=>'RR','match_status'=>$sets ? 1 : 0,
            'registration1_id'=>$regs[0]->id,'registration2_id'=>$regs[1]->id,'winner_registration'=>$sets ? $regs[0]->id : null]);
        foreach($sets as $index=>$set) $fixture->fixtureResults()->create(['set_nr'=>$index+1,'registration1_score'=>$set[0],'registration2_score'=>$set[1],
            'winner_registration'=>$set[0]>$set[1] ? $regs[0]->id : $regs[1]->id,'loser_registration'=>$set[0]>$set[1] ? $regs[1]->id : $regs[0]->id]);
        return $fixture;
    }
    private function review(Draw $draw,array $points=[]): array {return app(InterproScoringCorrectionService::class)->review($draw->fresh(),$points);}
    private function apply(Draw $draw,array $review,array $points=[],bool $ack=false)
    {
        return $this->postJson(route('draw.interpro-scoring-correction.store',$draw),['revision'=>$review['revision'],'third_set_points'=>$points,'acknowledge_winner_change'=>$ack]);
    }
    public function test_reviewed_rules_preserve_valid_results_walkovers_schedules_publication_and_general_freeze(): void
    {
        $draw=$this->context();$fixture=$this->fixture($draw,[[6,2],[6,3]]);
        $wo=$this->fixture($draw);$wo->update(['match_status'=>3,'winner_registration'=>$wo->registration1_id]);
        $venue=Venue::forceCreate(['name'=>'Fictional scoring courts']);
        OrderOfPlay::create(['draw_id'=>$draw->id,'fixture_id'=>$fixture->id,'venue_id'=>$venue->id,'time'=>'2026-11-27 13:00','court'=>'1']);
        DB::table('published_schedule_assignments')->insert(['event_id'=>$draw->event_id,'draw_id'=>$draw->id,'fixture_kind'=>'individual','fixture_id'=>$fixture->id,
            'venue_id'=>$venue->id,'scheduled_at'=>'2026-11-27 13:00','court'=>'1','published_at'=>now()]);
        $snapshot=fn()=>[DB::table('fixture_results')->get()->toJson(),DB::table('fixtures')->get()->toJson(),DB::table('order_of_plays')->get()->toJson(),DB::table('published_schedule_assignments')->get()->toJson()];
        $before=$snapshot();$review=$this->review($draw);
        $this->assertSame(2,$review['compatible']);$this->assertSame([],$review['incompatible']);
        $this->get(route('draw.interpro-scoring-correction.show',$draw))->assertOk()->assertSee('Apply reviewed rules to both stages');
        $this->postJson(route('backend.draw.update-settings',$draw),['score_format'=>$review['format']])->assertForbidden();
        $this->apply($draw,$review)->assertOk();$this->assertSame($before,$snapshot());
        $this->assertSame($review['format'],$draw->settings()->first()->score_format);
        $this->assertSame($review['format'],$draw->settings()->first()->playoff_score_format);
        $this->assertTrue((bool)$draw->fresh()->published);$this->assertTrue((bool)$draw->fresh()->oop_published);
        $this->apply($draw,$this->review($draw))->assertOk();
        $this->assertSame(1,DB::table('draw_audit_logs')->where('action','interpro_scoring_rules_corrected')->count());
        $this->assertSame(0,$wo->fixtureResults()->count());
    }
    public function test_incompatible_third_requires_actual_points_and_preserves_original_two_rows_and_notification_silence(): void
    {
        $draw=$this->context();$fixture=$this->fixture($draw,[[5,7],[6,3],[7,6]]);
        $original=$fixture->fixtureResults()->orderBy('set_nr')->get()->map->getAttributes()->all();
        $review=$this->review($draw);$this->assertCount(1,$review['incompatible']);
        $this->apply($draw,$review)->assertConflict();
        $this->assertSame($original,$fixture->fixtureResults()->orderBy('set_nr')->get()->map->getAttributes()->all());
        $bad=[$fixture->id=>[11,8]];$this->apply($draw,$this->review($draw,$bad),$bad)->assertConflict();
        $points=[$fixture->id=>[12,10]];$review=$this->review($draw,$points);
        $this->assertSame([],$review['incompatible']);
        $notice=\App\Models\MatchResultNotification::create(['event_id'=>$draw->event_id,'fixture_type'=>'individual','fixture_id'=>$fixture->id,
            'revision'=>1,'snapshot_hash'=>str_repeat('a',64),'snapshot'=>['sets'=>[[5,7],[6,3],[7,6]]],'recipient'=>'fictional@example.test','status'=>'pending']);
        $this->apply($draw,$review,$points)->assertOk();
        $rows=$fixture->fixtureResults()->orderBy('set_nr')->get()->map->getAttributes()->all();
        $this->assertSame(array_slice($original,0,2),array_slice($rows,0,2));
        $this->assertSame($original[2]['id'],$rows[2]['id']);$this->assertSame(12,(int)$rows[2]['registration1_score']);
        $this->assertSame(10,(int)$rows[2]['registration2_score']);
        $this->assertSame(1,DB::table('draw_audit_logs')->where('action','interpro_deciding_tiebreak_corrected')->count());
        $this->assertDatabaseCount('match_result_notifications',1);$this->assertNotNull($notice->fresh()->invalidated_at);
        Queue::assertNothingPushed();Mail::assertNothingSent();
    }
    public function test_winner_changes_need_acknowledgment_and_stale_foreign_confirmed_and_malformed_requests_reject(): void
    {
        $draw=$this->context();$fixture=$this->fixture($draw,[[6,2],[3,6],[7,6]]);
        $actor=auth()->user();
        $points=[$fixture->id=>[10,12]];$review=$this->review($draw,$points);
        $this->apply($draw,$review,$points)->assertUnprocessable();
        $this->assertSame((int)$fixture->registration1_id,(int)$fixture->fresh()->winner_registration);
        $this->apply($draw,$review,$points,true)->assertOk();
        $this->assertSame((int)$fixture->registration2_id,(int)$fixture->fresh()->winner_registration);
        $stale=$this->review($draw);$draw->settings()->update(['num_sets'=>1]);$this->apply($draw,$stale)->assertConflict();
        $foreign=$this->fixture($this->context(),[[6,2],[3,6],[7,6]]);
        $this->actingAs($actor);
        $this->get(route('draw.interpro-scoring-correction.show',$draw).'?'.http_build_query(['third_set_points'=>[$foreign->id=>[12,10]]]))->assertUnprocessable();
        $config=$draw->settings()->first()->playoff_config;$config[0]['trials_confirmed']=true;$draw->settings()->update(['playoff_config'=>$config]);
        $this->get(route('draw.interpro-scoring-correction.show',$draw))->assertConflict();
        $this->actingAs(User::factory()->create());$this->get(route('draw.interpro-scoring-correction.show',$draw))->assertForbidden();
    }
    public function test_new_format_score_entry_accepts_extended_points_and_rejects_impossible_and_extra_deciders_without_writes(): void
    {
        $draw=$this->context();$draw->settings()->update(['score_format'=>TennisScoreFormat::BEST_OF_3_START_2_MATCH_TIEBREAK,'playoff_score_format'=>TennisScoreFormat::BEST_OF_3_START_2_MATCH_TIEBREAK]);
        $fixture=$this->fixture($draw);
        $payload=fn($sets)=>['score_revision'=>app(IndividualScoreRevision::class)->forFixture($fixture->fresh()),'sets'=>$sets];
        $url=route('api.draws.fixtures.score.store',[$draw,$fixture]);
        foreach ([['6-2','3-6','10-9'],['6-2','3-6','11-8'],['6-2','3-6','7-6'],['6-2','6-3','12-10']] as $sets) {
            $this->postJson($url,$payload($sets))->assertUnprocessable();$this->assertSame(0,$fixture->fixtureResults()->count());
        }
        $this->postJson($url,$payload(['6-2','3-6','999-997']))->assertOk();
        $this->assertSame(999,(int)$fixture->fixtureResults()->where('set_nr',3)->value('registration1_score'));
        $this->assertSame((int)$fixture->registration1_id,(int)$fixture->fresh()->winner_registration);
        $other=$this->fixture($draw);$this->postJson(route('api.draws.fixtures.score.store',[$draw,$other]),[
            'score_revision'=>app(IndividualScoreRevision::class)->forFixture($other->fresh()),'sets'=>['6-2','3-6','10-12']])->assertOk();
        $this->assertSame((int)$other->registration2_id,(int)$other->fresh()->winner_registration);
    }

    public function test_malformed_set_order_and_cross_draw_dependencies_fail_without_writes(): void
    {
        $draw=$this->context();$fixture=$this->fixture($draw,[[6,2],[3,6],[7,6]]);
        $rows=$fixture->fixtureResults()->orderBy('set_nr')->get();
        $rows[1]->update(['set_nr'=>3]);$rows[2]->update(['set_nr'=>5]);
        $snapshot=fn()=>[DB::table('draw_settings')->get()->toJson(),DB::table('fixtures')->get()->toJson(),DB::table('fixture_results')->get()->toJson(),DB::table('draw_audit_logs')->get()->toJson()];
        $before=$snapshot();
        $this->get(route('draw.interpro-scoring-correction.show',$draw).'?'.http_build_query(['third_set_points'=>[$fixture->id=>[12,10]]]))->assertUnprocessable();
        $this->assertSame($before,$snapshot());
        $rows[1]->update(['set_nr'=>2]);$rows[2]->update(['set_nr'=>3]);
        $foreign=Fixture::factory()->create();
        $fixture->update(['parent_fixture_id'=>$foreign->id]);$before=$snapshot();
        $this->get(route('draw.interpro-scoring-correction.show',$draw))->assertConflict();$this->assertSame($before,$snapshot());
        $fixture->update(['parent_fixture_id'=>null]);$foreign->update(['parent_fixture_id'=>$fixture->id]);$before=$snapshot();
        $this->get(route('draw.interpro-scoring-correction.show',$draw))->assertConflict();$this->assertSame($before,$snapshot());
    }
}
