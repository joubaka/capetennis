<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, Draw, Event, Fixture, Registration, User};
use App\Services\Draw\FlexibleMonradService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawSetupTest extends TestCase
{
    use RefreshDatabase;

    private function draw(): Draw
    {
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        return $draw;
    }

    private function slots(Draw $draw): array
    {
        $slots = [];
        foreach (['aa', 'ab', 'ba', 'bb'] as $path) {
            $player = Registration::factory()->create();
            $player->categoryEvents()->attach($draw->category_event_id, ['status' => 'registered']);
            $slots[$path] = ['type' => 'player', 'id' => $player->id];
        }
        return $slots;
    }

    public function test_first_decision_is_read_only_and_round_robin_continues_to_groups(): void
    {
        $draw = $this->draw();
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertRedirect(route('draw.setup.show', $draw));
        $response = $this->get(route('draw.setup.show', $draw))->assertOk()
            ->assertSee('How should this draw start?')->assertSee('Round robin → playoffs')
            ->assertSee('Playoffs only')->assertSee('Monrad only')->assertSee('Custom Monrad');
        if (getenv('DRAW_SETUP_SNAPSHOT')) {
            \Illuminate\Support\Facades\Storage::disk('local')->put('testing/draw-setup.html', $response->getContent());
        }
        $this->assertDatabaseCount('flexible_monrad_draws', 0);
        $this->assertSame(0, $draw->groups()->count());
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])
            ->assertRedirect(route('backend.draw.roundrobin.show', $draw));
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('Build your groups');
        $this->assertSame(0, $draw->drawFixtures()->count());
        $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 0, 'draft' => ['size' => 4, 'slots' => []]])->assertConflict();
    }

    public function test_direct_formats_persist_resume_and_generate_the_correct_matches(): void
    {
        foreach (['playoffs' => 3, 'monrad' => 4, 'custom_monrad' => 4] as $workflow => $count) {
            $draw = $this->draw();
            $this->post(route('draw.setup.store', $draw), ['workflow' => $workflow])->assertRedirect(route('backend.draw.roundrobin.show', $draw));
            $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('Step 2')->assertSee('Setup &amp; Rules', false);
            $this->get(route('flexible-monrad.show', $draw))->assertRedirect(route('backend.draw.roundrobin.show', $draw));
            $this->assertSame($workflow, $draw->fresh()->settings->workflow);
            $this->assertIsObject(app(FlexibleMonradService::class)->state($draw->fresh())['draft']['slots']);
            $this->post(route('draw.setup.store', $draw), ['workflow' => $workflow])->assertRedirect();
            $this->assertSame(1, $draw->fresh()->flexibleMonrad->revision);
            $draft = ['size' => 4, 'slots' => $this->slots($draw), 'mode' => 'custom_monrad'];
            $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1, 'draft' => $draft])->assertOk();
            $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 2])->assertOk();
            $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 3])->assertOk();
            $this->assertSame($count, $draw->drawFixtures()->count());
            $this->assertSame(0, $draw->groups()->count());
            $graph = $draw->fresh()->flexibleMonrad->graph;
            $this->assertCount($workflow === 'playoffs' ? 2 : 4, $graph['positions']);
            if ($workflow === 'playoffs') {
                foreach ($graph['nodes'] as $node) $this->assertSame('Main draw', $node['section']);
            }
        }
    }

    public function test_only_custom_monrad_accepts_later_round_entry(): void
    {
        foreach (['playoffs', 'monrad', 'custom_monrad'] as $workflow) {
            $draw = $this->draw();
            $slots = $this->slots($draw);
            $this->post(route('draw.setup.store', $draw), ['workflow' => $workflow])->assertRedirect();
            $draft = ['size' => 4, 'slots' => ['a' => $slots['aa'], 'ba' => $slots['ba'], 'bb' => ['type' => 'bye']]];
            $response = $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1, 'draft' => $draft]);
            if ($workflow === 'custom_monrad') $response->assertOk();
            else $response->assertUnprocessable();
        }
    }

    public function test_selection_is_authorized_validated_and_locked_draws_are_protected(): void
    {
        $draw = $this->draw();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'invalid'])->assertUnprocessable();
        foreach (['locked', 'published'] as $flag) {
            $draw->update([$flag => true]);
            $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'monrad'])->assertForbidden();
            $draw->update([$flag => false]);
        }
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->get(route('draw.setup.show', $draw))->assertForbidden();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'monrad'])->assertForbidden();
        $this->assertDatabaseCount('flexible_monrad_draws', 0);
    }

    public function test_existing_assignments_and_fixtures_cannot_be_replaced(): void
    {
        $draw = $this->draw();
        $group = $draw->groups()->create(['name' => 'A']);
        $slots = $this->slots($draw);
        $group->registrations()->attach($slots['aa']['id']);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'monrad'])->assertConflict();
        $this->assertSame(1, $group->registrations()->count());
        $draw = $this->draw();
        Fixture::factory()->create(['draw_id' => $draw->id]);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'playoffs'])->assertConflict();
        $this->assertSame(1, $draw->drawFixtures()->count());
        $draw = $this->draw();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'custom_monrad'])->assertRedirect();
        app(FlexibleMonradService::class)->save($draw, ['size' => 4, 'slots' => $this->slots($draw)], 1);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])->assertConflict();
        $this->assertCount(4, $draw->fresh()->flexibleMonrad->draft['slots']);
    }

    public function test_empty_selection_can_change_without_creating_groups_or_fixtures(): void
    {
        $draw = $this->draw();
        foreach (['monrad', 'playoffs', 'round_robin_playoffs'] as $workflow) {
            $this->post(route('draw.setup.store', $draw), ['workflow' => $workflow])->assertRedirect();
            $this->assertSame($workflow, $draw->fresh()->settings->workflow);
        }
        $this->assertFalse($draw->fresh()->usesFlexibleMonrad());
        $this->assertSame(0, $draw->groups()->count());
        $this->assertSame(0, $draw->drawFixtures()->count());
    }

    public function test_event_wide_draw_chooses_format_before_category_and_rejects_foreign_category(): void
    {
        $draw = $this->draw();
        $categoryId = $draw->category_event_id;
        $draw->update(['category_event_id' => null]);
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertRedirect(route('draw.setup.show', $draw));
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'playoffs'])->assertOk()->assertSee('Which category will play?');
        $this->assertNull($draw->fresh()->settings?->workflow);
        $foreign = CategoryEvent::factory()->create();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'playoffs', 'category_event_id' => $foreign->id])->assertUnprocessable();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'playoffs', 'category_event_id' => $categoryId])->assertRedirect(route('backend.draw.roundrobin.show', $draw));
        $this->assertEquals($categoryId, $draw->fresh()->category_event_id);
    }

    public function test_playoffs_with_byes_have_only_a_champion_and_runner_up(): void
    {
        $graph = app(\App\Services\Draw\FlexibleMonradCompiler::class)->compile([
            'mode' => 'playoffs', 'size' => 4, 'slots' => [
                'aa' => ['type' => 'player', 'id' => 1], 'ab' => ['type' => 'player', 'id' => 2],
                'ba' => ['type' => 'bye'], 'bb' => ['type' => 'bye'],
            ],
        ]);
        $this->assertCount(1, $graph['nodes']);
        $this->assertSame(['type' => 'winner', 'match' => 'main_a'], $graph['positions'][1]);
        $this->assertSame(['type' => 'loser', 'match' => 'main_a'], $graph['positions'][2]);
    }
}
