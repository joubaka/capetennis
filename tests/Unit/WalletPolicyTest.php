<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Wallet;
use App\Policies\WalletPolicy;
use Mockery;
use Tests\TestCase;

class WalletPolicyTest extends TestCase
{
    private WalletPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new WalletPolicy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function user(int $id, array $roles = []): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->shouldReceive('hasAnyRole')->andReturnUsing(
            fn($checkRoles) => count(array_intersect((array) $checkRoles, $roles)) > 0
        );
        return $user;
    }

    private function wallet(int $userId): Wallet
    {
        $wallet = Mockery::mock(Wallet::class)->makePartial();
        $wallet->user_id = $userId;
        return $wallet;
    }

    // -----------------------------------------------------------------------
    // viewAny
    // -----------------------------------------------------------------------

    public function test_view_any_allowed_for_admin(): void
    {
        $this->assertTrue($this->policy->viewAny($this->user(1, ['admin'])));
    }

    public function test_view_any_denied_for_regular_user(): void
    {
        $this->assertFalse($this->policy->viewAny($this->user(1, [])));
    }

    // -----------------------------------------------------------------------
    // view
    // -----------------------------------------------------------------------

    public function test_view_allowed_for_wallet_owner(): void
    {
        $user = $this->user(5);
        $wallet = $this->wallet(5);
        $this->assertTrue($this->policy->view($user, $wallet));
    }

    public function test_view_allowed_for_admin(): void
    {
        $user = $this->user(99, ['admin']);
        $wallet = $this->wallet(5);
        $this->assertTrue($this->policy->view($user, $wallet));
    }

    public function test_view_denied_for_non_owner_regular_user(): void
    {
        $user = $this->user(7, []);
        $wallet = $this->wallet(5);
        $this->assertFalse($this->policy->view($user, $wallet));
    }

    // -----------------------------------------------------------------------
    // credit
    // -----------------------------------------------------------------------

    public function test_credit_allowed_for_admin(): void
    {
        $user = $this->user(1, ['admin']);
        $wallet = $this->wallet(99);
        $this->assertTrue($this->policy->credit($user, $wallet));
    }

    public function test_credit_denied_for_wallet_owner_who_is_not_admin(): void
    {
        $user = $this->user(5, []);
        $wallet = $this->wallet(5);
        $this->assertFalse($this->policy->credit($user, $wallet));
    }

    // -----------------------------------------------------------------------
    // debit
    // -----------------------------------------------------------------------

    public function test_debit_allowed_for_wallet_owner(): void
    {
        $user = $this->user(5, []);
        $wallet = $this->wallet(5);
        $this->assertTrue($this->policy->debit($user, $wallet));
    }

    public function test_debit_allowed_for_admin(): void
    {
        $user = $this->user(99, ['admin']);
        $wallet = $this->wallet(5);
        $this->assertTrue($this->policy->debit($user, $wallet));
    }

    public function test_debit_denied_for_other_regular_user(): void
    {
        $user = $this->user(7, []);
        $wallet = $this->wallet(5);
        $this->assertFalse($this->policy->debit($user, $wallet));
    }

    // -----------------------------------------------------------------------
    // viewTransactions
    // -----------------------------------------------------------------------

    public function test_view_transactions_allowed_for_owner(): void
    {
        $user = $this->user(5, []);
        $wallet = $this->wallet(5);
        $this->assertTrue($this->policy->viewTransactions($user, $wallet));
    }

    public function test_view_transactions_allowed_for_admin(): void
    {
        $user = $this->user(1, ['admin']);
        $wallet = $this->wallet(5);
        $this->assertTrue($this->policy->viewTransactions($user, $wallet));
    }

    public function test_view_transactions_denied_for_other_user(): void
    {
        $user = $this->user(7, []);
        $wallet = $this->wallet(5);
        $this->assertFalse($this->policy->viewTransactions($user, $wallet));
    }
}
