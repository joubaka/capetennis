<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminWalletSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
    }

    public function test_super_user_can_find_a_wallet_by_name_or_email(): void
    {
        $admin = User::factory()->create()->assignRole('super-user');
        $matchedByName = User::factory()->create([
            'name' => 'Wallet Search Target',
            'email' => 'first-wallet@example.test',
        ]);
        $matchedByEmail = User::factory()->create([
            'name' => 'Different Person',
            'email' => 'unique.wallet@example.test',
        ]);
        $excluded = User::factory()->create([
            'name' => 'Excluded Wallet Owner',
            'email' => 'excluded@example.test',
        ]);

        $matchedByName->wallet()->create();
        $matchedByEmail->wallet()->create();
        $excluded->wallet()->create();

        $this->actingAs($admin)
            ->get(route('backend.superadmin.workspace', [
                'tab' => 'wallets',
                'wallet_search' => 'Wallet Search',
            ]))
            ->assertOk()
            ->assertSee('Search name or email')
            ->assertViewHas('wallets', function ($wallets) use ($matchedByName, $excluded) {
                return $wallets->pluck('payable_id')->contains($matchedByName->id)
                    && ! $wallets->pluck('payable_id')->contains($excluded->id);
            });

        $this->actingAs($admin)
            ->get(route('backend.superadmin.workspace', [
                'tab' => 'wallets',
                'wallet_search' => 'unique.wallet@example.test',
            ]))
            ->assertOk()
            ->assertViewHas('wallets', function ($wallets) use ($matchedByEmail, $excluded) {
                return $wallets->pluck('payable_id')->contains($matchedByEmail->id)
                    && ! $wallets->pluck('payable_id')->contains($excluded->id);
            });
    }

    public function test_wallet_search_remains_restricted_to_super_users(): void
    {
        $ordinaryUser = User::factory()->create();

        $this->actingAs($ordinaryUser)
            ->get(route('backend.superadmin.workspace', [
                'tab' => 'wallets',
                'wallet_search' => 'someone@example.test',
            ]))
            ->assertForbidden();
    }
}
