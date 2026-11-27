<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\TennisScoreFormat;
use App\Models\{Draw, Event, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\{IndividualScoreRevision, InterproRoundRobinFormat};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Http, Mail, Notification, Queue};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StartAtTwoScoringTest extends TestCase
{
    use RefreshDatabase;

    private function context(): Draw
    {
        Mail::fake(); Notification::fake(); Queue::fake(); Bus::fake(); Http::preventStrayRequests();
        $event=Event::factory()->create(['eventType'=>6,'result_notifications_enabled'=>false]);
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $admin=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$admin->id]);
        $this->actingAs($admin);
        $draw=Draw::factory()->create(['event_id'=>$event->id,'published'=>true,'locked'=>false]);
        $draw->settings()->create(['workflow'=>'round_robin_playoffs','preset_key'=>InterproRoundRobinFormat::PRESET,
            'playoff_config'=>InterproRoundRobinFormat::configuration(),'score_format'=>TennisScoreFormat::BEST_OF_3_FULL_START_2,'num_sets'=>3]);
        return $draw;
    }

    private function fixture(Draw $draw): Fixture
    {
        $group=$draw->groups()->firstOrCreate(['name'=>'A']);
        $registrations=Registration::factory()->count(2)->create();
        foreach($registrations as $index=>$registration) {
            $registration->players()->attach(Player::factory()->create()->id);
            $group->registrations()->attach($registration->id,['seed'=>$index+1]);
        }
        return Fixture::factory()->create(['draw_id'=>$draw->id,'draw_group_id'=>$group->id,'stage'=>'RR',
            'registration1_id'=>$registrations[0]->id,'registration2_id'=>$registrations[1]->id,'match_status'=>0,'winner_registration'=>null]);
    }

    private function payload(Fixture $fixture,array $data): array
    {
        $current=Fixture::query()->findOrFail($fixture->id);
        $current->setRelation('draw',Draw::query()->findOrFail($current->draw_id));
        return ['score_revision'=>app(IndividualScoreRevision::class)->forFixture($current)]+$data;
    }

    public function test_invalid_played_scores_reject_on_both_routes_without_changing_records_or_bookings(): void
    {
        $draw=$this->context(); $fixture=$this->fixture($draw);
        $venue=Venue::forceCreate(['name'=>'Fictional two-all courts']);
        OrderOfPlay::create(['draw_id'=>$draw->id,'fixture_id'=>$fixture->id,'venue_id'=>$venue->id,'court'=>'1','time'=>'2026-11-27 13:00','duration_minutes'=>45]);
        $snapshot=fn()=>[DB::table('fixtures')->get()->toJson(),DB::table('fixture_results')->get()->toJson(),DB::table('order_of_plays')->get()->toJson(),DB::table('draw_settings')->get()->toJson(),DB::table('draw_audit_logs')->get()->toJson()];
        $before=$snapshot();
        foreach([route('api.draws.fixtures.score.store',[$draw,$fixture]),route('rr.fixture.saveScore',$fixture)] as $url) {
            foreach([['3-2','3-2'],['6-2'],['6-2','2-6'],['2-2'],['6-0','6-2'],['6-2','6-3','7-6']] as $sets) {
                $this->postJson($url,$this->payload($fixture,['sets'=>$sets]))->assertUnprocessable();
                $this->assertSame($before,$snapshot());
            }
        }
    }

    public function test_two_and_three_set_results_retirements_and_walkovers_use_distinct_outcomes(): void
    {
        $draw=$this->context();
        foreach([['6-2','6-3'],['6-2','3-6','7-6']] as $sets) {
            $fixture=$this->fixture($draw);
            $this->postJson(route('api.draws.fixtures.score.store',[$draw,$fixture]),$this->payload($fixture,['sets'=>$sets]))->assertOk();
            $this->assertSame(count($sets),$fixture->fixtureResults()->count());
            $this->assertSame((int)$fixture->registration1_id,(int)$fixture->fresh()->winner_registration);
        }
        $retired=$this->fixture($draw);
        $url=route('api.draws.fixtures.score.store',[$draw,$retired]);
        $this->postJson($url,$this->payload($retired,['sets'=>['0-0'],'retired_registration_id'=>$retired->registration2_id]))->assertUnprocessable();
        $this->assertSame(0,$retired->fixtureResults()->count());
        $this->postJson($url,$this->payload($retired,['sets'=>['2-2'],'retired_registration_id'=>$retired->registration2_id]))->assertOk();
        $this->assertTrue($retired->fresh()->isRetired());
        $this->assertFalse($retired->fresh()->isWalkover());
        $walkover=$this->fixture($draw);
        $this->postJson(route('api.draws.fixtures.score.store',[$draw,$walkover]),$this->payload($walkover,['walkover_winner_id'=>$walkover->registration1_id]))->assertOk();
        $this->assertTrue($walkover->fresh()->isWalkover());
        $this->assertSame(0,$walkover->fixtureResults()->count());
        $this->assertSame(4,$draw->drawFixtures()->count());
        $this->assertSame(6,DB::table('fixture_results')->count());
    }

    public function test_new_format_keeps_authorization_draw_isolation_and_scored_rule_freeze(): void
    {
        $draw=$this->context(); $fixture=$this->fixture($draw); $admin=auth()->user();
        $url=route('api.draws.fixtures.score.store',[$draw,$fixture]);
        $data=$this->payload($fixture,['sets'=>['6-2','6-3']]);
        $this->actingAs(User::factory()->create())->postJson($url,$data)->assertForbidden();
        $this->actingAs($admin);
        $foreign=Draw::factory()->create(['event_id'=>Event::factory()->create()->id]);
        $this->postJson(route('api.draws.fixtures.score.store',[$foreign,$fixture]),$data)->assertForbidden();
        $this->assertSame(0,DB::table('fixture_results')->count());
        $draw->settings()->update(['score_format'=>null,'num_sets'=>null]);
        $this->postJson(route('backend.draw.update-settings',$draw),['score_format'=>TennisScoreFormat::BEST_OF_3_FULL_START_2])->assertOk();
        $this->assertSame(TennisScoreFormat::BEST_OF_3_FULL_START_2,$draw->settings()->first()->score_format);
        $this->postJson($url,$this->payload($fixture,['sets'=>['6-2','6-3']]))->assertOk();
        $this->postJson(route('backend.draw.update-settings',$draw),['score_format'=>TennisScoreFormat::BEST_OF_3_FULL])->assertForbidden();
        $this->assertSame(2,$fixture->fixtureResults()->count());
    }

    public function test_round_robin_and_playoff_formats_are_saved_and_validated_separately(): void
    {
        $draw=$this->context();
        $draw->settings()->update(['score_format'=>TennisScoreFormat::ONE_FULL_SET,'num_sets'=>1,'playoff_score_format'=>null,'preset_key'=>null]);
        $rr=$this->fixture($draw); $playoff=$this->fixture($draw); $playoff->update(['stage'=>'MAIN']);
        $oldRevision=app(IndividualScoreRevision::class)->forFixture($playoff->fresh());
        $this->postJson(route('backend.draw.update-settings',$draw),['playoff_score_format'=>TennisScoreFormat::BEST_OF_3_FULL_START_2])->assertOk();
        $settings=$draw->settings()->first();
        $this->assertSame(TennisScoreFormat::ONE_FULL_SET,$settings->score_format);
        $this->assertSame(1,(int)$settings->num_sets);
        $this->assertSame(TennisScoreFormat::BEST_OF_3_FULL_START_2,$settings->scoreFormatForStage('MAIN'));
        $this->postJson(route('api.draws.fixtures.score.store',[$draw,$playoff]),['score_revision'=>$oldRevision,'sets'=>['6-2','6-3']])->assertConflict();
        $this->postJson(route('api.draws.fixtures.score.store',[$draw,$playoff]),$this->payload($playoff,['sets'=>['6-2']]))->assertUnprocessable();
        $this->assertSame(0,$playoff->fixtureResults()->count());
        $this->postJson(route('api.draws.fixtures.score.store',[$draw,$rr]),$this->payload($rr,['sets'=>['6-2']]))->assertOk();
        $this->postJson(route('api.draws.fixtures.score.store',[$draw,$playoff]),$this->payload($playoff,['sets'=>['6-2','6-3']]))->assertOk();
        $this->assertSame(1,$rr->fixtureResults()->count());
        $this->assertSame(2,$playoff->fixtureResults()->count());
        $this->assertSame((int)$playoff->registration1_id,(int)$playoff->fresh()->winner_registration);
        $this->postJson(route('backend.draw.update-settings',$draw),['playoff_score_format'=>TennisScoreFormat::ONE_FULL_SET])->assertForbidden();
        // Timing reads the actual fixture stage, including explicit day-batch overrides.
        $draw->settings()->update(['playoff_score_format'=>TennisScoreFormat::TIMED,'match_duration_minutes'=>25,'changeover_minutes'=>3]);
        $timing=app(\App\Services\Scheduling\MatchTimingService::class);
        $this->assertSame(['duration_minutes'=>60,'court_gap_minutes'=>5],$timing->forFixture($rr->fresh()));
        $this->assertSame(['duration_minutes'=>25,'court_gap_minutes'=>3],$timing->forFixture($playoff->fresh()));
        $this->assertSame(['duration_minutes'=>40,'court_gap_minutes'=>7],$timing->forFixture($playoff->fresh(),40,7,true));
    }

    public function test_separate_choices_prompt_without_blocking_legacy_visibility_and_render_match_contexts(): void
    {
        $draw=$this->context(); $this->fixture($draw);
        $draw->settings()->update(['score_format'=>TennisScoreFormat::ONE_FULL_SET,'playoff_score_format'=>null]);
        $workflow=app(\App\Services\Draw\DrawWorkflow::class)->forDraw($draw->fresh());
        $this->assertFalse($workflow['steps']['setup']['complete']);
        $this->get(route('backend.draw.roundrobin.show',$draw))->assertOk()->assertSee('Playoff scoring')->assertSee('Choose playoff scoring')->assertSee('Before play starts');
        $this->postJson(route('backend.draw.update-settings',$draw),['playoff_score_format'=>TennisScoreFormat::BEST_OF_3_FULL_START_2])->assertOk();
        $this->get(route('backend.draw.roundrobin.show',$draw))->assertOk()->assertSee('RR_SCORE_CONTEXTS',false)->assertSee('full_start_2',false);
        $sections=collect(app(\App\Domain\Draws\Services\DrawRulesService::class)->sections($draw->fresh()));
        $this->assertStringContainsString('One full set',$sections->firstWhere('key','match_format')['rules']);
        $this->assertStringContainsString('2–2',$sections->firstWhere('key','playoff_match_format')['rules']);
        $playoff=$this->fixture($draw); $playoff->update(['stage'=>'MAIN']);
        $this->get(route('backend.roundrobin.admin.scores',$draw))->assertOk()->assertSee('full_start_2',false)->assertSee('data-guidance=',false);
    }
}
