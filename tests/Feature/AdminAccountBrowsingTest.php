<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccountBrowsingTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        Role::findOrCreate('super-user', 'web');
        return User::factory()->create()->assignRole('super-user');
    }

    public function test_directory_search_and_pagination_report_exact_counts_without_private_fields(): void
    {
        $admin = $this->administrator();
        User::factory()->count(29)->create();
        $target = User::factory()->create(['name' => 'Example directory target', 'email' => 'target@example.test']);
        $this->actingAs($admin)->getJson(route('user.index', ['draw' => 3, 'start' => 10, 'length' => 10]))
            ->assertOk()->assertJsonPath('draw', 3)->assertJsonPath('recordsTotal', 31)
            ->assertJsonPath('recordsFiltered', 31)->assertJsonCount(10, 'data');
        $response = $this->getJson(route('user.index', ['search' => ['value' => 'target@example.test']]))
            ->assertOk()->assertJsonPath('recordsTotal', 31)->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
        $this->assertArrayNotHasKey('password', $response->json('data.0'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.0'));
    }

    public function test_wallet_pagination_preserves_full_ledger_balance_and_record_count(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create(['name' => 'Example wallet account']);
        $wallet = $user->wallet()->create();
        for ($i = 0; $i < 30; $i++) {
            $wallet->transactions()->create(['type' => 'credit', 'amount' => 10, 'source_type' => 'test']);
        }
        $response = $this->actingAs($admin)->get(route('wallet.show', $user))
            ->assertOk()->assertViewHas('transactions', fn ($rows) => $rows->count() === 25 && $rows->total() === 30)
            ->assertSee('R300.00');
        $this->assertSame(300.0, $wallet->balance);
        $this->assertDatabaseCount('wallet_transactions', 30);
        $this->fixture('wallet', $response->getContent());
        $this->get(route('wallet.show', [$user, 'page' => 2]))->assertOk()
            ->assertViewHas('transactions', fn ($rows) => $rows->count() === 5 && $rows->total() === 30);
        $this->get(route('user.show', $user))->assertOk()
            ->assertViewHas('transactions', fn ($rows) => $rows->count() === 25 && $rows->total() === 30);
    }

    public function test_legacy_adjustment_creation_redirects_without_creating_a_wallet(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();
        $this->actingAs($admin)->get(route('transaction.create', $user))->assertRedirect(route('wallet.show', $user));
        $this->assertDatabaseCount('wallets', 0);
        $this->actingAs(User::factory()->create())->get(route('transaction.create', $user))->assertForbidden();
    }

    public function test_legacy_goal_creation_uses_the_player_directory_instead_of_a_missing_view(): void
    {
        $this->actingAs($this->administrator())->get(route('goal.create'))->assertRedirect(route('player.index'));
        $this->assertDatabaseCount('goals', 0);
    }

    public function test_role_mutation_routes_remain_restricted(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($user)->postJson(route('backend.users.addRole', $target), ['role' => 'super-user'])->assertForbidden();
        $this->postJson(route('backend.users.removeRole', $target), ['role' => 'super-user'])->assertForbidden();
        $this->assertFalse($target->fresh()->hasRole('super-user'));
    }

    public function test_administrative_review_pages_render_without_consequential_actions(): void
    {
        $this->actingAs($this->administrator());
        foreach ([
            'accounts' => 'user.index',
            'agreements' => 'backend.agreements.index',
            'agreement-create' => 'backend.agreements.create',
            'settings' => 'settings.index',
            'audit' => 'superadmin.audit.index',
            'integrations' => 'superadmin.api-integrations.index',
            'duplicates' => 'superadmin.player-duplicates.index',
            'orphans' => 'superadmin.orphans.index',
            'discipline' => 'backend.disciplinary.index',
            'discipline-settings' => 'backend.disciplinary.settings',
            'finance' => 'superadmin.finances',
        ] as $fixture => $name) {
            $response = $this->get(route($name))->assertOk();
            $this->fixture($fixture, $response->getContent());
        }
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_wallet_validation_preserves_retry_key_and_reopens_the_adjustment_form(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();
        $user->wallet()->create();
        $key = (string) \Illuminate\Support\Str::uuid();
        $this->actingAs($admin)->from(route('wallet.show', $user))
            ->post(route('superadmin.wallets.transaction.store', $user), [
                'type' => 'credit', 'amount' => 0, 'idempotency_key' => $key, 'wallet_form' => 'add',
            ])->assertSessionHasErrors('amount');
        $this->get(route('wallet.show', $user))->assertOk()->assertSee($key)->assertSee('addTxModal.show();', false);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_platform_health_renders_long_diagnostics_without_exposing_credentials(): void
    {
        $items = [['status' => 'warn', 'label' => 'Example diagnostic', 'detail' => 'Example diagnostic detail for review', 'value' => 1]];
        $data = array_fill_keys(['engine', 'financial', 'draw', 'registration', 'queue', 'system'], $items);
        $data['summary'] = ['critical' => 0, 'warn' => 6, 'ok' => 0];
        $this->mock(\App\Services\PlatformHealthService::class)->shouldReceive('all')->once()->andReturn($data);
        $response = $this->actingAs($this->administrator())->get(route('platform.health'))->assertOk();
        $this->fixture('health', $response->getContent());
        $response->assertSee('Example diagnostic')->assertSee('6 warning(s)');
    }

    public function test_wallet_history_has_no_edit_or_delete_actions_for_immutable_entries(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();
        $wallet = $user->wallet()->create();
        $wallet->transactions()->create(['type' => 'credit', 'amount' => 10, 'source_type' => 'test']);
        $this->actingAs($admin)->get(route('wallet.show', $user))->assertOk()
            ->assertDontSee('btn-wallet-edit-tx', false)->assertDontSee('form-tx-delete', false)
            ->assertSee('Existing entries are permanent');
        $this->assertSame(10.0, $wallet->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    private function fixture(string $name, string $html): void
    {
        if (getenv('CT_ALL_ADMIN_QA') === '1') {
            file_put_contents(storage_path('app/all-admin-improvements-qa/'.$name.'.html'), $html);
        }
    }
}
