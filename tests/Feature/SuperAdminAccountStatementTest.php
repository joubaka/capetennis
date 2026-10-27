<?php

namespace Tests\Feature;

use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminAccountStatementTest extends TestCase
{
    use RefreshDatabase;

    private function superUser(): User
    {
        Role::findOrCreate('super-user', 'web');

        return User::factory()->create()->assignRole('super-user');
    }

    private function payment(User $user, string $reference, float $amount): void
    {
        ClothingOrder::create(['user_id' => $user->id, 'event_id' => Event::factory()->create()->id,
            'player_id' => Player::factory()->create()->id, 'pay_status' => 1, 'payfast_paid' => true,
            'paid_at' => '2025-03-01', 'amount_paid' => $amount, 'total' => $amount,
            'payfast_pf_payment_id' => $reference]);
    }

    public function test_search_and_all_target_formats_require_super_user_even_for_other_staff_roles(): void
    {
        $target = User::factory()->create();
        $this->get(route('superadmin.account-statements.index'))->assertRedirect();
        foreach ([null, 'admin', 'convenor'] as $role) {
            $actor = User::factory()->create();
            if ($role) {
                Role::findOrCreate($role, 'web');
                $actor->assignRole($role);
            }
            $this->actingAs($actor)->get(route('superadmin.account-statements.index'))->assertForbidden();
            foreach ([null, 'csv', 'pdf'] as $format) {
                $this->get(route('superadmin.account-statements.show', ['user' => $target, 'format' => $format]))->assertForbidden();
            }
        }
    }

    public function test_super_user_search_is_paginated_and_includes_users_without_wallets(): void
    {
        $admin = $this->superUser();
        User::factory()->count(29)->create(['userSurname' => 'SearchStatementSurname']);
        $target = User::factory()->create(['name' => 'Statement Target', 'email' => 'statement-target@example.test', 'userSurname' => 'SearchStatementSurname']);
        $this->assertNull($target->wallet);
        $this->actingAs($admin)->get(route('superadmin.account-statements.index', ['search' => 'SearchStatementSurname']))
            ->assertOk()->assertViewHas('users', fn ($users) => $users->count() === 25 && $users->total() === 30);
        $this->get(route('superadmin.account-statements.index', ['search' => 'statement-target@example.test']))
            ->assertOk()->assertSee('Statement Target')->assertSee(route('superadmin.account-statements.show', $target), false)
            ->assertViewHas('users', fn ($users) => $users->total() === 1);
        $this->get(route('superadmin.account-statements.index', ['search' => 'no-account-matches-this']))->assertOk()->assertSee('No accounts match');
    }

    public function test_selected_account_is_preserved_for_preview_filters_and_exports(): void
    {
        $admin = $this->superUser();
        $target = User::factory()->create(['name' => 'Target Payer', 'email' => 'target-payer@example.test']);
        $this->payment($target, 'TARGET-RECEIPT', 123.45);
        $this->payment($admin, 'ACTOR-RECEIPT', 999);
        $params = ['user' => $target, 'from' => '2025-01-01', 'to' => '2026-12-31', 'category' => 'clothing'];
        $this->actingAs($admin)->get(route('superadmin.account-statements.show', $params))
            ->assertOk()->assertSee('Super Admin: viewing another account')->assertSee('Target Payer')->assertSee('Choose another user')
            ->assertSee('action="'.route('superadmin.account-statements.show', $target).'"', false)
            ->assertViewHas('account', fn ($account) => $account->is($target))
            ->assertViewHas('paid', 12345)->assertViewHas('payments', fn ($payments) => $payments->path() === route('superadmin.account-statements.show', $target));
        $csv = $this->get(route('superadmin.account-statements.show', $params + ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('target-payer@example.test', $csv);
        $this->assertStringContainsString('TARGET-RECEIPT', $csv);
        $this->assertStringContainsString('123.45', $csv);
        $this->assertStringNotContainsString('ACTOR-RECEIPT', $csv);
        $this->get(route('superadmin.account-statements.show', $params + ['format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseCount('clothing_orders', 2);
    }

    public function test_self_statement_ignores_submitted_user_ids_even_for_super_user(): void
    {
        $actor = $this->superUser();
        $other = User::factory()->create();
        $this->payment($other, 'PRIVATE-TARGET', 300);
        $this->actingAs($actor)->get(route('account.statement', ['user' => $other->id, 'user_id' => $other->id,
            'from' => '2025-01-01', 'to' => '2026-12-31']))->assertOk()
            ->assertViewHas('account', fn ($account) => $account->is($actor))->assertViewHas('paid', 0)
            ->assertDontSee('PRIVATE-TARGET')->assertDontSee('Super Admin: viewing another account');
    }

    public function test_super_user_can_view_empty_account_without_wallet_and_missing_target_is_404(): void
    {
        $target = User::factory()->create();
        $this->actingAs($this->superUser())->get(route('superadmin.account-statements.show', $target))
            ->assertOk()->assertViewHas('paid', 0)->assertSee('No recorded entry payments');
        $this->get(route('superadmin.account-statements.show', ['user' => 999999999]))->assertNotFound();
    }
}
