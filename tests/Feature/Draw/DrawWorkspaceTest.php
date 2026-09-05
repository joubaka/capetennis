<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, FixtureResult, Player, Registration, User};
use App\Services\Draw\GroupAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        return [$draw, $category];
    }

    private function entrant(CategoryEvent $category, array $attributes = []): Registration
    {
        $registration = Registration::factory()->create();
        $registration->players()->attach(Player::factory()->create()->id);
        CategoryEventRegistration::factory()->create(array_merge([
            'category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 1,
        ], $attributes));
        return $registration;
    }

    public function test_empty_workspace_renders_all_views_without_creating_groups_or_fixtures(): void
    {
        [$draw] = $this->workspace();
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertRedirect(route('draw.setup.show', $draw));
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])
            ->assertRedirect(route('backend.draw.roundrobin.show', $draw));
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()
            ->assertSee('Players &amp; Groups', false)->assertSee('Draw &amp; Results', false)
            ->assertSee('Setup &amp; Rules', false)->assertSee('Print Draw Pack')
            ->assertSee('Manage full schedule')
            ->assertSee('data-full-schedule-action', false)
            ->assertSee(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'manual' => 1]));
        $this->assertSame(0, $draw->groups()->count());
        $this->assertSame(0, $draw->drawFixtures()->count());
    }

    public function test_grouped_workspace_does_not_generate_fixtures_on_refresh(): void
    {
        [$draw, $category] = $this->workspace();
        $group = $draw->groups()->create(['name' => 'A']);
        $group->registrations()->attach([$this->entrant($category)->id, $this->entrant($category)->id]);
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk();
        $this->assertSame(0, $draw->drawFixtures()->count());
        $this->assertSame(2, $group->registrations()->count());
    }

    public function test_web_and_api_reject_foreign_group_without_mutating_either_draw(): void
    {
        [$draw, $category] = $this->workspace();
        $group = $draw->groups()->create(['name' => 'A']);
        $player = $this->entrant($category);
        $group->registrations()->attach($player->id);
        $foreign = Draw::factory()->create()->groups()->create(['name' => 'A']);
        foreach (['backend.draw.save-groups', 'api.draws.groups.save'] as $route) {
            $this->postJson(route($route, $draw), ['groups' => [
                ['group_id' => $group->id, 'registration_ids' => []],
                ['group_id' => $foreign->id, 'registration_ids' => [$player->id]],
            ]])->assertUnprocessable();
            $this->assertSame([$player->id], $group->registrations()->pluck('registrations.id')->all());
            $this->assertSame(0, $foreign->registrations()->count());
        }
    }

    public function test_only_paid_active_players_in_the_correct_category_can_be_assigned(): void
    {
        [$draw, $category] = $this->workspace();
        $group = $draw->groups()->create(['name' => 'A']);
        $invalid = [
            $this->entrant($category, ['payment_status_id' => 2]),
            $this->entrant($category, ['status' => 'withdrawn']),
            $this->entrant($category, ['withdrawn_at' => now()]),
            $this->entrant(CategoryEvent::factory()->create(['event_id' => $draw->event_id])),
            $this->entrant(CategoryEvent::factory()->create()),
        ];
        foreach ($invalid as $registration) {
            $this->postJson(route('backend.draw.save-groups', $draw), ['groups' => [
                ['group_id' => $group->id, 'registration_ids' => [$registration->id]],
            ]])->assertUnprocessable();
        }
        $this->assertSame(0, $group->registrations()->count());
        $this->getJson(route('backend.draw.available-players', $draw))->assertOk()->assertJsonPath('categories', []);
    }

    public function test_duplicate_assignment_across_groups_is_rejected(): void
    {
        [$draw, $category] = $this->workspace();
        $a = $draw->groups()->create(['name' => 'A']);
        $b = $draw->groups()->create(['name' => 'B']);
        $id = $this->entrant($category)->id;
        $this->postJson(route('backend.draw.save-groups', $draw), ['groups' => [
            ['group_id' => $a->id, 'registration_ids' => [$id]],
            ['group_id' => $b->id, 'registration_ids' => [$id]],
        ]])->assertUnprocessable();
        $this->assertDatabaseCount('draw_group_registrations', 0);
    }

    public function test_assign_move_remove_and_seed_order_survive_repeat_save_without_changing_entries(): void
    {
        [$draw, $category] = $this->workspace();
        $a = $draw->groups()->create(['name' => 'A']);
        $b = $draw->groups()->create(['name' => 'B']);
        $one = $this->entrant($category)->id;
        $two = $this->entrant($category)->id;
        $payload = ['groups' => [['group_id' => $a->id, 'registration_ids' => [$two, $one]], ['group_id' => $b->id, 'registration_ids' => []]]];
        $url = route('backend.draw.save-groups', $draw);
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $this->assertSame([$two, $one], $a->groupRegistrations()->pluck('registration_id')->all());
        $this->assertDatabaseCount('draw_group_registrations', 2);
        $payload['groups'][0]['registration_ids'] = [];
        $payload['groups'][1]['registration_ids'] = [$one];
        $this->postJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('draw_group_registrations', 1);
        $this->assertSame([$one], $b->groupRegistrations()->pluck('registration_id')->all());
        $this->assertSame(2, CategoryEventRegistration::where('category_event_id', $category->id)->where('payment_status_id', 1)->where('status', 'active')->count());
    }

    public function test_stale_revision_cannot_overwrite_another_admins_assignment(): void
    {
        [$draw, $category] = $this->workspace();
        $group = $draw->groups()->create(['name' => 'A']);
        $revision = app(GroupAssignmentService::class)->revision($draw);
        $id = $this->entrant($category)->id;
        $group->registrations()->attach($id);
        $this->postJson(route('backend.draw.save-groups', $draw), ['revision' => $revision, 'groups' => [
            ['group_id' => $group->id, 'registration_ids' => []],
        ]])->assertConflict();
        $this->assertSame([$id], $group->registrations()->pluck('registrations.id')->all());
    }

    public function test_scored_draw_rejects_assignment_changes_and_preserves_results(): void
    {
        [$draw, $category] = $this->workspace();
        $group = $draw->groups()->create(['name' => 'A']);
        $group->registrations()->attach($this->entrant($category)->id);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id]);
        $result = FixtureResult::factory()->create(['fixture_id' => $fixture->id]);
        $this->postJson(route('backend.draw.save-groups', $draw), ['groups' => [
            ['group_id' => $group->id, 'registration_ids' => []],
        ]])->assertUnprocessable();
        $this->assertSame(1, $group->registrations()->count());
        $this->assertDatabaseHas('fixture_results', ['id' => $result->id]);
    }

    public function test_resize_preserves_group_ids_and_requires_a_destination_for_removed_players(): void
    {
        [$draw, $category] = $this->workspace();
        $a = $draw->groups()->create(['name' => 'A']);
        $b = $draw->groups()->create(['name' => 'B']);
        $one = $this->entrant($category)->id;
        $two = $this->entrant($category)->id;
        $a->registrations()->attach($one, ['seed' => 1]);
        $b->registrations()->attach($two, ['seed' => 1]);
        $url = '/backend/draw/'.$draw->id.'/settings';
        $this->postJson($url, ['boxes' => 3])->assertOk();
        $this->assertDatabaseHas('draw_groups', ['id' => $a->id, 'name' => 'A']);
        $this->assertSame([$two], $b->registrations()->pluck('registrations.id')->all());
        $this->postJson($url, ['boxes' => 1])->assertUnprocessable();
        $this->assertSame(3, $draw->groups()->count());
        $this->assertSame(3, (int) $draw->settings()->first()->boxes);
        $this->postJson($url, ['boxes' => 1, 'move_to_group_id' => $a->id])->assertOk();
        $this->assertSame(1, $draw->groups()->count());
        $this->assertSame([$one, $two], $a->registrations()->pluck('registrations.id')->all());
    }

    public function test_resize_cannot_delete_a_group_referenced_by_fixtures(): void
    {
        [$draw] = $this->workspace();
        $a = $draw->groups()->create(['name' => 'A']);
        $b = $draw->groups()->create(['name' => 'B']);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $b->id]);
        $this->postJson('/backend/draw/'.$draw->id.'/settings', ['boxes' => 1, 'move_to_group_id' => $a->id])->assertUnprocessable();
        $this->assertSame(2, $draw->groups()->count());
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id, 'draw_group_id' => $b->id]);
    }

    public function test_play_order_persists_without_renumbering_bracket_matches_or_dropping_filtered_fixtures(): void
    {
        [$draw] = $this->workspace();
        $one = Fixture::factory()->create(['draw_id' => $draw->id, 'match_nr' => 1001]);
        $two = Fixture::factory()->create(['draw_id' => $draw->id, 'match_nr' => 1002]);
        $url = route('backend.draw.save-order', $draw);
        $this->postJson($url, ['order' => [$two->id]])->assertUnprocessable();
        $this->postJson($url, ['order' => [$two->id, $one->id]])->assertOk();
        $this->assertDatabaseHas('fixtures', ['id' => $one->id, 'match_nr' => 1001, 'play_order' => 2]);
        $this->assertDatabaseHas('fixtures', ['id' => $two->id, 'match_nr' => 1002, 'play_order' => 1]);
        $this->getJson(route('api.draws.hub', $draw))->assertOk()->assertJsonPath('oops.0.id', $two->id);
        $draw->update(['locked' => true]);
        $this->postJson($url, ['order' => [$one->id, $two->id]])->assertForbidden();
    }

    public function test_populated_workspace_retains_print_configuration_and_exports_optional_browser_fixture(): void
    {
        [$draw, $category] = $this->workspace();
        $draw->update(['drawName' => 'Draw workspace regression demo']);
        $a = $draw->groups()->create(['name' => 'A']);
        $b = $draw->groups()->create(['name' => 'B']);
        $draw->settings()->create(['boxes' => 2, 'num_sets' => 3]);
        foreach (['Alex Test', 'Blair Test', 'Casey Test', 'Drew Test', 'Ellis Test', 'Frankie Test'] as $index => $name) {
            $player = $this->entrant($category);
            $player->players()->first()->update(['name' => explode(' ', $name)[0], 'surname' => 'Test']);
            if ($index < 2) $a->registrations()->attach($player->id, ['seed' => $index + 1]);
        }
        $response = $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk();
        foreach (['btn-print-fixtures', 'btn-print-matrix', 'btn-print-bracket', 'btn-print-empty-bracket', 'btn-print-combined', 'btn-print-draw-pack', 'preset-selector', 'btn-save-notes', 'autoScheduleBtn'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }
        if (getenv('DRAW_WORKSPACE_SNAPSHOT') === '1') {
            $directory = storage_path('app/testing');
            if (! is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/draw-workspace.html', $response->getContent());
        }
    }

    public function test_stale_fixture_preview_cannot_replace_existing_fixtures(): void
    {
        [$draw] = $this->workspace();
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        $this->postJson(route('backend.draw.regenerate-rr', $draw), ['revision' => 'old-revision'])->assertUnprocessable();
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
        $this->assertSame(1, $draw->drawFixtures()->count());
    }

    public function test_playoff_regeneration_preserves_recorded_playoff_scores(): void
    {
        [$draw] = $this->workspace();
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN']);
        $result = FixtureResult::factory()->create(['fixture_id' => $fixture->id]);
        $this->postJson(route('backend.draw.generate-main-bracket', $draw))->assertUnprocessable();
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
        $this->assertDatabaseHas('fixture_results', ['id' => $result->id]);
    }
}
