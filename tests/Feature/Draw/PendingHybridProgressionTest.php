<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\{GroupAssignmentService, HybridRoundRobinService, PendingHybridRoundRobinService, RoundRobinProgressReadiness, TwoStageRoundRobinService};
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PendingHybridProgressionTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $hybrid = true, bool $complete = true): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 6, 'published' => true]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'published' => false, 'oop_published' => false, 'locked' => false]);
        $this->actingAs($actor)->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_round_robin',
            'groups' => 2, 'second_stage_format' => $hybrid ? HybridRoundRobinService::FORMAT : 'equal_positions'])->assertRedirect();
        $draw = $draw->fresh();
        $draw->settings->update(['score_format' => 'timed', 'num_sets' => 1, 'match_duration_minutes' => 15, 'changeover_minutes' => 0]);
        $players = [];
        foreach ($draw->groups()->orderBy('name')->get() as $group) foreach (range(1, $group->name === 'A' ? 4 : 5) as $position) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create(['name' => $group->name.$position, 'surname' => 'Example'])->id);
            CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 1]);
            $draw->registrations()->attach($registration->id);
            $group->registrations()->attach($registration->id, ['seed' => $position]);
            $players[$group->name.$position] = $registration->id;
        }
        app(RoundRobinGenerationService::class)->generate($draw->fresh());
        $first = $draw->drawFixtures()->whereHas('drawGroup', fn ($query) => $query->where('round_robin_phase', 1))->orderBy('id')->get();
        if ($complete) foreach ($first as $fixture) {
            $drawn = in_array($fixture->registration1_id, [$players['B4'], $players['B5']]) && in_array($fixture->registration2_id, [$players['B4'], $players['B5']]);
            $losingScore = max($fixture->registration1_id, $fixture->registration2_id) === $players['B4'] ? 2 : 0;
            app(DrawService::class)->saveScore($fixture, $drawn ? [[14, 14]] : ($fixture->registration1_id < $fixture->registration2_id ? [[6, $losingScore]] : [[$losingScore, 6]]));
        }
        return [$draw->fresh(), $actor, $players, $first->modelKeys()];
    }

    private function payload(Draw $draw): array
    {
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        return ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(), 'standings_snapshot' => $review['snapshot']];
    }

    private function book(Draw $draw, int $fixtureId): Venue
    {
        $venue = Venue::forceCreate(['name' => 'Staged test courts']);
        $draw->event->venues()->attach($venue->id, ['num_courts' => 2]);
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixtureId, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-11-14 10:00:00', 'duration_minutes' => 15]);
        DB::table('schedules')->insert(['draw_id' => $draw->id, 'fixture_id' => $fixtureId, 'venue_id' => $venue->id, 'time' => '2026-11-14 10:00:00']);
        DB::table('published_schedule_assignments')->insert(['event_id' => $draw->event_id, 'draw_id' => $draw->id,
            'fixture_kind' => 'individual', 'fixture_id' => $fixtureId, 'venue_id' => $venue->id, 'court' => '1',
            'scheduled_at' => '2026-11-14 10:00:00', 'duration' => 15, 'published_at' => now()]);
        return $venue;
    }

    private function protectedRows(Draw $draw): array
    {
        return [$draw->drawFixtures()->orderBy('id')->pluck('id')->all(),
            DB::table('fixture_results')->whereIn('fixture_id', $draw->drawFixtures()->pluck('id'))->orderBy('id')->get()->toJson(),
            DB::table('order_of_plays')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson(),
            DB::table('schedules')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson(),
            DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson()];
    }

    public function test_selected_hybrid_prepares_once_then_confirms_in_place_with_carried_references_and_frozen_sources(): void
    {
        [$draw, , $players, $sourceIds] = $this->scenario();
        $this->assertCount(16, $sourceIds);
        $this->assertSame(26, $draw->drawFixtures()->count());
        $this->assertFalse($draw->hasConfirmedSecondRoundRobin());
        $this->assertCount(0, app(TwoStageRoundRobinService::class)->prepare($draw));
        $this->assertFalse(app(GroupAssignmentService::class)->fixturesNeedRegeneration($draw->fresh()));
        $state = $draw->settings->playoff_config['_second_round_robin'];
        $this->book($draw, $state['lower_fixture_ids'][0]);
        $before = $this->protectedRows($draw);
        $readiness = app(RoundRobinProgressReadiness::class)->forDraw($draw->fresh());
        $this->assertSame(['total' => 16, 'completed' => 16, 'remaining' => 0], $readiness['round_robin']);
        $this->assertTrue($readiness['ready']); $this->assertTrue($readiness['pending']);
        $payload = $this->payload($draw);
        $this->assertSame($before, $this->protectedRows($draw));
        $draw->update(['published' => true, 'oop_published' => true]);
        // Publication is part of the reviewed snapshot, so review after publishing.
        $payload = $this->payload($draw);
        $this->postJson(route('backend.draw.progress', $draw), $payload)->assertOk()->assertJsonPath('progress.created', 0);
        $this->postJson(route('backend.draw.progress', $draw), $payload)->assertOk()->assertJsonPath('progress.already_progressed', true);
        $this->assertSame($before, $this->protectedRows($draw));
        $draw = $draw->fresh(); $this->assertTrue($draw->hasConfirmedSecondRoundRobin());
        $state = $draw->settings->playoff_config['_second_round_robin'];
        $this->assertCount(4, $state['carried_fixture_ids']);
        $this->assertCount(5, $draw->groups->firstWhere('id', $state['lower_group_id'])->groupRegistrations);
        $this->assertCount(10, HybridRoundRobinService::effectiveFixtures($draw)->where('draw_group_id', $state['lower_group_id'])->where('stage', 'RR'));
        $semis = $draw->drawFixtures()->whereIn('id', $state['semi_fixture_ids'])->orderBy('id')->get();
        $this->assertSame([[$players['A1'],$players['B2']],[$players['B1'],$players['A2']]],
            $semis->map(fn ($fixture) => [$fixture->registration1_id,$fixture->registration2_id])->all());
        $this->assertFalse(app(RoundRobinProgressReadiness::class)->forDraw($draw)['pending']);
        $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->assertJsonPath('review.pending', false);
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertDontSee('class="btn btn-primary align-self-start progress-draw"', false);
        $this->postJson(route('backend.roundrobin.score.store', Fixture::findOrFail($sourceIds[0])), ['sets' => ['6-1']])->assertConflict();
        $this->assertSame($before, $this->protectedRows($draw));
    }

    public function test_admin_and_venue_prompts_cover_both_staged_formats_and_only_authorized_event_scopes(): void
    {
        foreach ([false, true] as $hybrid) {
            [$draw, $actor, , $first] = $this->scenario($hybrid);
            $venue = $this->book($draw, $first[0]);
            $destination = $hybrid ? 'top-four playoffs and the lower round robin' : 'second round robin';
            $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('data-rr-progression', false)
                ->assertSee('data-progress-destination="'.$destination.'"', false)->assertSee('Review &amp; progress to '.$destination, false);
            $url = route('frontend.scoring.workspace', ['event' => $draw->event_id, 'venue' => $venue->id]);
            $page = $this->get($url)->assertOk(); $state = $page->viewData('playoffProgression')->get($draw->id);
            $this->assertSame(16, $state['total']); $this->assertSame(16, $state['completed']);
            $this->assertTrue($state['ready']); $this->assertTrue($state['pending']); $this->assertSame($destination, $state['destination']);
            $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
            $this->getJson(route('backend.draw.progress-review', $foreign))->assertForbidden();
            $this->get(route('frontend.scoring.workspace', ['event' => $draw->event_id, 'draw_ids' => [$foreign->id]]))->assertNotFound();
            $outsider = User::factory()->create()->assignRole('admin');
            $this->actingAs($outsider)->getJson(route('backend.draw.progress-review', $draw))->assertForbidden();
            $this->postJson(route('backend.draw.progress', $draw))->assertForbidden();
            $this->actingAs($actor);
        }
    }

    public function test_partial_sets_do_not_complete_first_stage_but_timed_draws_and_scoreless_walkovers_do(): void
    {
        [$draw, , , $sourceIds] = $this->scenario(true, false);
        $draw->settings->update(['score_format' => null, 'num_sets' => 3]);
        foreach (Fixture::whereIn('id', $sourceIds)->get() as $fixture) app(DrawService::class)->saveScore($fixture, [[6,0]]);
        $readiness = app(RoundRobinProgressReadiness::class)->forDraw($draw->fresh());
        $this->assertSame(0, $readiness['round_robin']['completed']); $this->assertFalse($readiness['ready']);
        $this->getJson(route('backend.draw.progress-review', $draw))->assertStatus(422)->assertJsonPath('round_robin.completed', 0);
        [$timed, , , $timedIds] = $this->scenario();
        $walkover = Fixture::findOrFail($timedIds[0]); $walkover->fixtureResults()->delete(); $walkover->update(['match_status' => 3]);
        $this->getJson(route('backend.draw.progress-review', $timed))->assertOk();
        $this->assertSame(16, app(RoundRobinProgressReadiness::class)->forDraw($timed->fresh())['round_robin']['completed']);
    }

    public function test_completed_tied_groups_return_a_warning_state_without_qualifying_players(): void
    {
        [$draw, , , $sourceIds] = $this->scenario();
        $group = $draw->groups->firstWhere('name','A');
        foreach (Fixture::whereIn('id',$sourceIds)->where('draw_group_id',$group->id)->get() as $fixture) app(DrawService::class)->saveScore($fixture, [[5,5]]);
        $before = $this->protectedRows($draw);
        $this->getJson(route('backend.draw.progress-review', $draw))->assertStatus(422)
            ->assertJsonPath('round_robin.completed',16)->assertJsonPath('pending',true)->assertJsonPath('ready',false)
            ->assertJsonPath('message','Resolve the tied first-stage positions before progressing.');
        $this->assertSame($before,$this->protectedRows($draw));
        foreach (Fixture::whereIn('id',$sourceIds)->where('draw_group_id',$group->id)->get() as $fixture) app(DrawService::class)->saveScore($fixture,
            $fixture->registration1_id < $fixture->registration2_id ? [[6,0]] : [[0,6]]);
        $this->getJson(route('backend.draw.progress-review',$draw))->assertOk();
    }

    public function test_pending_playoffs_reject_scoring_and_tampered_feeders_or_stale_bookings_cannot_progress(): void
    {
        [$draw] = $this->scenario(); $state = $draw->settings->playoff_config['_second_round_robin'];
        $semi = Fixture::findOrFail($state['semi_fixture_ids'][0]);
        $before = $this->protectedRows($draw);
        $this->postJson(route('api.draws.fixtures.score.store',['draw'=>$draw,'fixture'=>$semi]), ['sets'=>['6-0']])->assertConflict();
        $this->postJson(route('backend.roundrobin.score.store',$semi), ['sets'=>['6-0']])->assertConflict();
        try { app(DrawService::class)->saveBracketScore($semi,[[6,0]]); $this->fail('Pending playoff accepted a score.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(409,$exception->getStatusCode()); }
        $this->assertSame($before,$this->protectedRows($draw));
        $payload = $this->payload($draw); $this->book($draw,$semi->id);
        $booked = $this->protectedRows($draw);
        $this->postJson(route('backend.draw.progress',$draw),$payload)->assertConflict();
        $this->assertSame($booked,$this->protectedRows($draw));
        $semi->update(['loser_feeder_slot'=>1,'registration1_id'=>Fixture::where('draw_id',$draw->id)->where('stage','RR')->whereHas('drawGroup',fn($q)=>$q->where('round_robin_phase',1))->firstOrFail()->registration1_id]);
        $source=Fixture::where('draw_id',$draw->id)->where('stage','RR')->whereHas('drawGroup',fn($q)=>$q->where('round_robin_phase',1))->firstOrFail();
        try {
            app(DrawService::class)->saveScore($source,(int)$source->winner_registration===(int)$source->registration1_id ? [[0,6]] : [[6,0]]);
            $this->fail('Malformed occupied hybrid structure allowed ordinary correction.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(409,$exception->getStatusCode()); }
        $this->assertSame($booked,$this->protectedRows($draw));
        $this->assertFalse($draw->fresh()->hasConfirmedSecondRoundRobin());
        $semi->update(['loser_feeder_slot'=>2]);
        $this->getJson(route('backend.draw.progress-review',$draw))->assertStatus(422);
        $this->postJson(route('backend.draw.progress',$draw),$payload)->assertConflict();
        $this->assertSame($booked,$this->protectedRows($draw));
    }

    public function test_setup_and_generic_settings_preserve_prepared_hybrid_metadata_and_bookings(): void
    {
        [$draw] = $this->scenario(true,false); $state=$draw->settings->playoff_config['_second_round_robin'];
        $this->book($draw,$state['final_fixture_id']); $before=$this->protectedRows($draw);
        $this->get(route('draw.setup.show',$draw))->assertOk()->assertSee('data-current-groups="2"',false)->assertSee('value="2" selected',false);
        $this->post(route('draw.setup.store',$draw),['workflow'=>'round_robin_round_robin','groups'=>2,'second_stage_format'=>HybridRoundRobinService::FORMAT])->assertRedirect();
        $this->post(route('draw.setup.store',$draw),['workflow'=>'round_robin_round_robin','groups'=>2,'second_stage_format'=>'equal_positions','reset_existing'=>1])->assertConflict();
        $page=$this->get(route('backend.draw.roundrobin.show',$draw))->assertOk()->assertSee('Hybrid qualification and playoff structure are fixed')
            ->assertDontSee('let playoffConfig =',false)->assertDontSee('id="btn-add-playoff"',false);
        $this->postJson(route('backend.draw.update-settings',$draw),['score_format'=>'one_full_set'])->assertOk();
        $this->assertSame($state,$draw->fresh()->settings->playoff_config['_second_round_robin']);
        $generic=[['slug'=>'MAIN','name'=>'Altered','size'=>2,'positions'=>[1,2],'enabled'=>true]];
        $this->postJson(route('backend.draw.update-settings',$draw),['playoff_config'=>$generic])->assertStatus(422);
        $this->postJson(route('backend.draw.update-playoff-config',$draw),['playoff_config'=>$generic])->assertStatus(422);
        $this->assertSame($before,$this->protectedRows($draw));
        $this->getJson(route('backend.draw.progress-review',$draw))->assertStatus(422);
        $this->artisan('draw:convert-hybrid',['draw'=>$draw->id,'--event'=>$draw->event_id,'--actor'=>auth()->id()])->assertFailed();
        $this->assertSame($before,$this->protectedRows($draw));
    }

    public function test_historical_confirmed_hybrid_is_read_only_and_suppresses_both_progression_prompts(): void
    {
        [$draw,$actor,,$sourceIds]=$this->scenario(false);
        $this->postJson(route('backend.draw.progress',$draw),$this->payload($draw))->assertOk();
        $service=app(HybridRoundRobinService::class); $plan=$service->review($draw->fresh(),$draw->event_id);
        $service->apply($draw,$draw->event_id,$plan['snapshot'],$actor);
        $draw=$draw->fresh(); $venue=$this->book($draw,$sourceIds[0]); $before=$this->protectedRows($draw);
        $state=app(RoundRobinProgressReadiness::class)->forDraw($draw);
        $this->assertSame('hybrid',$state['kind']); $this->assertFalse($state['pending']);
        $this->getJson(route('backend.draw.progress-review',$draw))->assertOk()->assertJsonPath('review.pending',false);
        $this->get(route('backend.draw.roundrobin.show',$draw))->assertOk()->assertDontSee('class="btn btn-primary align-self-start progress-draw"',false);
        $this->get(route('frontend.scoring.workspace',['event'=>$draw->event_id,'venue'=>$venue->id]))->assertOk()
            ->assertViewHas('playoffProgression',fn($states)=>!$states->get($draw->id)['pending']);
        $this->assertSame($before,$this->protectedRows($draw));
    }
}
