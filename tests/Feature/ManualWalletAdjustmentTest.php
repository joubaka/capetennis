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

    public function test_successful_debit_retry_succeeds_after_balance_is_consumed_without_duplicate_debit(): void
    {
        $admin = User::factory()->create()->assignRole('super-user');
        $user = User::factory()->create();
        $route = route('superadmin.wallets.transaction.store', $user);
        $this->actingAs($admin)->post($route, [
            'type' => 'credit', 'amount' => '100.00', 'reference' => 'Initial funds', 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $payload = ['type' => 'debit', 'amount' => '90.25', 'reference' => 'Reviewed correction', 'idempotency_key' => (string) Str::uuid()];
        foreach ([1, 2] as $attempt) $this->post($route, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $user->wallet->transactions()->where('type', 'debit')->count());
        $this->assertSame(9.75, $user->wallet->balance);
        $this->post($route, array_replace($payload, ['amount' => '90.26']))->assertSessionHasErrors('idempotency_key');
        $this->post($route, array_replace($payload, ['reference' => 'Changed reason']))->assertSessionHasErrors('idempotency_key');
        $otherAdmin = User::factory()->create()->assignRole('super-user');
        $this->actingAs($otherAdmin)->post($route, $payload)->assertSessionHasErrors('idempotency_key');
        $this->assertSame(9.75, $user->wallet->fresh()->balance);
        $this->assertSame(2, $user->wallet->transactions()->count());
    }

    public function test_new_insufficient_debit_is_rejected_without_new_transaction(): void
    {
        $admin = User::factory()->create()->assignRole('super-user');
        $user = User::factory()->create();
        $this->actingAs($admin)->post(route('superadmin.wallets.transaction.store', $user), [
            'type' => 'debit', 'amount' => '1.00', 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_profile_wallet_json_debit_retry_returns_success_after_balance_consumed(): void
    {
        $admin = User::factory()->create()->assignRole('super-user');
        $user = User::factory()->create();
        $route = route('wallet.transaction.store', $user->id);
        $this->actingAs($admin)->postJson($route, [
            'type' => 'credit', 'amount' => '50.00', 'reference' => 'Starting funds', 'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();
        $payload = ['type' => 'debit', 'amount' => '50.00', 'reference' => 'Recorded deduction', 'idempotency_key' => (string) Str::uuid()];
        foreach ([1, 2] as $attempt) $this->postJson($route, $payload)->assertOk()->assertJsonPath('balance', 0);
        $this->postJson($route, array_replace($payload, ['reference' => 'Changed details']))
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->postJson($route, array_replace($payload, ['idempotency_key' => (string) Str::uuid()]))
            ->assertUnprocessable()->assertJsonPath('message', 'Insufficient balance for debit.');
        $this->assertSame(2, $user->wallet->transactions()->count());
        $this->assertSame(0.0, $user->wallet->fresh()->balance);
    }
}
