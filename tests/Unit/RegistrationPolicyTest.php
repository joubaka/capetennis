<?php

namespace Tests\Unit;

use App\Models\CategoryEventRegistration;
use App\Models\User;
use App\Policies\RegistrationPolicy;
use Mockery;
use Tests\TestCase;

class RegistrationPolicyTest extends TestCase
{
    private RegistrationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RegistrationPolicy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function user(int $id, array $roles = []): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->shouldReceive('hasAnyRole')->andReturnUsing(
            fn($checkRoles) => count(array_intersect((array) $checkRoles, $roles)) > 0
        );
        return $user;
    }

    private function registration(int $userId, string $status = 'active', bool $isPaid = false): CategoryEventRegistration
    {
        $reg = Mockery::mock(CategoryEventRegistration::class)->makePartial();
        $reg->user_id = $userId;
        $reg->status = $status;
        $reg->shouldReceive('getAttribute')->with('is_paid')->andReturn($isPaid);
        return $reg;
    }

    // -----------------------------------------------------------------------
    // viewAny
    // -----------------------------------------------------------------------

    public function test_view_any_allowed_for_admin(): void
    {
        $this->assertTrue($this->policy->viewAny($this->user(1, ['admin'])));
    }

    public function test_view_any_allowed_for_super_user(): void
    {
        $this->assertTrue($this->policy->viewAny($this->user(1, ['super-user'])));
    }

    public function test_view_any_denied_for_regular_user(): void
    {
        $this->assertFalse($this->policy->viewAny($this->user(1, [])));
    }

    // -----------------------------------------------------------------------
    // view
    // -----------------------------------------------------------------------

    public function test_view_allowed_for_owner(): void
    {
        $user = $this->user(42);
        $reg = $this->registration(42);
        $this->assertTrue($this->policy->view($user, $reg));
    }

    public function test_view_allowed_for_admin(): void
    {
        $user = $this->user(99, ['admin']);
        $reg = $this->registration(42);
        $this->assertTrue($this->policy->view($user, $reg));
    }

    public function test_view_denied_for_other_user(): void
    {
        $user = $this->user(5, []);
        $reg = $this->registration(42);
        $this->assertFalse($this->policy->view($user, $reg));
    }

    // -----------------------------------------------------------------------
    // withdraw
    // -----------------------------------------------------------------------

    public function test_withdraw_denied_when_already_withdrawn(): void
    {
        $user = $this->user(1);
        $reg = $this->registration(1, 'withdrawn');
        $this->assertFalse($this->policy->withdraw($user, $reg));
    }

    public function test_withdraw_allowed_for_owner(): void
    {
        $user = $this->user(1);
        $reg = $this->registration(1, 'active');
        $this->assertTrue($this->policy->withdraw($user, $reg));
    }

    public function test_withdraw_denied_for_non_owner_non_admin(): void
    {
        $user = $this->user(2, []);
        $reg = $this->registration(1, 'active');
        $this->assertFalse($this->policy->withdraw($user, $reg));
    }

    public function test_withdraw_allowed_for_admin_regardless_of_ownership(): void
    {
        $user = $this->user(99, ['admin']);
        $reg = $this->registration(1, 'active');
        $this->assertTrue($this->policy->withdraw($user, $reg));
    }

    // -----------------------------------------------------------------------
    // refund
    // -----------------------------------------------------------------------

    public function test_refund_denied_when_not_withdrawn(): void
    {
        $user = $this->user(1);
        $reg = $this->registration(1, 'active', true);
        $this->assertFalse($this->policy->refund($user, $reg));
    }

    public function test_refund_denied_when_not_paid(): void
    {
        $user = $this->user(1);
        $reg = $this->registration(1, 'withdrawn', false);
        $this->assertFalse($this->policy->refund($user, $reg));
    }

    public function test_refund_allowed_for_owner_who_is_withdrawn_and_paid(): void
    {
        $user = $this->user(1);
        $reg = $this->registration(1, 'withdrawn', true);
        $this->assertTrue($this->policy->refund($user, $reg));
    }

    public function test_refund_denied_for_other_user_not_admin(): void
    {
        $user = $this->user(2, []);
        $reg = $this->registration(1, 'withdrawn', true);
        $this->assertFalse($this->policy->refund($user, $reg));
    }

    // -----------------------------------------------------------------------
    // delete
    // -----------------------------------------------------------------------

    public function test_delete_allowed_for_super_user(): void
    {
        $user = $this->user(1, ['super-user']);
        $reg = $this->registration(99, 'active');
        $this->assertTrue($this->policy->delete($user, $reg));
    }

    public function test_delete_denied_for_regular_user(): void
    {
        $user = $this->user(1, []);
        $reg = $this->registration(1, 'active');
        $this->assertFalse($this->policy->delete($user, $reg));
    }
}
