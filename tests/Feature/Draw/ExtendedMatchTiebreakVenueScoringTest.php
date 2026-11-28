<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\TennisScoreFormat;
use App\Models\{Draw,Event,Fixture,OrderOfPlay,Registration,User,Venue};
use App\Services\Draw\IndividualScoreRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus,DB,Http,Mail,Notification,Queue};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExtendedMatchTiebreakVenueScoringTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        Mail::fake(); Notification::fake(); Queue::fake(); Bus::fake(); Http::preventStrayRequests();
        $event=Event::factory()->create(['eventType'=>6,'published'=>true,'result_notifications_enabled'=>false]);
        $draw=Draw::factory()->create(['event_id'=>$event->id,'published'=>true,'locked'=>false]);
        $draw->settings()->create(['workflow'=>'round_robin_playoffs','score_format'=>TennisScoreFormat::BEST_OF_3_FULL_START_2,
            'playoff_score_format'=>TennisScoreFormat::BEST_OF_3_MATCH_TIEBREAK,'num_sets'=>3]);
        $venue=Venue::forceCreate(['name'=>'Fictional extended tiebreak courts']);
        $event->venues()->attach($venue->id,['num_courts'=>2]);
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $user=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$user->id]);
        $this->actingAs($user)->withSession(['venue_scoring.operator'=>'Extended tiebreak test']);
        return [$event,$draw,$venue];
    }

    private function fixture(Draw $draw,Venue $venue,string $stage): Fixture
    {
        $regs=Registration::factory()->count(2)->create();
        $fixture=Fixture::factory()->create(['draw_id'=>$draw->id,'stage'=>$stage,'match_status'=>0,'winner_registration'=>null,
            'registration1_id'=>$regs[0]->id,'registration2_id'=>$regs[1]->id]);
        OrderOfPlay::create(['draw_id'=>$draw->id,'fixture_id'=>$fixture->id,'venue_id'=>$venue->id,'court'=>'1','time'=>'2026-11-28 08:00']);
        return $fixture;
    }

    public function test_venue_controller_saves_and_reloads_extended_generic_playoff_tiebreaks_in_both_orientations(): void
    {
        [$event,$draw,$venue]=$this->context();
        foreach (['MAIN'=>[12,10],'CONS'=>[18,20]] as $stage=>$points) {
            $fixture=$this->fixture($draw,$venue,$stage);
            $this->postJson(route('backend.roundrobin.score.store',$fixture),[
                'score_revision'=>app(IndividualScoreRevision::class)->forFixture($fixture->fresh()),
                'sets'=>['6-0','3-6',implode('-',$points)],
            ])->assertOk();
            $third=$fixture->fixtureResults()->where('set_nr',3)->firstOrFail();
            $this->assertSame($points,[(int)$third->registration1_score,(int)$third->registration2_score]);
            $this->assertSame((int)($points[0]>$points[1] ? $fixture->registration1_id : $fixture->registration2_id),(int)$fixture->fresh()->winner_registration);
            $page=$this->get(route('frontend.scoring.workspace',['event'=>$event,'venue'=>$venue->id,'schedule_source'=>'working']))->assertOk();
            $page->assertSee('data-score-format="best_of_3_match_tiebreak"',false)->assertSee('match_tiebreak',false);
            $page->assertSee(implode('–',$points));
            $this->assertStringContainsString(json_encode([[6,0],[3,6],$points]),$page->getContent());
        }
    }

    public function test_venue_rejects_unfinished_or_nonterminal_tiebreak_and_does_not_change_full_set_rules(): void
    {
        [, $draw,$venue]=$this->context();$fixture=$this->fixture($draw,$venue,'CONS');
        foreach (['10-9','11-8','20-17'] as $third) {
            $this->postJson(route('backend.roundrobin.score.store',$fixture),[
                'score_revision'=>app(IndividualScoreRevision::class)->forFixture($fixture->fresh()),'sets'=>['6-0','3-6',$third],
            ])->assertUnprocessable();
            $this->assertSame(0,$fixture->fixtureResults()->count());
        }
        $draw->settings()->update(['playoff_score_format'=>TennisScoreFormat::BEST_OF_3_FULL]);
        $this->postJson(route('backend.roundrobin.score.store',$fixture),[
            'score_revision'=>app(IndividualScoreRevision::class)->forFixture($fixture->fresh()),'sets'=>['6-0','3-6','12-10'],
        ])->assertUnprocessable();
        $this->assertSame(0,$fixture->fixtureResults()->count());
    }
}
