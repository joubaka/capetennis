<?php

namespace Tests\Feature;

use App\Models\{AuditEvent, Player, User};
use App\Support\Audit\AuditWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardLaunchSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function user(?string $role = null): User
    {
        $user = User::factory()->create();
        if ($role) $user->assignRole(Role::findOrCreate($role, 'web'));
        return $user;
    }

    public function test_directory_html_and_both_json_routes_deny_ordinary_and_admin_accounts(): void
    {
        foreach ([null, 'admin'] as $role) {
            $this->actingAs($this->user($role));
            $this->get(route('user.index'))->assertForbidden();
            $this->getJson(route('user.index'))->assertForbidden();
            $this->getJson(route('user.data'))->assertForbidden();
            $this->get(route('backend.dashboard'))->assertOk()->assertDontSee('id="tab-users"', false);
        }
    }

    public function test_super_user_directory_paginates_and_searches_beyond_first_page(): void
    {
        $admin = $this->user('super-user');
        User::factory()->count(30)->create();
        $target = User::factory()->create(['name' => 'ZZZ Beyond first page', 'email' => 'beyond@example.test']);
        $this->actingAs($admin)->getJson(route('user.data', [
            'draw' => 2, 'start' => 25, 'length' => 25, 'order' => [['column' => 0, 'dir' => 'asc']],
        ]))->assertOk()->assertJsonPath('recordsTotal', 32)->assertJsonCount(7, 'data')
            ->assertJsonPath('data.6.id', $target->id);
        $this->getJson(route('user.data', ['search' => ['value' => 'beyond@example.test']]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.id', $target->id);
        $this->get(route('backend.dashboard'))->assertOk()->assertSee('id="tab-users"', false);
    }

    public function test_player_directory_paginates_and_searches_beyond_first_page(): void
    {
        Player::factory()->count(30)->create();
        $target = Player::factory()->create(['name' => 'BeyondPagePlayer']);
        $this->actingAs($this->user('admin'))->getJson(route('player.index', [
            'start' => 25, 'length' => 25, 'order' => [['column' => 0, 'dir' => 'asc']],
        ]))->assertOk()->assertJsonPath('recordsTotal', 31)->assertJsonCount(6, 'data')
            ->assertJsonPath('data.5.id', $target->id);
        $this->getJson(route('player.index', ['search' => ['value' => 'BeyondPagePlayer']]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.id', $target->id);
    }

    public function test_ordinary_and_admin_accounts_cannot_assign_or_remove_roles_even_on_themselves(): void
    {
        Role::findOrCreate('super-user', 'web');
        foreach ([null, 'admin'] as $role) {
            $actor = $this->user($role);
            $this->actingAs($actor)->postJson(route('backend.users.addRole', $actor), ['role' => 'super-user'])->assertForbidden();
            $this->postJson(route('backend.users.removeRole', $actor), ['role' => 'admin'])->assertForbidden();
            $this->assertFalse($actor->fresh()->hasRole('super-user'));
            if ($role) $this->assertTrue($actor->fresh()->hasRole($role));
        }
        $this->assertSame(0, AuditEvent::whereIn('action', ['user.role-assigned', 'user.role-removed'])->count());
    }

    public function test_legacy_get_role_actions_are_gone_and_make_no_mutation(): void
    {
        $admin = $this->user('super-user');
        $target = $this->user('admin');
        foreach (['user.add.role', 'user.remove.role'] as $route) {
            $this->actingAs($admin)->get(route($route, ['id' => $target->id, 'role' => 'super-user']))->assertGone();
        }
        $this->assertSame(['admin'], $target->fresh()->getRoleNames()->all());
        $this->assertSame(0, AuditEvent::whereIn('action', ['user.role-assigned', 'user.role-removed'])->count());
    }

    public function test_super_user_post_changes_roles_with_actual_actor_audit(): void
    {
        $admin = $this->user('super-user');
        $target = $this->user();
        Role::findOrCreate('admin', 'web');
        $this->actingAs($admin)->postJson(route('backend.users.addRole', $target), ['role' => 'admin'])->assertOk();
        $this->assertTrue($target->fresh()->hasRole('admin'));
        $this->postJson(route('backend.users.removeRole', $target), ['role' => 'admin'])->assertOk();
        $this->assertFalse($target->fresh()->hasRole('admin'));
        $this->assertDatabaseHas('audit_events', ['action' => 'user.role-assigned', 'actor_id' => $admin->id, 'subject_id' => $target->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.role-removed', 'actor_id' => $admin->id, 'subject_id' => $target->id]);
        $this->assertSame(2, AuditEvent::where('category', 'security')->count());
    }

    public function test_missing_audit_rolls_back_both_role_changes(): void
    {
        $admin = $this->user('super-user');
        $target = $this->user('admin');
        $this->mock(AuditWriter::class)->shouldReceive('record')->andReturnNull();
        $this->actingAs($admin)->postJson(route('backend.users.addRole', $target), ['role' => 'super-user'])->assertStatus(500);
        $this->assertFalse($target->fresh()->hasRole('super-user'));
        $this->postJson(route('backend.users.removeRole', $target), ['role' => 'admin'])->assertStatus(500);
        $this->assertTrue($target->fresh()->hasRole('admin'));
    }

    public function test_ordinary_and_admin_accounts_cannot_delete_accounts(): void
    {
        $target = $this->user();
        foreach ([null, 'admin'] as $role) {
            $this->actingAs($this->user($role))->deleteJson(route('user.destroy', $target))->assertForbidden();
            $this->assertDatabaseHas('users', ['id' => $target->id]);
        }
        $this->assertSame(0, AuditEvent::where('action', 'user.deleted')->count());
    }

    public function test_ordinary_and_admin_accounts_cannot_create_roles_or_delete_super_user_role(): void
    {
        $protectedRole = Role::findOrCreate('super-user', 'web');
        foreach ([null, 'admin'] as $role) {
            $this->actingAs($this->user($role));
            $auditsBefore = AuditEvent::whereIn('action', ['role.created', 'role.deleted'])->count();
            $this->postJson(route('backend.roles.store'), ['name' => 'unauthorised-role'])->assertForbidden();
            $this->deleteJson(route('backend.roles.destroy', $protectedRole))->assertForbidden();
            $this->assertDatabaseHas('roles', ['id' => $protectedRole->id, 'name' => 'super-user']);
            $this->assertDatabaseMissing('roles', ['name' => 'unauthorised-role']);
            $this->assertSame($auditsBefore, AuditEvent::whereIn('action', ['role.created', 'role.deleted'])->count());
        }
    }

    public function test_super_user_can_create_and_delete_roles_with_audit_and_null_audit_rolls_back(): void
    {
        $admin = $this->user('super-user');
        $this->actingAs($admin)->postJson(route('backend.roles.store'), ['name' => 'reviewed-role'])->assertOk();
        $role = Role::findByName('reviewed-role', 'web');
        $this->assertDatabaseHas('audit_events', ['action' => 'role.created', 'actor_id' => $admin->id, 'subject_id' => $role->id]);
        $this->deleteJson(route('backend.roles.destroy', $role))->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'role.deleted', 'actor_id' => $admin->id, 'subject_id' => $role->id]);

        $retained = Role::findOrCreate('retained-role', 'web');
        $this->mock(AuditWriter::class)->shouldReceive('record')->andReturnNull();
        $this->postJson(route('backend.roles.store'), ['name' => 'rolled-back-role'])->assertStatus(500);
        $this->assertDatabaseMissing('roles', ['name' => 'rolled-back-role']);
        $this->deleteJson(route('backend.roles.destroy', $retained))->assertStatus(500);
        $this->assertDatabaseHas('roles', ['id' => $retained->id]);
    }
}
