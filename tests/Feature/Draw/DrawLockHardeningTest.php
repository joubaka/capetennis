<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\DrawGroup;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Draw lock system — hardening tests.
 *
 * Verifies that EVERY mutation endpoint returns 403 when the draw is locked,
 * and succeeds (2xx) when unlocked.
 */
class DrawLockHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor',   'guard_name' => 'web']);
    }

    private function admin(Draw $draw): User
    {
        if (! $draw->event_id) {
            $draw->update(['event_id' => Event::factory()->create()->id]);
        }

        $user = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $draw->event_id, 'user_id' => $user->id]);

        return $user;
    }

    private function lockedDraw(): Draw
    {
        return Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'locked' => 1, 'published' => 0]);
    }

    private function unlockedDraw(): Draw
    {
        return Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'locked' => 0, 'published' => 0]);
    }

    // ─── saveGroups ──────────────────────────────────────────────────

    public function test_save_groups_blocked_when_locked(): void
    {
        $draw = $this->lockedDraw();
        $group = DrawGroup::factory()->create(['draw_id' => $draw->id]);

        $this->actingAs($this->admin($draw))
            ->postJson(route('backend.draw.save-groups', $draw), [
                'groups' => [['group_id' => $group->id, 'registration_ids' => []]],
            ])
            ->assertForbidden();
    }

    public function test_save_groups_allowed_when_unlocked(): void
    {
        $draw  = $this->unlockedDraw();
        $group = DrawGroup::factory()->create(['draw_id' => $draw->id]);

        $this->actingAs($this->admin($draw))
            ->postJson(route('backend.draw.save-groups', $draw), [
                'groups' => [['group_id' => $group->id, 'registration_ids' => []]],
            ])
            ->assertOk();
    }

    // ─── regenerateRR ────────────────────────────────────────────────

    public function test_regenerate_fixtures_blocked_when_locked(): void
    {
        $draw = $this->lockedDraw();

        $this->actingAs($this->admin($draw))
            ->postJson(route('backend.draw.regenerate-rr', $draw))
            ->assertForbidden();
    }

    public function test_regenerate_fixtures_blocked_when_published(): void
    {
        $draw = Draw::factory()->create(['locked' => 0, 'published' => 1]);

        $this->actingAs($this->admin($draw))
            ->postJson(route('backend.draw.regenerate-rr', $draw))
            ->assertForbidden();
    }

    // ─── saveScore (RoundRobinController) ────────────────────────────

    public function test_save_score_blocked_when_locked(): void
    {
        $draw    = $this->lockedDraw();
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);

        $this->actingAs($this->admin($draw))
            ->postJson(route('backend.roundrobin.score.store', $fixture), [
                'sets' => ['6-3'],
            ])
            ->assertForbidden();
    }

    public function test_save_score_blocked_when_published(): void
    {
        $draw    = Draw::factory()->create(['locked' => 0, 'published' => 1]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);

        $this->actingAs($this->admin($draw))
            ->postJson(route('backend.roundrobin.score.store', $fixture), [
                'sets' => ['6-3'],
            ])
            ->assertForbidden();
    }

    // ─── deleteScore (RoundRobinController) ──────────────────────────

    public function test_delete_score_blocked_when_locked(): void
    {
        $draw    = $this->lockedDraw();
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);

        $this->actingAs($this->admin($draw))
            ->deleteJson(route('backend.roundrobin.score.delete', $fixture))
            ->assertForbidden();
    }

    public function test_delete_score_blocked_when_published(): void
    {
        $draw    = Draw::factory()->create(['locked' => 0, 'published' => 1]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);

        $this->actingAs($this->admin($draw))
            ->deleteJson(route('backend.roundrobin.score.delete', $fixture))
            ->assertForbidden();
    }

    // ─── updateSettings (ManageDrawController) ───────────────────────

    public function test_update_settings_blocked_when_locked(): void
    {
        $draw = $this->lockedDraw();

        $this->actingAs($this->admin($draw))
            ->postJson(route('backend.draw.update-settings', $draw), ['boxes' => 2])
            ->assertForbidden();
    }

    public function test_update_settings_allowed_when_unlocked(): void
    {
        $draw = $this->unlockedDraw();

        $this->actingAs($this->admin($draw))
            ->postJson(route('backend.draw.update-settings', $draw), ['boxes' => 2])
            ->assertOk();
    }

    // ─── updateNotes — allowed even when locked ───────────────────────

    public function test_update_notes_allowed_when_locked(): void
    {
        $draw = $this->lockedDraw();

        $this->actingAs($this->admin($draw))
            ->postJson(route('backend.draw.update-notes', $draw), [
                'notes' => ['general' => 'Test note'],
            ])
            ->assertOk();
    }

    // ─── toggleLock ──────────────────────────────────────────────────

    public function test_toggle_lock_returns_permissions(): void
    {
        $draw = $this->unlockedDraw();

        $response = $this->actingAs($this->admin($draw))
            ->postJson(route('backend.draw.toggle-lock', $draw))
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'locked',
                'permissions' => [
                    'locked',
                    'published',
                    'canEditAssignments',
                    'canGenerateFixtures',
                    'canEditScores',
                    'canDeleteScores',
                ],
            ]);

        $this->assertTrue($response->json('locked'));
        $this->assertFalse($response->json('permissions.canEditAssignments'));
        $this->assertFalse($response->json('permissions.canEditScores'));
    }

    // ─── DrawMutationPolicy unit ──────────────────────────────────────

    public function test_mutation_policy_locked_draw(): void
    {
        $draw = $this->lockedDraw();
        $policy = \App\Services\Draw\DrawMutationPolicy::for($draw);

        $this->assertFalse($policy->canEditAssignments());
        $this->assertFalse($policy->canGenerateFixtures());
        $this->assertFalse($policy->canEditScores());
        $this->assertFalse($policy->canDeleteScores());
        $this->assertFalse($policy->canEditSettings());
        $this->assertTrue($policy->canEditNotes());
        $this->assertTrue($policy->canToggleLock());
    }

    public function test_mutation_policy_unlocked_draw(): void
    {
        $draw = $this->unlockedDraw();
        $policy = \App\Services\Draw\DrawMutationPolicy::for($draw);

        $this->assertTrue($policy->canEditAssignments());
        $this->assertTrue($policy->canGenerateFixtures());
        $this->assertTrue($policy->canEditScores());
        $this->assertTrue($policy->canDeleteScores());
        $this->assertTrue($policy->canEditSettings());
    }

    public function test_mutation_policy_published_draw(): void
    {
        $draw = Draw::factory()->create(['locked' => 0, 'published' => 1]);
        $policy = \App\Services\Draw\DrawMutationPolicy::for($draw);

        $this->assertFalse($policy->canEditAssignments());
        $this->assertFalse($policy->canGenerateFixtures());
        $this->assertFalse($policy->canEditScores());
        $this->assertFalse($policy->canDeleteScores());
        $this->assertTrue($policy->canEditNotes());
    }
}
