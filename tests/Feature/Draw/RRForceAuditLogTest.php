<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Forced-action audit log tests.
 *
 * Proves that DrawAuditLog records the correct forced-override entries
 * and that normal (non-forced) paths do not produce forced log entries.
 *
 * A. Main bracket force logging
 *   A1. Incomplete RR + no force → 422, no forced audit log
 *   A2. Incomplete RR + force:1  → forced audit log created
 *   A3. Forced log payload contains expected context fields
 *
 * B. Second/third bracket force logging
 *   B1. Incomplete RR + no force → 422, no forced audit log
 *   B2. Incomplete RR + force:1  → forced audit log created
 *   B3. Forced log created before generation (even when generation itself fails)
 *
 * C. RR regeneration force logging
 *   C1. Results exist + no force → 422, no forced audit log
 *   C2. Results exist + force:1  → forced audit log created
 *   C3. No results + force:1     → NO forced audit log (nothing was overridden)
 *   C4. Forced log payload contains expected context fields
 *
 * D. Non-forced normal paths do not produce forced log entries
 *   D1. Completed RR normal bracket generation → action is 'bracket_generated', not forced
 *   D2. Regeneration with no results → action is 'rr_regenerated', not forced
 */
class RRForceAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
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

    private function score(Fixture $fixture): FixtureResult
    {
        return FixtureResult::factory()->create([
            'fixture_id'          => $fixture->id,
            'set_nr'              => 1,
            'registration1_score' => 6,
            'registration2_score' => 4,
        ]);
    }

    private function forcedLogExists(Draw $draw, string $action): bool
    {
        return DrawAuditLog::where('draw_id', $draw->id)
            ->where('action', $action)
            ->exists();
    }

    private function getForcedLog(Draw $draw, string $action): ?DrawAuditLog
    {
        return DrawAuditLog::where('draw_id', $draw->id)
            ->where('action', $action)
            ->first();
    }

    // ═════════════════════════════════════════════════════════════════
    // A. Main bracket force logging
    // ═════════════════════════════════════════════════════════════════

    // A1. Incomplete RR + no force → 422, no forced audit log
    public function test_main_bracket_no_force_returns_422_and_no_forced_log(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw); // unscored

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw))
            ->assertStatus(422);

        $this->assertFalse(
            $this->forcedLogExists($draw, 'force_generate_main_bracket'),
            'No forced audit log should be created when force flag was not sent'
        );
    }

    // A2. Incomplete RR + force:1 → forced audit log created
    public function test_main_bracket_force_creates_forced_audit_log(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw); // unscored

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw), ['force' => 1]);

        $this->assertTrue(
            $this->forcedLogExists($draw, 'force_generate_main_bracket'),
            'A forced audit log entry must be created when force=1'
        );
    }

    // A3. Forced log payload contains expected context fields
    public function test_main_bracket_force_log_payload_contains_context(): void
    {
        $draw = $this->draw();
        $f1   = $this->rrFixture($draw);
        $f2   = $this->rrFixture($draw);
        $this->score($f1); // 1 of 2 scored

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw), ['force' => 1]);

        $log = $this->getForcedLog($draw, 'force_generate_main_bracket');
        $this->assertNotNull($log, 'Forced log entry should exist');

        $payload = $log->payload;
        $this->assertArrayHasKey('force',          $payload);
        $this->assertArrayHasKey('rr_total',       $payload);
        $this->assertArrayHasKey('rr_played',      $payload);
        $this->assertArrayHasKey('draw_locked',    $payload);
        $this->assertArrayHasKey('draw_published', $payload);

        $this->assertTrue($payload['force']);
        $this->assertSame(2, $payload['rr_total']);
        $this->assertSame(1, $payload['rr_played']);
        $this->assertFalse($payload['draw_locked']);
        $this->assertFalse($payload['draw_published']);
    }

    // A4. User ID is captured on the forced log
    public function test_main_bracket_force_log_records_user_id(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw);
        $user = $this->admin();

        $this->actingAs($user)
            ->postJson(route('backend.draw.generate-main-bracket', $draw), ['force' => 1]);

        $log = $this->getForcedLog($draw, 'force_generate_main_bracket');
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
    }

    // ═════════════════════════════════════════════════════════════════
    // B. Second/third bracket force logging
    // ═════════════════════════════════════════════════════════════════

    // B1. Incomplete RR + no force → 422, no forced audit log
    public function test_second_third_bracket_no_force_returns_422_and_no_forced_log(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw);

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-second-third-bracket', $draw))
            ->assertStatus(422);

        $this->assertFalse(
            $this->forcedLogExists($draw, 'force_generate_second_third_bracket'),
            'No forced audit log should be created when force flag was not sent'
        );
    }

    // B2. Incomplete RR + force:1 → forced audit log created
    public function test_second_third_bracket_force_creates_forced_audit_log(): void
    {
        $draw = $this->draw();
        $this->rrFixture($draw);

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-second-third-bracket', $draw), ['force' => 1]);

        $this->assertTrue(
            $this->forcedLogExists($draw, 'force_generate_second_third_bracket'),
            'A forced audit log entry must be created when force=1'
        );
    }

    // B3. Forced log is created BEFORE generation runs, so it exists even
    //     when generation itself fails (e.g. missing seed data).
    //     This is intentional: the audit records the intent/override, not just success.
    public function test_second_third_bracket_force_log_exists_even_when_generation_fails(): void
    {
        // Draw with no groups/seeds — generation will throw inside the transaction,
        // but the audit log is written before that point.
        $draw = $this->draw();
        $this->rrFixture($draw); // has fixture but no groups/standings

        $response = $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-second-third-bracket', $draw), ['force' => 1]);

        // Generation may return any non-422-guard status (422 from catch, 500, etc.)
        // The key assertion is that the forced audit log was created regardless.
        $this->assertTrue(
            $this->forcedLogExists($draw, 'force_generate_second_third_bracket'),
            'Forced audit log must be written before the generation attempt, so it exists even on failure'
        );
    }

    // B4. Forced log payload contains expected context fields
    public function test_second_third_bracket_force_log_payload_contains_context(): void
    {
        $draw = $this->draw();
        $f    = $this->rrFixture($draw);
        // leave unscored — rr_played should be 0

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-second-third-bracket', $draw), ['force' => 1]);

        $log     = $this->getForcedLog($draw, 'force_generate_second_third_bracket');
        $payload = $log->payload;

        $this->assertArrayHasKey('force',          $payload);
        $this->assertArrayHasKey('rr_total',       $payload);
        $this->assertArrayHasKey('rr_played',      $payload);
        $this->assertArrayHasKey('draw_locked',    $payload);
        $this->assertArrayHasKey('draw_published', $payload);

        $this->assertTrue($payload['force']);
        $this->assertSame(1, $payload['rr_total']);
        $this->assertSame(0, $payload['rr_played']);
    }

    // ═════════════════════════════════════════════════════════════════
    // C. RR regeneration force logging
    // ═════════════════════════════════════════════════════════════════

    // C1. Results exist + no force → 422, no forced audit log
    public function test_regenerate_rr_no_force_returns_422_and_no_forced_log(): void
    {
        $draw = $this->draw();
        $f    = $this->rrFixture($draw);
        $this->score($f);

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw))
            ->assertStatus(422);

        $this->assertFalse(
            $this->forcedLogExists($draw, 'force_regenerate_round_robin'),
            'No forced audit log when force flag was not sent'
        );
    }

    // C2. Results exist + force:1 → forced audit log created
    public function test_regenerate_rr_force_creates_forced_audit_log_when_results_exist(): void
    {
        $draw  = $this->draw();
        $group = $draw->groups()->create(['name' => 'A']);
        $f     = $this->rrFixture($draw);
        $this->score($f);

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw), ['force' => 1]);

        $this->assertTrue(
            $this->forcedLogExists($draw, 'force_regenerate_round_robin'),
            'A forced audit log entry must be created when force=1 and results existed'
        );
    }

    // C3. No results + force:1 → NO forced audit log (nothing to override)
    public function test_regenerate_rr_force_with_no_results_does_not_create_forced_log(): void
    {
        $draw  = $this->draw();
        $group = $draw->groups()->create(['name' => 'A']);
        $this->rrFixture($draw); // fixture with NO score

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw), ['force' => 1]);

        $this->assertFalse(
            $this->forcedLogExists($draw, 'force_regenerate_round_robin'),
            'No forced audit log when there were no results to override'
        );
    }

    // C4. Forced regeneration log payload contains expected context fields
    public function test_regenerate_rr_force_log_payload_contains_context(): void
    {
        $draw  = $this->draw();
        $group = $draw->groups()->create(['name' => 'A']);
        $f1    = $this->rrFixture($draw);
        $f2    = $this->rrFixture($draw);
        $this->score($f1);
        $this->score($f2);

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw), ['force' => 1]);

        $log     = $this->getForcedLog($draw, 'force_regenerate_round_robin');
        $this->assertNotNull($log);
        $payload = $log->payload;

        $this->assertArrayHasKey('force',            $payload);
        $this->assertArrayHasKey('existing_results', $payload);
        $this->assertArrayHasKey('rr_fixtures',      $payload);
        $this->assertArrayHasKey('draw_locked',      $payload);
        $this->assertArrayHasKey('draw_published',   $payload);

        $this->assertTrue($payload['force']);
        $this->assertSame(2, $payload['existing_results']);
        $this->assertSame(2, $payload['rr_fixtures']);
        $this->assertFalse($payload['draw_locked']);
        $this->assertFalse($payload['draw_published']);
    }

    // C5. User ID is captured on the forced regeneration log
    public function test_regenerate_rr_force_log_records_user_id(): void
    {
        $draw  = $this->draw();
        $group = $draw->groups()->create(['name' => 'A']);
        $f     = $this->rrFixture($draw);
        $this->score($f);
        $user  = $this->admin();

        $this->actingAs($user)
            ->postJson(route('backend.draw.regenerate-rr', $draw), ['force' => 1]);

        $log = $this->getForcedLog($draw, 'force_regenerate_round_robin');
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
    }

    // ═════════════════════════════════════════════════════════════════
    // D. Non-forced normal paths do not produce forced log entries
    // ═════════════════════════════════════════════════════════════════

    // D1. Completed RR bracket generation (no force) → only 'bracket_generated', no forced entry
    public function test_normal_completed_bracket_generation_does_not_create_forced_log(): void
    {
        $draw = $this->draw();
        $f    = $this->rrFixture($draw);
        $this->score($f); // fully scored

        // Generation may fail for other reasons (no event data), but the forced
        // audit log must never be written without force=1.
        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw));
        // no ['force' => 1]

        $this->assertFalse(
            $this->forcedLogExists($draw, 'force_generate_main_bracket'),
            'Normal generation (no force flag) must never write a forced audit log'
        );
    }

    // D2. Regeneration with no results (no force) → only 'rr_regenerated', no forced entry
    public function test_normal_regeneration_with_no_results_does_not_create_forced_log(): void
    {
        $draw  = $this->draw();
        $group = $draw->groups()->create(['name' => 'A']);
        $this->rrFixture($draw); // no score

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.regenerate-rr', $draw));
        // no ['force' => 1]

        $this->assertFalse(
            $this->forcedLogExists($draw, 'force_regenerate_round_robin'),
            'Normal regeneration without results must not write a forced audit log'
        );
    }

    // D3. force:1 on locked draw → forced log NOT created (blocked before logging)
    public function test_main_bracket_force_does_not_log_when_draw_is_locked(): void
    {
        $draw = $this->draw(['locked' => true]);
        $this->rrFixture($draw);

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw), ['force' => 1])
            ->assertStatus(403);

        $this->assertFalse(
            $this->forcedLogExists($draw, 'force_generate_main_bracket'),
            'Locked draw blocks execution before audit logging — no forced log should exist'
        );
    }

    // D4. force:1 on published draw → forced log NOT created (blocked before logging)
    public function test_main_bracket_force_does_not_log_when_draw_is_published(): void
    {
        $draw = $this->draw(['published' => true]);
        $this->rrFixture($draw);

        $this->actingAs($this->admin())
            ->postJson(route('backend.draw.generate-main-bracket', $draw), ['force' => 1])
            ->assertStatus(403);

        $this->assertFalse(
            $this->forcedLogExists($draw, 'force_generate_main_bracket'),
            'Published draw blocks execution before audit logging — no forced log should exist'
        );
    }
}
