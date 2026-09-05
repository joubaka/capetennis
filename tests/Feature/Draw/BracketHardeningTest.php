<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bracket / Playoff Admin Hardening Test Suite
 *
 * Covers:
 *  1.  Guest cannot save bracket score
 *  2.  Guest cannot delete bracket score
 *  3.  Locked draw blocks bracket score save (API)
 *  4.  Published draw allows bracket score save (API)
 *  5.  Locked draw blocks bracket score delete (API)
 *  6.  Locked draw blocks main bracket generation
 *  7.  Locked draw blocks plate bracket generation
 *  8.  Published draw blocks main bracket generation
 *  9.  Admin can fetch bracket data (read-only)
 *  10. saveScore for bracket records audit log
 *  11. deleteScore for bracket records audit log
 *  12. deleteScore rolls back winner and match_status
 *  13. deleteScore clears parent fixture slot
 */
class BracketHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor',   'guard_name' => 'web']);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function adminUser(Draw $draw): User
    {
        $user = User::factory()->create()->assignRole('admin');

        DB::table('event_admins')->insert([
            'event_id' => $draw->event_id,
            'user_id' => $user->id,
        ]);

        return $user;
    }

    private function guestUser(): User
    {
        return User::factory()->create();
    }

    private function makeDraw(array $attrs = []): Draw
    {
        return Draw::factory()->create(array_merge([
            'event_id' => Event::factory()->create()->id,
            'locked'    => false,
            'published' => false,
        ], $attrs));
    }

    private function makeBracketFixture(Draw $draw, array $attrs = []): Fixture
    {
        return Fixture::factory()->create(array_merge([
            'draw_id'  => $draw->id,
            'stage'    => 'MAIN',
            'round'    => 1,
            'match_nr' => 1,
            'match_status' => 0,
        ], $attrs));
    }

    private function scoreUrl(Draw $draw, Fixture $fixture): string
    {
        return "/api/draws/{$draw->id}/fixtures/{$fixture->id}/score";
    }

    // ─────────────────────────────────────────────
    // 1. Guest cannot save bracket score
    // ─────────────────────────────────────────────

    public function test_guest_cannot_save_bracket_score(): void
    {
        $draw    = $this->makeDraw();
        $fixture = $this->makeBracketFixture($draw);

        $this->postJson($this->scoreUrl($draw, $fixture), ['sets' => ['6-3']])
             ->assertStatus(401);
    }

    // ─────────────────────────────────────────────
    // 2. Guest cannot delete bracket score
    // ─────────────────────────────────────────────

    public function test_guest_cannot_delete_bracket_score(): void
    {
        $draw    = $this->makeDraw();
        $fixture = $this->makeBracketFixture($draw);

        $this->deleteJson($this->scoreUrl($draw, $fixture))
             ->assertStatus(401);
    }

    // ─────────────────────────────────────────────
    // 3. Locked draw blocks bracket score save (API)
    // ─────────────────────────────────────────────

    public function test_locked_draw_blocks_bracket_score_save(): void
    {
        $draw    = $this->makeDraw(['locked' => true]);
        $fixture = $this->makeBracketFixture($draw);

        $this->actingAs($this->adminUser($draw))
             ->postJson($this->scoreUrl($draw, $fixture), ['sets' => ['6-3']])
             ->assertStatus(403);
    }

    // ─────────────────────────────────────────────
    // 4. Published draw allows bracket score save (API)
    // ─────────────────────────────────────────────

    public function test_published_draw_allows_bracket_score_save(): void
    {
        $draw    = $this->makeDraw(['published' => true]);
        $fixture = $this->makeBracketFixture($draw, [
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
        ]);

        $this->actingAs($this->adminUser($draw))
             ->postJson($this->scoreUrl($draw, $fixture), ['sets' => ['6-3']])
             ->assertOk()
             ->assertJsonPath('success', true);

        $this->assertDatabaseHas('fixture_results', [
            'fixture_id' => $fixture->id,
            'registration1_score' => 6,
            'registration2_score' => 3,
        ]);
    }

    public function test_published_draw_allows_bracket_score_delete(): void
    {
        $draw = $this->makeDraw(['published' => true]);
        $fixture = $this->makeBracketFixture($draw, [
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
            'match_status' => 1,
        ]);
        FixtureResult::factory()->create(['fixture_id' => $fixture->id]);

        $this->actingAs($this->adminUser($draw))
            ->deleteJson($this->scoreUrl($draw, $fixture))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('fixture_results', ['fixture_id' => $fixture->id]);
    }

    public function test_size_two_named_final_is_rendered_as_a_real_match(): void
    {
        $draw = $this->makeDraw(['published' => true]);
        $draw->settings()->create([
            'workflow' => 'round_robin_playoffs',
            'playoff_config' => [[
                'name' => '1st/2nd (#1 vs #2)',
                'slug' => 'main',
                'size' => 2,
                'positions' => [1, 2],
                'enabled' => true,
            ]],
        ]);
        $fixture = $this->makeBracketFixture($draw, [
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
            'playoff_type' => '1st/2nd (#1 vs #2)',
            'position' => null,
        ]);

        $bracket = (new \App\Services\DynamicBracketEngine($draw->fresh()))->build();

        $this->assertSame($fixture->id, $bracket['brackets'][0]['rounds'][1][0]['fx']->id);
    }

    // ─────────────────────────────────────────────
    // 5. Locked draw blocks bracket score delete (API)
    // ─────────────────────────────────────────────

    public function test_locked_draw_blocks_bracket_score_delete(): void
    {
        $draw    = $this->makeDraw(['locked' => true]);
        $fixture = $this->makeBracketFixture($draw);

        $this->actingAs($this->adminUser($draw))
             ->deleteJson($this->scoreUrl($draw, $fixture))
             ->assertStatus(403);
    }

    // ─────────────────────────────────────────────
    // 6. Locked draw blocks main bracket generation
    // ─────────────────────────────────────────────

    public function test_locked_draw_blocks_main_bracket_generation(): void
    {
        $draw = $this->makeDraw(['locked' => true]);

        $this->actingAs($this->adminUser($draw))
             ->postJson("/backend/draw/{$draw->id}/generate-main-bracket")
             ->assertStatus(403)
             ->assertJson(['success' => false]);
    }

    // ─────────────────────────────────────────────
    // 7. Locked draw blocks plate bracket generation
    // ─────────────────────────────────────────────

    public function test_locked_draw_blocks_plate_bracket_generation(): void
    {
        $draw = $this->makeDraw(['locked' => true]);

        $this->actingAs($this->adminUser($draw))
             ->postJson("/backend/draw/{$draw->id}/generate-second-third-bracket")
             ->assertStatus(403)
             ->assertJson(['success' => false]);
    }

    // ─────────────────────────────────────────────
    // 8. Published draw blocks main bracket generation
    // ─────────────────────────────────────────────

    public function test_published_draw_blocks_main_bracket_generation(): void
    {
        $draw = $this->makeDraw(['published' => true]);

        $this->actingAs($this->adminUser($draw))
             ->postJson("/backend/draw/{$draw->id}/generate-main-bracket")
             ->assertStatus(403)
             ->assertJson(['success' => false]);
    }

    // ─────────────────────────────────────────────
    // 9. Admin can fetch bracket data (read-only)
    // ─────────────────────────────────────────────

    public function test_admin_can_fetch_bracket_data(): void
    {
        $draw = $this->makeDraw();

        $this->actingAs($this->adminUser($draw))
             ->getJson("/api/draws/{$draw->id}/brackets")
             ->assertOk()
             ->assertJsonStructure(['success', 'stages']);
    }

    // ─────────────────────────────────────────────
    // 10. saveScore for bracket records audit log
    // ─────────────────────────────────────────────

    public function test_bracket_save_score_creates_audit_log(): void
    {
        $draw    = $this->makeDraw();
        $fixture = $this->makeBracketFixture($draw);

        $this->actingAs($this->adminUser($draw))
             ->postJson($this->scoreUrl($draw, $fixture), ['sets' => ['6-3', '6-4']]);

        $this->assertDatabaseHas('draw_audit_logs', [
            'draw_id'    => $draw->id,
            'action'     => 'score_saved',
            'fixture_id' => $fixture->id,
        ]);
    }

    // ─────────────────────────────────────────────
    // 11. deleteScore for bracket records audit log
    // ─────────────────────────────────────────────

    public function test_bracket_delete_score_creates_audit_log(): void
    {
        $draw    = $this->makeDraw();
        $fixture = $this->makeBracketFixture($draw, ['match_status' => 1]);

        $this->actingAs($this->adminUser($draw))
             ->deleteJson($this->scoreUrl($draw, $fixture));

        $this->assertDatabaseHas('draw_audit_logs', [
            'draw_id'    => $draw->id,
            'action'     => 'score_deleted',
            'fixture_id' => $fixture->id,
        ]);
    }

    // ─────────────────────────────────────────────
    // 12. deleteScore rolls back winner and match_status
    // ─────────────────────────────────────────────

    public function test_bracket_delete_score_rolls_back_winner(): void
    {
        $draw    = $this->makeDraw();
        $fixture = $this->makeBracketFixture($draw, [
            'match_status'        => 1,
            'winner_registration' => 99,
        ]);

        FixtureResult::factory()->create(['fixture_id' => $fixture->id]);

        $this->actingAs($this->adminUser($draw))
             ->deleteJson($this->scoreUrl($draw, $fixture))
             ->assertOk()
             ->assertJson(['success' => true]);

        $fixture->refresh();
        $this->assertNull($fixture->winner_registration);
        $this->assertEquals(0, $fixture->match_status);
        $this->assertDatabaseMissing('fixture_results', ['fixture_id' => $fixture->id]);
    }

    // ─────────────────────────────────────────────
    // 13. deleteScore clears parent fixture slot
    // ─────────────────────────────────────────────

    public function test_bracket_delete_score_clears_parent_slot(): void
    {
        $draw   = $this->makeDraw();
        $parent = $this->makeBracketFixture($draw, ['round' => 2, 'match_nr' => 1]);

        $fixture = $this->makeBracketFixture($draw, [
            'round'               => 1,
            'match_nr'            => 1,
            'parent_fixture_id'   => $parent->id,
            'match_status'        => 1,
            'winner_registration' => 99,
        ]);

        $parent->registration1_id = 99;
        $parent->save();

        FixtureResult::factory()->create(['fixture_id' => $fixture->id]);

        $this->actingAs($this->adminUser($draw))
             ->deleteJson($this->scoreUrl($draw, $fixture))
             ->assertOk();

        $parent->refresh();
        $this->assertNull($parent->registration1_id);
    }
}
