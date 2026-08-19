<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\Fixture;
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
