<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, DrawAuditLog, Event, Fixture, FixtureResult, Player, Registration, User};
use App\Services\Draw\{DrawWorkflow, GroupAssignmentService, HybridRoundRobinService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IndividualDrawSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function draw(?CategoryEvent $category = null): Draw
    {
        $category ??= CategoryEvent::factory()->create(['event_id' => Event::factory()->create()->id]);
        $draw = Draw::factory()->create(['event_id'=>$category->event_id, 'category_event_id'=>$category->id]);
        $draw->settings()->create(['workflow'=>'round_robin', 'score_format'=>'best_of_3_full']);
        Role::firstOrCreate(['name'=>'admin', 'guard_name'=>'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$draw->event_id, 'user_id'=>$admin->id]);
        $this->actingAs($admin);
        return $draw;
    }

    private function entrant(CategoryEvent $category, ?Player $player = null): Registration
    {
        $registration = Registration::factory()->create();
        $registration->players()->attach(($player ?? Player::factory()->create())->id);
        CategoryEventRegistration::factory()->create(['category_event_id'=>$category->id,
            'registration_id'=>$registration->id, 'payment_status_id'=>1, 'status'=>'active']);
        return $registration;
    }

    private function save(Draw $draw, $group, array $ids): void
    {
        $this->postJson(route('backend.draw.save-groups', $draw), ['revision' => app(\App\Services\Draw\GroupAssignmentService::class)->revision($draw->fresh())] + (['groups'=>[
            ['group_id'=>$group->id, 'registration_ids'=>$ids],
        ]]))->assertOk();
    }

    public function test_two_explicitly_saved_subsets_complete_players_without_generating_or_claiming_rosters(): void
    {
        $first = $this->draw();
        $second = $this->draw($first->categoryEvent);
        $players = collect(range(1, 9))->map(fn () => $this->entrant($first->categoryEvent));
        $a = $first->groups()->create(['name'=>'A']);
        $b = $second->groups()->create(['name'=>'A']);
        $a->registrations()->sync($players->take(4)->pluck('id')->all());
        $b->registrations()->sync($players->skip(4)->pluck('id')->all());
        $this->assertFalse(app(DrawWorkflow::class)->forDraw($first->fresh())['playersReady']);
        $this->assertFalse(app(DrawWorkflow::class)->forDraw($second->fresh())['playersReady']);
        foreach ([[$first,$a,$players->take(4)],[$second,$b,$players->skip(4)]] as [$draw,$group,$subset]) {
            $this->save($draw, $group, $subset->pluck('id')->all());
            $state = app(DrawWorkflow::class)->forDraw($draw->fresh());
            $this->assertTrue($state['playersReady']);
            $this->assertSame('matches', $state['next']);
        }
        $this->assertDatabaseCount('draw_registrations', 0);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertDatabaseCount('category_event_registrations', 9);
        // A direct unsaved edit invalidates exact membership/seed evidence.
        $a->groupRegistrations()->where('registration_id',$players[0]->id)->update(['seed'=>99]);
        $this->assertFalse(app(DrawWorkflow::class)->forDraw($first->fresh())['playersReady']);
    }

    public function test_partial_save_keeps_untouched_groups_in_selection_and_preserves_canonical_roster(): void
    {
        $draw = $this->draw();
        $players = collect(range(1, 5))->map(fn () => $this->entrant($draw->categoryEvent));
        $a = $draw->groups()->create(['name'=>'A']);
        $b = $draw->groups()->create(['name'=>'B']);
        $b->registrations()->sync($players->slice(2,2)->pluck('id')->all());
        $this->save($draw,$a,$players->take(2)->pluck('id')->all());
        $this->assertSame($players->take(4)->pluck('id')->all(), app(GroupAssignmentService::class)->savedSelection($draw->fresh()));
        $this->assertSame($players->slice(2,2)->pluck('id')->all(), $b->registrations()->pluck('registrations.id')->all());
        $this->assertTrue(app(DrawWorkflow::class)->forDraw($draw->fresh())['playersReady']);
        $draw->registrations()->sync($players->take(4)->pluck('id')->all());
        $before = DB::table('draw_registrations')->where('draw_id',$draw->id)->get()->toArray();
        $fixture = Fixture::factory()->create(['draw_id'=>$draw->id, 'draw_group_id'=>$a->id]);
        FixtureResult::factory()->create(['fixture_id'=>$fixture->id]);
        $this->postJson(route('backend.draw.save-groups',$draw), ['revision' => app(\App\Services\Draw\GroupAssignmentService::class)->revision($draw->fresh())] + (['groups'=>[
            ['group_id'=>$a->id,'registration_ids'=>[$players[0]->id,$players[4]->id]],
        ]]))->assertUnprocessable();
        $this->assertEquals($before,DB::table('draw_registrations')->where('draw_id',$draw->id)->get()->toArray());
        $this->assertSame($players->take(2)->pluck('id')->all(),$a->registrations()->pluck('registrations.id')->all());
        $this->assertDatabaseCount('fixture_results',1);
    }

    public function test_sibling_group_or_roster_assignments_reject_forged_web_and_api_saves_atomically(): void
    {
        foreach (['groups','roster'] as $source) {
            $draw = $this->draw();
            $sibling = $this->draw($draw->categoryEvent);
            $registration = $this->entrant($draw->categoryEvent);
            $group = $draw->groups()->create(['name'=>'A']);
            if ($source === 'groups') $sibling->groups()->create(['name'=>'A'])->registrations()->attach($registration->id);
            else $sibling->registrations()->attach($registration->id);
            foreach (['backend.draw.save-groups','api.draws.groups.save'] as $route) {
                $before = DrawAuditLog::count();
                $this->postJson(route($route,$draw), ['revision' => app(\App\Services\Draw\GroupAssignmentService::class)->revision($draw->fresh())] + (['groups'=>[
                    ['group_id'=>$group->id,'registration_ids'=>[$registration->id]],
                ]]))->assertUnprocessable();
                $this->assertSame(0,$group->registrations()->count());
                $this->assertSame($before,DrawAuditLog::count());
            }
            $this->getJson(route('backend.draw.available-players',$draw))->assertOk()->assertJsonPath('categories',[]);
            $this->postJson(route('draws.add-player',$draw),['player_id'=>$registration->id])->assertUnprocessable();
            $this->postJson(route('admin.draws.addPlayerToDraw'),['draw_id'=>$draw->id,'player_ids'=>[$registration->id]])->assertUnprocessable();
            $this->postJson(route('admin.draws.addCategoryPlayers'),['draw_id'=>$draw->id,'category_id'=>$draw->category_event_id])->assertUnprocessable();
            $this->assertSame(0,$draw->registrations()->count());
        }
    }

    public function test_duplicate_registration_aliases_do_not_allow_the_same_player_in_two_draws_or_twice_in_one(): void
    {
        $draw = $this->draw();
        $sibling = $this->draw($draw->categoryEvent);
        $player = Player::factory()->create();
        $one = $this->entrant($draw->categoryEvent,$player);
        $two = $this->entrant($draw->categoryEvent,$player);
        $group = $draw->groups()->create(['name'=>'A']);
        $siblingGroup = $sibling->groups()->create(['name'=>'A']);
        $siblingGroup->registrations()->attach($one->id);
        $this->postJson(route('backend.draw.save-groups',$draw), ['revision' => app(\App\Services\Draw\GroupAssignmentService::class)->revision($draw->fresh())] + (['groups'=>[
            ['group_id'=>$group->id,'registration_ids'=>[$two->id]],
        ]]))->assertUnprocessable();
        $this->postJson(route('draws.add-player',$draw),['player_id'=>$two->id])->assertUnprocessable();
        $siblingGroup->registrations()->detach();
        $this->postJson(route('backend.draw.save-groups',$draw), ['revision' => app(\App\Services\Draw\GroupAssignmentService::class)->revision($draw->fresh())] + (['groups'=>[
            ['group_id'=>$group->id,'registration_ids'=>[$one->id,$two->id]],
        ]]))->assertUnprocessable();
        $draw->registrations()->attach($one->id);
        $this->postJson(route('draws.add-player',$draw),['player_id'=>$two->id])->assertUnprocessable();
        // Existing malformed assignments must not acquire a ready badge either.
        $group->registrations()->attach([$one->id,$two->id]);
        $draw->registrations()->attach($two->id);
        $this->assertFalse(app(DrawWorkflow::class)->forDraw($draw->fresh())['playersReady']);
    }

    public function test_different_event_and_category_competitions_can_use_the_same_player(): void
    {
        $first = $this->draw();
        $player = Player::factory()->create();
        $registration = $this->entrant($first->categoryEvent,$player);
        $first->groups()->create(['name'=>'A'])->registrations()->attach($registration->id);
        foreach ([CategoryEvent::factory()->create(['event_id'=>$first->event_id]),CategoryEvent::factory()->create()] as $category) {
            $other = $this->draw($category);
            $otherRegistration = $this->entrant($category,$player);
            $group = $other->groups()->create(['name'=>'A']);
            $this->save($other,$group,[$otherRegistration->id]);
            $this->assertSame([$otherRegistration->id],$group->registrations()->pluck('registrations.id')->all());
        }
    }

    public function test_two_stage_setup_and_group_resize_reject_one_group_without_mutation(): void
    {
        $draw = $this->draw();
        $before = $draw->settings->getAttributes();
        $this->postJson(route('draw.setup.store',$draw),['workflow'=>'round_robin_round_robin','groups'=>1])
            ->assertUnprocessable()->assertJsonValidationErrors('groups');
        $this->assertSame($before,$draw->fresh()->settings->getAttributes());
        $this->assertSame(0,$draw->groups()->count());
        $this->postJson(route('draw.setup.store',$draw),['workflow'=>'round_robin_round_robin','groups'=>2])->assertRedirect();
        $groupIds = $draw->groups()->pluck('id')->all();
        $settings = $draw->fresh()->settings->getAttributes();
        $this->postJson('/backend/draw/'.$draw->id.'/settings',['boxes'=>1])->assertUnprocessable()->assertJsonValidationErrors('boxes');
        $this->assertSame($groupIds,$draw->groups()->pluck('id')->all());
        $this->assertSame($settings,$draw->fresh()->settings->getAttributes());
        $draw->settings()->update(['playoff_config'=>[
            '_second_round_robin'=>['state'=>'selected','format'=>HybridRoundRobinService::FORMAT,'carried_fixture_ids'=>[]],
        ]]);
        $hybridSettings = $draw->fresh()->settings->getAttributes();
        $this->postJson('/backend/draw/'.$draw->id.'/settings',['boxes'=>3])->assertUnprocessable()->assertJsonValidationErrors('boxes');
        $this->assertSame($groupIds,$draw->groups()->pluck('id')->all());
        $this->assertSame($hybridSettings,$draw->fresh()->settings->getAttributes());
        $ordinary = $this->draw();
        $this->postJson(route('draw.setup.store',$ordinary),['workflow'=>'round_robin','groups'=>1])->assertRedirect();
        $this->assertSame(1,$ordinary->groups()->count());
        $ordinary->groups()->create(['name'=>'B']);
        $this->postJson('/backend/draw/'.$ordinary->id.'/settings',['boxes'=>1])->assertOk();
    }

    public function test_hybrid_players_label_and_format_help_match_selected_competition(): void
    {
        $draw = $this->draw();
        $draw->settings()->update(['workflow'=>'round_robin_round_robin','playoff_config'=>[
            '_second_round_robin'=>['state'=>'selected','format'=>HybridRoundRobinService::FORMAT,'carried_fixture_ids'=>[]],
        ]]);
        $draw->groups()->create(['name'=>'A']);
        $draw->groups()->create(['name'=>'B']);
        $page = $this->get(route('backend.draw.roundrobin.show',$draw))->assertOk()
            ->assertSee('top two from each group enter semifinals; the remaining players enter one round robin with carried results.');
        $this->assertMatchesRegularExpression('/name="workflow" value="round_robin_round_robin" checked/',$page->getContent());
        $this->assertDoesNotMatchRegularExpression('/name="workflow" value="round_robin_playoffs" checked/',$page->getContent());
        $setup = $this->get(route('draw.setup.show',$draw))->assertOk()
            ->assertSee('Best for a second group stage, or top-four playoffs with a lower round robin.');
        $this->assertSame(1,substr_count($setup->getContent(),'Use when seeded players must enter in different rounds.'));
        $this->assertDatabaseCount('fixtures',0);
    }

    public function test_initial_and_refreshed_rosters_hide_sibling_players_and_offer_legacy_selection_confirmation(): void
    {
        $draw = $this->draw();
        $sibling = $this->draw($draw->categoryEvent);
        $one = $this->entrant($draw->categoryEvent);
        $two = $this->entrant($draw->categoryEvent);
        $three = $this->entrant($draw->categoryEvent);
        $group = $draw->groups()->create(['name'=>'A']);
        $group->registrations()->attach([$one->id,$two->id]);
        $sibling->groups()->create(['name'=>'A'])->registrations()->attach($three->id);
        $page = $this->get(route('backend.draw.roundrobin.show',$draw))->assertOk()
            ->assertSee('window.RR_NEEDS_SELECTION_CONFIRMATION = true',false);
        preg_match('/window\.RR_ROSTER = (.*);/',$page->getContent(),$matches);
        $roster = json_decode($matches[1],true,512,JSON_THROW_ON_ERROR);
        $this->assertSame([$one->id,$two->id],array_column($roster,'id'));
        $this->getJson(route('backend.draw.groups-data',$draw))->assertOk()->assertJsonPath('needs_selection_confirmation',true);
        $this->save($draw,$group,[$one->id,$two->id]);
        $this->getJson(route('backend.draw.groups-data',$draw))->assertOk()->assertJsonPath('needs_selection_confirmation',false);
        $this->assertTrue(app(DrawWorkflow::class)->forDraw($draw->fresh())['playersReady']);
        $this->assertDatabaseCount('fixtures',0);
    }
}
