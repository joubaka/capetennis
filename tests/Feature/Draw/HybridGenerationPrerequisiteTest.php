<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Player, Registration, User};
use App\Services\Draw\HybridRoundRobinService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HybridGenerationPrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    private function selectedHybrid(): Draw
    {
        config(['capetennis_engine.mode'=>'hybrid', 'capetennis_engine.auto_fallback'=>true,
            'capetennis_engine.mismatch_rollback_threshold'=>0]);
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType'=>6]);
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$actor->id]);
        $category = CategoryEvent::factory()->create(['event_id'=>$event->id]);
        $draw = Draw::factory()->create(['event_id'=>$event->id,'category_event_id'=>$category->id,'engine_mode'=>'hybrid']);
        $this->actingAs($actor)->post(route('draw.setup.store',$draw),[
            'workflow'=>'round_robin_round_robin','groups'=>2,'second_stage_format'=>HybridRoundRobinService::FORMAT,
        ])->assertRedirect();
        $draw->fresh()->settings->update(['score_format'=>'timed','num_sets'=>1,'match_duration_minutes'=>15,'changeover_minutes'=>0]);
        foreach ($draw->groups()->orderBy('name')->get() as $group) {
            foreach (range(1,$group->name === 'A' ? 4 : 5) as $seed) {
                $registration = Registration::factory()->create();
                $registration->players()->attach(Player::factory()->create(['name'=>'Example','surname'=>$group->name.$seed])->id);
                CategoryEventRegistration::factory()->create(['category_event_id'=>$category->id,
                    'registration_id'=>$registration->id,'payment_status_id'=>1,'status'=>'active']);
                $group->registrations()->attach($registration->id,['seed'=>$seed]);
            }
        }
        return $draw->fresh();
    }

    public function test_missing_fixture_schema_fails_before_any_competition_write_through_the_real_hybrid_route(): void
    {
        $draw = $this->selectedHybrid();
        Schema::table('fixtures',fn ($table) => $table->dropColumn('loser_feeder_slot'));
        $before = [$draw->groups()->orderBy('id')->get()->toJson(),
            DB::table('draw_group_registrations')->orderBy('id')->get()->toJson(),$draw->settings->getAttributes()];
        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(?:insert|update|delete).*\b(?:fixtures|draw_groups|draw_group_registrations|draw_settings)\b/i',$query->sql)) $writes[] = $query->sql;
        });
        $this->postJson(route('backend.draw.regenerate-rr',$draw), ['rebuild_revision' => app(\App\Services\Draw\RoundRobinRebuildService::class)->revision($draw->fresh())] + (['revision' => app(\App\Services\Draw\GroupAssignmentService::class)->revision(($draw)->fresh())]))
            ->assertConflict()->assertJsonPath('message',
                'Fixture progression schema is incomplete. Apply the reviewed fixture migrations, including 2026_11_14_100000_add_loser_feeder_slot_to_fixtures.php, before preparing or converting this hybrid draw.');
        $this->assertSame([],$writes,'Rollback cannot protect nontransactional legacy group tables: fail before the first write.');
        $this->assertSame($before,[$draw->groups()->orderBy('id')->get()->toJson(),
            DB::table('draw_group_registrations')->orderBy('id')->get()->toJson(),$draw->fresh()->settings->getAttributes()]);
        $this->assertDatabaseCount('fixtures',0);
        $this->assertSame(0,$draw->groups()->where('round_robin_phase',2)->count());
    }

    public function test_hybrid_generation_route_prepares_all_phases_once_with_unresolved_players(): void
    {
        $draw = $this->selectedHybrid();
        $this->postJson(route('backend.draw.regenerate-rr',$draw), ['rebuild_revision' => app(\App\Services\Draw\RoundRobinRebuildService::class)->revision($draw->fresh())] + (['revision' => app(\App\Services\Draw\GroupAssignmentService::class)->revision(($draw)->fresh())]))->assertOk()->assertJsonPath('fixture_count',26);
        $draw = $draw->fresh();
        $this->assertSame(16,$draw->drawFixtures()->where('stage','RR')->whereHas('drawGroup',fn ($query)=>$query->where('round_robin_phase',1))->count());
        $this->assertSame(6,$draw->drawFixtures()->where('stage','RR')->whereHas('drawGroup',fn ($query)=>$query->where('round_robin_phase',2))->count());
        $this->assertSame(4,$draw->drawFixtures()->where('stage','MAIN')->count());
        $later = $draw->drawFixtures()->where(fn ($query)=>$query->where('stage','MAIN')
            ->orWhereHas('drawGroup',fn ($group)=>$group->where('round_robin_phase',2)))->get();
        $this->assertTrue($later->every(fn ($fixture)=>!$fixture->registration1_id && !$fixture->registration2_id && !$fixture->winner_registration));
        $state = $draw->settings->playoff_config['_second_round_robin'];
        $this->assertSame('prepared',$state['state']);
        $this->assertCount(10,$state['prepared_fixture_ids']);
        $bracket = $this->get(route('backend.draw.main-bracket',$draw))->assertOk();
        $this->assertCount(1,$bracket->viewData('svgData')['brackets']);
        $ids = $draw->drawFixtures()->orderBy('id')->pluck('id')->all();
        $groupsBefore = $draw->groups()->orderBy('id')->get()->toJson();
        $readWrites = [];
        DB::listen(function (QueryExecuted $query) use (&$readWrites): void {
            if (preg_match('/^\s*(?:insert|update|delete).*\b(?:fixtures|draw_groups|draw_group_registrations|draw_settings|registrations|player_registrations)\b/i',$query->sql)) $readWrites[] = $query->sql;
        });
        $hub = $this->getJson(route('api.draws.hub',$draw))->assertOk()->assertJsonCount(3,'groups')
            ->assertJsonPath('can_assign',false)->assertJsonPath('can_generate',false);
        $this->assertSame([],$readWrites);
        $this->assertSame(['id','display_name','seed'],array_keys($hub->json('groups.0.registrations.0')));
        $this->assertStringContainsString('0 of 16 first-stage results recorded.',$hub->json('progression_html'));
        $lower = collect($hub->json('groups'))->firstWhere('round_robin_phase',2);
        $this->assertSame([],$lower['registrations']);
        $lowerFixtures = $hub->json('rrFixtures.'.$lower['id']);
        $this->assertCount(6,$lowerFixtures);
        foreach ($lowerFixtures as $fixture) {
            $this->assertNull($fixture['r1_id']); $this->assertNull($fixture['r2_id']);
            $this->assertMatchesRegularExpression('/^Group [AB] #[345]$/',$fixture['name1']);
            $this->assertMatchesRegularExpression('/^Group [AB] #[345]$/',$fixture['name2']);
            $this->assertGreaterThanOrEqual(17,$fixture['match_nr']);
        }
        $this->assertSame($ids,$draw->drawFixtures()->orderBy('id')->pluck('id')->all());
        $this->assertSame($groupsBefore,$draw->groups()->orderBy('id')->get()->toJson());
        $this->postJson(route('backend.roundrobin.score.store',$state['lower_fixture_ids'][0]), ['score_revision' => app(\App\Services\Draw\IndividualScoreRevision::class)->forFixture((\App\Models\Fixture::findOrFail($state['lower_fixture_ids'][0]))->fresh())] + (['sets'=>['6-0']]))->assertConflict();
        $this->postJson(route('backend.roundrobin.score.store',$state['semi_fixture_ids'][0]), ['score_revision' => app(\App\Services\Draw\IndividualScoreRevision::class)->forFixture((\App\Models\Fixture::findOrFail($state['semi_fixture_ids'][0]))->fresh())] + (['sets'=>['6-0']]))->assertConflict();
        $actor = auth()->user();
        $outsider = User::factory()->create()->assignRole('admin');
        $this->actingAs($outsider)->getJson(route('api.draws.hub',$draw))->assertForbidden();
        $this->actingAs($actor);
        $this->postJson(route('backend.draw.regenerate-rr',$draw), ['rebuild_revision' => app(\App\Services\Draw\RoundRobinRebuildService::class)->revision($draw->fresh())] + (['revision' => app(\App\Services\Draw\GroupAssignmentService::class)->revision(($draw)->fresh())]))->assertConflict()
            ->assertJsonPath('message','Both round-robin stages must be preserved once second-stage matches are prepared.');
        $this->assertSame($ids,$draw->drawFixtures()->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('fixture_results',0);
        $this->assertDatabaseCount('order_of_plays',0);
        $this->assertDatabaseCount('schedules',0);
        $this->assertDatabaseCount('published_schedule_assignments',0);
    }
}
