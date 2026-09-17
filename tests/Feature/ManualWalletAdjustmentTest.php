<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualWalletAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
    }

    public function test_same_admin_can_credit_the_same_wallet_more_than_once(): void
    {
        $admin = User::factory()->create()->assignRole('super-user');
        $user = User::factory()->create();

        foreach ([100, 50] as $amount) {
            $this->actingAs($admin)->post(route('superadmin.wallets.transaction.store', $user), [
                'type' => 'credit',
                'amount' => $amount,
                'reference' => 'Manual top-up',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();
        }

        $this->assertSame(2, $user->wallet->transactions()->where('type', 'credit')->count());
        $this->assertSame(150.0, $user->wallet->balance);
    }

    public function test_retrying_the_same_manual_adjustment_is_idempotent(): void
    {
        $admin = User::factory()->create()->assignRole('super-user');
        $user = User::factory()->create();
        $key = (string) Str::uuid();
        $payload = [
            'type' => 'credit',
            'amount' => 75,
            'reference' => 'Manual top-up',
            'idempotency_key' => $key,
        ];

        $this->actingAs($admin)->post(route('superadmin.wallets.transaction.store', $user), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('superadmin.wallets.transaction.store', $user), $payload)->assertRedirect();

        $this->assertSame(1, $user->wallet->transactions()->where('type', 'credit')->count());
        $this->assertSame(75.0, $user->wallet->balance);
    }

    public function test_user_wallet_modal_submits_an_idempotency_key(): void
    {
        $admin = User::factory()->create()->assignRole('super-user');
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('user.show', $user));

        $response->assertOk();
        $response->assertSee('id="txn-idempotency-key"', false);
        $response->assertSee('idempotency_key: idempotencyKey', false);
    }
}
