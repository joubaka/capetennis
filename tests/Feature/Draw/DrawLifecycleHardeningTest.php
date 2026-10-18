<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use App\Models\TeamFixture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawLifecycleHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function adminFor(Event $event): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        return $user;
    }

    public function test_deleting_draft_removes_individual_and_team_fixture_graphs(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => 0, 'published' => 0]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        $teamFixture = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1]);
        DB::table('fixture_results')->insert([
            'fixture_id' => $fixture->id, 'set_nr' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('team_fixture_players')->insert([
            'team_fixture_id' => $teamFixture->id, 'team1_id' => null, 'team2_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('team_fixture_results')->insert([
            'team_fixture_id' => $teamFixture->id, 'set_nr' => 1,
            'team1_score' => 6, 'team2_score' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->adminFor($event))
            ->deleteJson(route('draws.destroy', $draw->id))
            ->assertOk()
            ->assertJsonPath('individual_fixtures_deleted', 1)
            ->assertJsonPath('team_fixtures_deleted', 1);

        $this->assertDatabaseMissing('draws', ['id' => $draw->id]);
        $this->assertDatabaseMissing('fixtures', ['id' => $fixture->id]);
        $this->assertDatabaseMissing('team_fixtures', ['id' => $teamFixture->id]);
        $this->assertDatabaseMissing('fixture_results', ['fixture_id' => $fixture->id]);
        $this->assertDatabaseMissing('team_fixture_players', ['team_fixture_id' => $teamFixture->id]);
        $this->assertDatabaseMissing('team_fixture_results', ['team_fixture_id' => $teamFixture->id]);
    }

    public function test_published_or_locked_draw_cannot_be_deleted(): void
    {
        $event = Event::factory()->create();
        $admin = $this->adminFor($event);

        foreach ([['published' => 1, 'locked' => 0], ['published' => 0, 'locked' => 1]] as $state) {
            $draw = Draw::factory()->create(['event_id' => $event->id] + $state);
            $this->actingAs($admin)->deleteJson(route('draws.destroy', $draw->id))->assertForbidden();
            $this->assertDatabaseHas('draws', ['id' => $draw->id]);
        }
    }

    public function test_delete_removes_only_target_draw_fixtures_results_and_schedule_and_retains_audit(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        $schedule = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id]);
        DB::table('fixture_results')->insert(['fixture_id' => $fixture->id, 'set_nr' => 1]);

        $otherDraw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $otherFixture = Fixture::factory()->create(['draw_id' => $otherDraw->id]);
        $otherSchedule = OrderOfPlay::create(['draw_id' => $otherDraw->id, 'fixture_id' => $otherFixture->id]);
        DB::table('fixture_results')->insert(['fixture_id' => $otherFixture->id, 'set_nr' => 1]);
        $admin = $this->adminFor($event);

        $this->actingAs($admin)->deleteJson(route('draws.destroy', $otherDraw))
            ->assertForbidden();
        $this->deleteJson(route('draws.destroy', $draw))->assertOk()
            ->assertJsonPath('individual_fixtures_deleted', 1);
        $this->deleteJson(route('draws.destroy', $draw))->assertNotFound();

        $this->assertDatabaseMissing('order_of_plays', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('fixture_results', ['fixture_id' => $fixture->id]);
        $this->assertDatabaseHas('draws', ['id' => $otherDraw->id]);
        $this->assertDatabaseHas('fixtures', ['id' => $otherFixture->id]);
        $this->assertDatabaseHas('order_of_plays', ['id' => $otherSchedule->id]);
        $this->assertDatabaseHas('fixture_results', ['fixture_id' => $otherFixture->id]);
        $this->assertDatabaseCount('draws', 1);
        $this->assertDatabaseCount('fixtures', 1);
        $this->assertDatabaseCount('fixture_results', 1);
        $this->assertDatabaseCount('order_of_plays', 1);
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'deleted', 'user_id' => $admin->id]);
    }

    public function test_empty_draw_cannot_be_locked(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => 0]);

        $this->actingAs($this->adminFor($event))
            ->postJson(route('draw.lock', $draw))
            ->assertUnprocessable();

        $this->assertEquals(0, $draw->fresh()->locked);
    }
}
