<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\{RoundRobinGenerationService, StandingsService, TennisScoreFormat};
use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, Player, Registration, User};
use App\Services\Draw\{DrawFinalPlacementService, GroupAssignmentService, HybridRoundRobinService, TwoStageRoundRobinService};
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HybridRoundRobinTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_production_progression_column_blocks_before_mutation_and_migration_repairs_it_idempotently(): void
    {
        [$draw, $actor] = $this->scenario();
        $service = app(HybridRoundRobinService::class);
        $plan = $service->review($draw, $draw->event_id);
        $beforeResults = DB::table('fixture_results')->orderBy('id')->get()->toJson();
        $beforeFixtures = $draw->drawFixtures()->orderBy('id')->pluck('id')->all();
        $beforeGroups = $draw->groups()->orderBy('id')->pluck('id')->all();
        \Illuminate\Support\Facades\Schema::table('fixtures', fn ($table) => $table->dropColumn('loser_feeder_slot'));
        $this->artisan('draw:convert-hybrid', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id])->assertFailed();
        $this->artisan('draw:convert-hybrid', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id, '--apply' => true, '--snapshot' => $plan['snapshot']])->assertFailed();
        $this->assertSame($beforeFixtures, $draw->drawFixtures()->orderBy('id')->pluck('id')->all());
        $this->assertSame($beforeGroups, $draw->groups()->orderBy('id')->pluck('id')->all());
        $this->assertSame($beforeResults, DB::table('fixture_results')->orderBy('id')->get()->toJson());
        $migration = require database_path('migrations/2026_11_14_100000_add_loser_feeder_slot_to_fixtures.php');
        $migration->up();
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('fixtures', 'loser_feeder_slot'));
        $plan = $service->review($draw->fresh(), $draw->event_id);
        $this->assertCount(10, $service->apply($draw, $draw->event_id, $plan['snapshot'], $actor)['created_fixture_ids']);
        $this->assertSame([1, 2], $draw->drawFixtures()->where('stage', 'MAIN')->where('round', 1)->orderBy('id')->pluck('loser_feeder_slot')->all());
        $this->assertSame($beforeResults, DB::table('fixture_results')->orderBy('id')->get()->toJson());
    }

    private function scenario(bool $timed = true): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 6, 'published' => true]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->actingAs($actor);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => false, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_round_robin', 'boxes' => 2, 'num_sets' => 1,
            'score_format' => $timed ? TennisScoreFormat::TIMED : TennisScoreFormat::ONE_FULL_SET,
            'match_duration_minutes' => 15, 'changeover_minutes' => 0]);
        $players = [];
        foreach (['A' => 4, 'B' => 5] as $name => $size) {
            $group = $draw->groups()->create(['name' => $name]);
            foreach (range(1, $size) as $seed) {
                $registration = Registration::factory()->create();
                $registration->players()->attach(Player::factory()->create(['name' => $name.$seed, 'surname' => 'Example'])->id);
                CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 1]);
                $draw->registrations()->attach($registration->id);
                $group->registrations()->attach($registration->id, ['seed' => $seed]);
                $players[$name.$seed] = $registration->id;
            }
        }
        app(RoundRobinGenerationService::class)->generate($draw);
        $firstIds = $draw->groups()->where('round_robin_phase', 1)->pluck('id');
        foreach ($draw->drawFixtures()->whereIn('draw_group_id', $firstIds)->get() as $fixture) {
            $drawn = $timed && in_array($fixture->registration1_id, [$players['B4'], $players['B5']]) && in_array($fixture->registration2_id, [$players['B4'], $players['B5']]);
            $losingId = max($fixture->registration1_id, $fixture->registration2_id);
            $losingScore = $timed && $losingId === $players['B4'] ? 2 : 0;
            $sets = $drawn ? [[14, 14]] : ($fixture->registration1_id < $fixture->registration2_id ? [[6, $losingScore]] : [[$losingScore, 6]]);
            app(DrawService::class)->saveScore($fixture, $sets);
        }
        $service = app(TwoStageRoundRobinService::class);
        $review = $service->review($draw->fresh());
        $service->progress($draw->fresh(), ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(), 'standings_snapshot' => $review['snapshot']]);
        $draw->update(['published' => true, 'oop_published' => true]);
        return [$draw->fresh(), $actor, $players];
    }

    public function test_timed_hybrid_preserves_results_and_carries_only_mutual_lower_matches(): void
    {
        [$draw, $actor, $players] = $this->scenario();
        $service = app(HybridRoundRobinService::class);
        $before = DB::table('fixture_results')->orderBy('id')->get()->toJson();
        $plan = $service->review($draw, $draw->event_id);
        $this->assertCount(16, $plan['preserved_fixture_ids']);
        $this->assertCount(4, $plan['replaced_fixture_ids']);
        $this->assertCount(4, $plan['carried_fixture_ids']);
        $this->assertSame(6, $plan['new_round_robin_matches']);
        $result = $service->apply($draw, $draw->event_id, $plan['snapshot'], $actor);
        $draw = $draw->fresh();
        $this->assertCount(10, $result['created_fixture_ids']);
        $this->assertSame(26, $draw->drawFixtures()->count());
        $this->assertSame($before, DB::table('fixture_results')->orderBy('id')->get()->toJson());
        $this->assertTrue((bool) $draw->published);
        $this->assertTrue((bool) $draw->oop_published);
        $this->assertFalse(app(GroupAssignmentService::class)->fixturesNeedRegeneration($draw));
        $effective = HybridRoundRobinService::effectiveFixtures($draw);
        $this->assertCount(30, $effective);
        $this->assertCount(30, HybridRoundRobinService::effectiveFixtures($draw, $effective));
        $lower = $result['lower_group_id'];
        $rows = collect(app(StandingsService::class)->forDraw($draw)[$lower])->keyBy('reg_id');
        $this->assertSame(1, $rows[$players['B4']]['draws']);
        $this->assertSame(1, $rows[$players['B5']]['draws']);
        $this->assertSame(2, $rows[$players['B3']]['wins']);
        $this->assertSame(1, $rows[$players['A3']]['wins']);
        $hub = app(DrawService::class)->loadRoundRobinHub($draw);
        $this->assertCount(10, $hub['rrFixtures'][$lower]);
        $this->assertCount(4, collect($hub['rrFixtures'][$lower])->where('carried', true));
        $this->assertCount(26, $hub['oops']);
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('Top-four playoffs');
        $this->get('/roundrobin/'.$draw->id)->assertOk()->assertSee('Positions 5');
        $this->assertTrue($service->apply($draw, $draw->event_id, $plan['snapshot'], $actor)['already_applied']);
        $this->assertSame(26, $draw->drawFixtures()->count());
        $this->assertCount(9, app(DrawFinalPlacementService::class)->forDraw($draw));
        $semis = $draw->drawFixtures()->where('stage', 'MAIN')->where('round', 1)->orderBy('id')->get();
        $this->assertSame([[$players['A1'], $players['B2']], [$players['B1'], $players['A2']]], $semis->map(fn ($fixture) => [$fixture->registration1_id, $fixture->registration2_id])->all());
        $brackets = (new \App\Services\DynamicBracketEngine($draw))->build()['brackets'];
        $this->assertCount(1, $brackets);
        $this->assertCount(2, $brackets[0]['rounds'][1]);
        $this->assertEqualsCanonicalizing($semis->pluck('id')->all(), collect($brackets[0]['rounds'][1])->pluck('fx.id')->all());
        $this->assertSame($draw->drawFixtures()->where('stage', 'MAIN')->where('position', 1)->value('id'), $brackets[0]['rounds'][2][0]['fx']->id);
        $this->assertSame($draw->drawFixtures()->where('stage', 'MAIN')->where('position', 3)->value('id'), $brackets[0]['positionPlayoffs'][0]['fx']->id);
        $this->postJson(route('backend.roundrobin.score.store', $plan['carried_fixture_ids'][0]), ['sets' => ['7-0']])->assertConflict();
        foreach ($semis as $semi) $this->postJson(route('backend.roundrobin.score.store', $semi), ['sets' => ['10-5']])->assertOk();
        $final = $draw->drawFixtures()->where('stage', 'MAIN')->where('position', 1)->first();
        $bronze = $draw->drawFixtures()->where('stage', 'MAIN')->where('position', 3)->first();
        $this->assertEqualsCanonicalizing([$players['A1'], $players['B1']], [$final->registration1_id, $final->registration2_id]);
        $this->assertEqualsCanonicalizing([$players['B2'], $players['A2']], [$bronze->registration1_id, $bronze->registration2_id]);
        $this->postJson(route('backend.roundrobin.score.store', $final), ['sets' => ['10-5']])->assertOk();
        $this->postJson(route('backend.roundrobin.score.store', $bronze), ['sets' => ['10-5']])->assertOk();
        $placements = app(DrawFinalPlacementService::class)->forDraw($draw->fresh());
        $this->assertSame([1, 2, 3, 4], $placements->where('status', 'resolved')->pluck('position')->all());
        foreach ($draw->drawFixtures()->where('draw_group_id', $lower)->get() as $fixture) {
            $score = $fixture->registration1_id < $fixture->registration2_id ? '10-5' : '5-10';
            $this->postJson(route('backend.roundrobin.score.store', $fixture), ['sets' => [$score]])->assertOk();
        }
        $placements = app(DrawFinalPlacementService::class)->forDraw($draw->fresh());
        $this->assertSame(range(1, 9), $placements->pluck('position')->all());
        $this->assertSame(9, $placements->where('status', 'resolved')->pluck('registration_id')->unique()->count());
        $this->assertSame($players['A3'], $placements->firstWhere('position', 5)['registration_id']);
        $audit = \App\Models\DrawAuditLog::where('draw_id', $draw->id)->where('action', 'hybrid_round_robin_applied')->sole();
        $this->assertSame($actor->id, $audit->user_id);
        $this->assertCount(4, $audit->payload['replaced']['fixtures']);
        $this->assertCount(5, $audit->payload['replaced']['groups']);
        $draw->update(['published' => false]);
        $this->assertFalse((new \App\Services\Draw\DrawMutationPolicy($draw->fresh()))->canGenerateBrackets());
        $this->postJson(route('backend.draw.generate-main-bracket', $draw))->assertConflict();
    }

    public function test_standard_scores_convert_and_all_schedule_evidence_is_stale_protected(): void
    {
        [$draw, $actor, $players] = $this->scenario(false);
        $service = app(HybridRoundRobinService::class);
        $plan = $service->review($draw, $draw->event_id);
        $old = $plan['replaced_fixture_ids'][0];
        $venue = \App\Models\Venue::forceCreate(['name' => 'Review courts']);
        $booking = \App\Models\OrderOfPlay::create(['fixture_id' => $old, 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'time' => '2026-11-14 10:00:00', 'court' => 1]);
        $this->artisan('draw:convert-hybrid', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id, '--apply' => true, '--snapshot' => $plan['snapshot']])->assertFailed();
        $plan = $service->review($draw->fresh(), $draw->event_id);
        $result = $service->apply($draw, $draw->event_id, $plan['snapshot'], $actor);
        $this->assertDatabaseMissing('order_of_plays', ['id' => $booking->id]);
        $rows = collect(app(StandingsService::class)->forDraw($draw->fresh())[$result['lower_group_id']])->keyBy('reg_id');
        $this->assertSame(1, $rows[$players['A3']]['wins']);
        $this->assertSame(2, $rows[$players['B3']]['wins']);
        $this->assertSame(16, DB::table('fixture_results')->count());
    }

    public function test_conversion_rejects_wrong_event_unauthorized_actor_and_stale_state(): void
    {
        [$draw, $actor] = $this->scenario(false);
        $service = app(HybridRoundRobinService::class);
        $plan = $service->review($draw, $draw->event_id);
        $this->artisan('draw:convert-hybrid', ['draw' => $draw->id, '--event' => $draw->event_id + 1, '--actor' => $actor->id])->assertFailed();
        $stranger = User::factory()->create()->assignRole('admin');
        $this->artisan('draw:convert-hybrid', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $stranger->id, '--apply' => true, '--snapshot' => $plan['snapshot']])->assertFailed();
        foreach (['convenor', 'score-keeper'] as $role) {
            $restricted = User::factory()->create();
            DB::table('event_convenors')->insert(['event_id' => $draw->event_id, 'user_id' => $restricted->id, 'role' => $role]);
            $this->artisan('draw:convert-hybrid', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $restricted->id, '--apply' => true, '--snapshot' => $plan['snapshot']])->assertFailed();
        }
        $draw->update(['oop_published' => false]);
        $this->artisan('draw:convert-hybrid', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id, '--apply' => true, '--snapshot' => $plan['snapshot']])->assertFailed();
        $this->assertSame(20, $draw->drawFixtures()->count());
        $second = $draw->drawFixtures()->whereHas('drawGroup', fn ($query) => $query->where('round_robin_phase', 2))->first();
        $second->update(['match_status' => 2]);
        $this->artisan('draw:convert-hybrid', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id])->assertFailed();
        $this->assertSame(16, DB::table('fixture_results')->count());
    }
}
