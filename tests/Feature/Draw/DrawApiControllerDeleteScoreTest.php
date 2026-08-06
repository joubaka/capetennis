<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * DrawApiController::deleteScore published/locked guard tests.
 */
class DrawApiControllerDeleteScoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
    }

    private function adminUser(Draw $draw): User
    {
        $user = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $draw->event_id, 'user_id' => $user->id]);

        return $user;
    }

    private function makeDraw(array $attrs = []): Draw
    {
        return Draw::factory()->create(array_merge([
            'event_id' => Event::factory()->create()->id,
            'locked'    => false,
            'published' => false,
        ], $attrs));
    }

    private function makeFixture(Draw $draw): Fixture
    {
        return Fixture::factory()->create([
            'draw_id'  => $draw->id,
            'stage'    => 'RR',
            'round'    => 1,
            'match_nr' => 1,
        ]);
    }

    private function deleteScoreRoute(Draw $draw, Fixture $fixture): string
    {
        return route('api.draws.fixtures.score.delete', ['draw' => $draw, 'fixture' => $fixture]);
    }

    // ─────────────────────────────────────────────
    // 1. Published draw ALLOWS score delete for round robin
    // ─────────────────────────────────────────────

    public function test_published_draw_allows_api_score_delete_for_round_robin(): void
    {
        $draw    = $this->makeDraw(['published' => true]);
        $fixture = $this->makeFixture($draw);

        $response = $this->actingAs($this->adminUser($draw))
            ->deleteJson($this->deleteScoreRoute($draw, $fixture));

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ─────────────────────────────────────────────
    // 2. Locked draw blocks score delete
    // ─────────────────────────────────────────────

    public function test_locked_draw_blocks_api_score_delete(): void
    {
        $draw    = $this->makeDraw(['locked' => true]);
        $fixture = $this->makeFixture($draw);

        $response = $this->actingAs($this->adminUser($draw))
            ->deleteJson($this->deleteScoreRoute($draw, $fixture));

        $response->assertForbidden();
    }

    // ─────────────────────────────────────────────
    // 3. Mutable draw allows score delete
    // ─────────────────────────────────────────────

    public function test_mutable_draw_allows_api_score_delete(): void
    {
        $draw    = $this->makeDraw(['locked' => false, 'published' => false]);
        $fixture = $this->makeFixture($draw);

        // Seed a result so rollback has something to clear
        FixtureResult::factory()->create([
            'fixture_id'           => $fixture->id,
            'set_nr'               => 1,
            'registration1_score'  => 6,
            'registration2_score'  => 3,
            'winner_registration'  => $fixture->registration1_id,
            'loser_registration'   => $fixture->registration2_id,
        ]);

        $response = $this->actingAs($this->adminUser($draw))
            ->deleteJson($this->deleteScoreRoute($draw, $fixture));

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
