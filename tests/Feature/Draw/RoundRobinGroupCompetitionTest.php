<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, FixtureResult, OrderOfPlay, Registration, User, Venue};
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoundRobinGroupCompetitionTest extends TestCase
{
    use RefreshDatabase;

    private function draw(): Draw
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create(['eventType' => 6]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'locked' => false, 'oop_published' => false]);
        $draw->settings()->create(['workflow' => 'round_robin', 'num_sets' => 1, 'boxes' => 2]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        return $draw;
    }

    private function group(Draw $draw, string $name): Fixture
    {
        $group = $draw->groups()->create(['name' => $name]);
        $ids = Registration::factory()->count(2)->create()->pluck('id');
        foreach ($ids as $seed => $id) $group->registrations()->attach($id, ['seed' => $seed + 1]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR', 'registration1_id' => $ids[0], 'registration2_id' => $ids[1], 'winner_registration' => $ids[0]]);
        FixtureResult::factory()->create(['fixture_id' => $fixture->id, 'registration1_score' => 6, 'registration2_score' => 2, 'set_nr' => 1]);
        return $fixture;
    }

    public function test_competition_choice_preserves_groups_fixtures_results_and_schedule(): void
    {
        $draw = $this->draw();
        $fixture = $this->group($draw, 'A');
        $this->group($draw, 'B');
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => Venue::factory()->create()->id, 'court' => '1', 'time' => '2026-11-01 09:00:00']);
        $before = [DB::table('draw_groups')->get()->toJson(), DB::table('draw_group_registrations')->get()->toJson(), Fixture::all()->toJson(), FixtureResult::all()->toJson(), DB::table('order_of_plays')->get()->toJson()];
        foreach (['round_robin_playoffs', 'round_robin'] as $workflow) {
            $this->post(route('draw.setup.store', $draw), ['workflow' => $workflow])->assertRedirect();
            $this->assertSame($workflow, $draw->fresh()->settings->workflow);
        }
        $this->assertSame($before, [DB::table('draw_groups')->get()->toJson(), DB::table('draw_group_registrations')->get()->toJson(), Fixture::all()->toJson(), FixtureResult::all()->toJson(), DB::table('order_of_plays')->get()->toJson()]);
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN']);
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])->assertStatus(409);
        $this->assertSame('round_robin', $draw->fresh()->settings->workflow);
    }

    public function test_lifecycle_and_event_authorization_protect_competition_option(): void
    {
        $draw = $this->draw();
        foreach (['locked', 'published', 'oop_published'] as $flag) {
            $draw->update([$flag => true]);
            $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])->assertStatus(409);
            $draw->update([$flag => false]);
        }
        $outsider = User::factory()->create()->assignRole('admin');
        $this->actingAs($outsider)->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])->assertForbidden();
    }

    public function test_each_completed_group_gets_its_own_winner_and_incomplete_groups_do_not(): void
    {
        $draw = $this->draw();
        $a = $this->group($draw, 'A');
        $b = $this->group($draw, 'B');
        $hub = app(DrawService::class)->loadRoundRobinHub($draw->fresh());
        foreach ([$a, $b] as $fixture) {
            $rows = $hub['standings'][$fixture->draw_group_id];
            $this->assertSame($fixture->registration1_id, collect($rows)->firstWhere('group_winner', true)['reg_id']);
        }
        $b->fixtureResults()->delete();
        $hub = app(DrawService::class)->loadRoundRobinHub($draw->fresh());
        $this->assertFalse(collect($hub['standings'][$b->draw_group_id])->contains('group_winner', true));
        $this->assertSame('incomplete', $hub['standings'][$b->draw_group_id][0]['group_result_status']);
    }

    public function test_timed_drawn_group_with_equal_totals_has_no_declared_winner(): void
    {
        $draw = $this->draw();
        $draw->settings()->update(['score_format' => 'timed']);
        $fixture = $this->group($draw, 'A');
        $fixture->fixtureResults()->update(['registration1_score' => 4, 'registration2_score' => 4]);
        $hub = app(DrawService::class)->loadRoundRobinHub($draw->fresh());
        $rows = $hub['standings'][$fixture->draw_group_id];
        $this->assertSame('tied', $rows[0]['group_result_status']);
        $this->assertFalse(collect($rows)->contains('group_winner', true));
    }
}

