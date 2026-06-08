<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Round Robin Workflow Guard Tests
 *
 * Proves the safety guards added in the recent RR workflow implementation:
 *
 * B. Bracket generation guard (generateMainBracket, generateSecondThirdBracket, generatePlayoffBrackets proxy)
 *   B1.  Incomplete RR → 422
 *   B2.  422 response body contains a useful message
 *   B3.  422 response does NOT include force override by default
 *   B4.  force=1 bypasses RR-complete check and returns success path
 *   B5.  Locked draw still blocked even with force=1
 *   B6.  Published draw still blocked even with force=1
 *   B7.  Unauthenticated blocked
 *   B8.  Proxy route (generate-playoffs) inherits same 422 guard
 *   B9.  All RR scored → no 422, reaches generation logic
 *   B10. generateSecondThirdBracket — same 422 guard
 *   B11. generateSecondThirdBracket — force=1 bypasses guard
 *
 * C. Group regeneration guard (regenerateRR)
 *   C1.  No results → regeneration succeeds (200/ok)
 *   C2.  Results exist → 422
 *   C3.  422 body includes confirm: true
 *   C4.  422 body includes a descriptive message
 *   C5.  force=1 allows regeneration when results exist
 *   C6.  force=1 does not bypass auth (unauthenticated still blocked)
 *   C7.  force=1 does not bypass locked draw
 *
 * E. Standings delegation regression
 *   E1.  draw-status endpoint returns expected keys
 *   E2.  draw-status reflects fixture/score state correctly
 */
class RRWorkflowGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor',   'guard_name' => 'web']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create()->assignRole('admin');
    }

    private function draw(array $attrs = []): Draw
    {
        return Draw::factory()->create(array_merge([
            'locked'    => false,
            'published' => false,
        ], $attrs));
    }

    private function rrFixture(Draw $draw): Fixture
    {
        return Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage'   => 'RR',
        ]);
    }

    private function scoreFixture(Fixture $fixture): FixtureResult
    {
        return FixtureResult::factory()->create([
            'fixture_id'          => $fixture->id,
            'set_nr'              => 1,
            'registration1_score' => 6,
            'registration2_score' => 4,
        ]);
    }

    // ═════════════════════════════════════════════════════════════════
    // B. Bracket generation guards
    // ═════════════════════════════════════════════════════════════════

    // B1. Incomplete RR → 422
    public function test_generate_main_bracket_returns_422_when_rr_incomplete(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw); // unscored

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw));

        $response->assertStatus(422);
    }

    // B2. 422 body has a useful message
    public function test_generate_main_bracket_422_body_has_message(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw));

        $response->assertStatus(422)
                 ->assertJsonStructure(['success', 'message'])
                 ->assertJsonPath('success', false);

        $this->assertNotEmpty($response->json('message'));
    }

    // B3. Without force, 422 contains no implicit force approval
    public function test_generate_main_bracket_422_does_not_include_force_approval(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw));

        $response->assertStatus(422)
                 ->assertJsonMissing(['force' => true]);
    }

    // B4. force=1 bypasses RR-complete check (draw has no RR — empty fixtures
    //     means isRRComplete = false, but force should skip that check)
    public function test_generate_main_bracket_force_bypasses_rr_completeness_check(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw); // unscored — without force, would 422

        // With force=1 the completeness guard is skipped.
        // The request will proceed to auth/generation logic; we cannot easily
        // assert 200 in isolation (generation needs real event data), so we
        // confirm it does NOT return 422.
        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw), ['force' => 1]);

        $this->assertNotEquals(422, $response->status(),
            'force=1 should bypass the RR-completeness 422 guard');
    }

    // B5. Locked draw still blocked even with force=1
    public function test_generate_main_bracket_locked_draw_blocked_with_force(): void
    {
        $draw = $this->draw(['locked' => true]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw), ['force' => 1]);

        $response->assertStatus(403);
    }

    // B6. Published draw still blocked even with force=1
    public function test_generate_main_bracket_published_draw_blocked_with_force(): void
    {
        $draw = $this->draw(['published' => true]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw), ['force' => 1]);

        $response->assertStatus(403);
    }

    // B7. Unauthenticated blocked
    public function test_generate_main_bracket_unauthenticated_blocked(): void
    {
        $draw = $this->draw();

        $response = $this->postJson(route('backend.draw.generate-main-bracket', $draw));

        $response->assertUnauthorized();
    }

    // B8. Proxy route (generate-playoffs) inherits 422 guard
    public function test_generate_playoffs_proxy_returns_422_when_rr_incomplete(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw); // unscored

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-playoffs', $draw));

        $response->assertStatus(422);
    }

    // B9. All RR scored → no 422 from completeness guard
    public function test_generate_main_bracket_no_422_when_rr_complete(): void
    {
        $draw = $this->draw();
        $f    = $this->rrFixture($draw);
        $this->scoreFixture($f);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw));

        // Should not be 422 — generation logic may fail for other reasons
        // (missing event data etc.), but the completeness guard is satisfied
        $this->assertNotEquals(422, $response->status(),
            'Completeness guard should not block a fully-scored draw');
    }

    // B10. generateSecondThirdBracket — same 422 guard
    public function test_generate_second_third_bracket_422_when_rr_incomplete(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-second-third-bracket', $draw));

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }

    // B11. generateSecondThirdBracket — force=1 bypasses completeness guard
    public function test_generate_second_third_bracket_force_bypasses_rr_completeness_check(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw); // unscored

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-second-third-bracket', $draw), ['force' => 1]);

        // The completeness guard message is very specific. Any other 422 is from
        // the generation logic itself (acceptable — the guard was bypassed).
        if ($response->status() === 422) {
            $this->assertStringNotContainsStringIgnoringCase(
                'not all round robin matches',
                $response->json('message') ?? '',
                'force=1 must bypass the RR-completeness guard — the 422 must come from generation, not the guard'
            );
        }

        // If not 422, the request got past the guard (also acceptable)
        $this->assertTrue(
            $response->status() !== 422
            || !str_contains(strtolower($response->json('message') ?? ''), 'not all round robin'),
            'force=1 should bypass the RR-completeness guard on second/third bracket'
        );
    }

    // ═════════════════════════════════════════════════════════════════
    // C. Group regeneration guard
    // ═════════════════════════════════════════════════════════════════

    // C1. No results → regeneration succeeds
    public function test_regenerate_rr_succeeds_when_no_results_exist(): void
    {
        $draw  = $this->draw();
        $group = $draw->groups()->create(['name' => 'A']);
        $this->rrFixture($draw); // fixture but NO score

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw));

        // May succeed or hit generation issues, but must NOT be 422
        $this->assertNotEquals(422, $response->status(),
            'Regeneration without results must not return 422');
    }

    // C2. Results exist → 422
    public function test_regenerate_rr_returns_422_when_results_exist(): void
    {
        $draw = $this->draw();
        $f    = $this->rrFixture($draw);
        $this->scoreFixture($f);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw));

        $response->assertStatus(422);
    }

    // C3. 422 body includes confirm: true
    public function test_regenerate_rr_422_includes_confirm_flag(): void
    {
        $draw = $this->draw();
        $f    = $this->rrFixture($draw);
        $this->scoreFixture($f);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw));

        $response->assertStatus(422)
                 ->assertJsonPath('confirm', true);
    }

    // C4. 422 body includes a descriptive message
    public function test_regenerate_rr_422_includes_descriptive_message(): void
    {
        $draw = $this->draw();
        $f    = $this->rrFixture($draw);
        $this->scoreFixture($f);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw));

        $response->assertStatus(422)
                 ->assertJsonStructure(['message']);

        $this->assertNotEmpty($response->json('message'));
    }

    // C5. force=1 allows regeneration when results exist
    public function test_regenerate_rr_force_bypasses_results_guard(): void
    {
        $draw  = $this->draw();
        $group = $draw->groups()->create(['name' => 'A']);
        $f     = $this->rrFixture($draw);
        $this->scoreFixture($f);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw), ['force' => 1]);

        $this->assertNotEquals(422, $response->status(),
            'force=1 must bypass the results-exist 422 guard');
    }

    // C6. force=1 does not bypass unauthenticated
    public function test_regenerate_rr_force_does_not_bypass_auth(): void
    {
        $draw = $this->draw();
        $f    = $this->rrFixture($draw);
        $this->scoreFixture($f);

        $response = $this->postJson(
            route('backend.draw.regenerate-rr', $draw), ['force' => 1]
        );

        $response->assertUnauthorized();
    }

    // C7. force=1 does not bypass locked draw
    public function test_regenerate_rr_force_does_not_bypass_locked_draw(): void
    {
        $draw = $this->draw(['locked' => true]);
        $f    = $this->rrFixture($draw);
        $this->scoreFixture($f);

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw), ['force' => 1]);

        $response->assertForbidden();
    }

    // ═════════════════════════════════════════════════════════════════
    // E. draw-status endpoint regression (standings path)
    // ═════════════════════════════════════════════════════════════════

    // E1. draw-status returns expected JSON keys
    public function test_draw_status_endpoint_returns_expected_keys(): void
    {
        $draw = $this->draw();

        $response = $this->actingAs($this->admin())
            ->getJson(route('backend.draw.status', $draw));

        $response->assertOk()
                 ->assertJsonStructure([
                     'groups_configured',
                     'fixtures_generated',
                     'rr_total',
                     'rr_played',
                     'rr_complete_pct',
                     'rr_complete',
                     'standings_ready',
                     'brackets_generated',
                     'locked',
                     'published',
                     'warnings',
                 ]);
    }

    // E2. draw-status reflects scoring state correctly
    public function test_draw_status_reflects_scoring_state(): void
    {
        $draw = $this->draw();
        $f1   = $this->rrFixture($draw);
        $f2   = $this->rrFixture($draw);
        $this->scoreFixture($f1); // 1 of 2

        $response = $this->actingAs($this->admin())
            ->getJson(route('backend.draw.status', $draw));

        $response->assertOk()
                 ->assertJsonPath('rr_total',        2)
                 ->assertJsonPath('rr_played',       1)
                 ->assertJsonPath('rr_complete_pct', 50)
                 ->assertJsonPath('rr_complete',     false)
                 ->assertJsonPath('standings_ready', false);
    }

    // E3. draw-status reports complete after all scores
    public function test_draw_status_reports_complete_when_all_scored(): void
    {
        $draw = $this->draw();
        $f1   = $this->rrFixture($draw);
        $f2   = $this->rrFixture($draw);
        $this->scoreFixture($f1);
        $this->scoreFixture($f2);

        $response = $this->actingAs($this->admin())
            ->getJson(route('backend.draw.status', $draw));

        $response->assertOk()
                 ->assertJsonPath('rr_complete',     true)
                 ->assertJsonPath('standings_ready', true)
                 ->assertJsonPath('rr_complete_pct', 100);
    }

    // E4. Unauthenticated cannot access draw-status
    public function test_draw_status_requires_auth(): void
    {
        $draw = $this->draw();

        $response = $this->getJson(route('backend.draw.status', $draw));

        $response->assertUnauthorized();
    }
}
