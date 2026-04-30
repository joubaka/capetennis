<?php

namespace Tests\Unit;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Mockery;
use Tests\TestCase;

class CategoryEventRegistrationModelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function regWithEvent(
        int $userId,
        string $status,
        Carbon $withdrawalDeadline,
        ?string $pfTxId = null
    ): CategoryEventRegistration {
        $illuminateDeadline = IlluminateCarbon::instance($withdrawalDeadline);

        $event = Mockery::mock(Event::class)->makePartial();
        $event->withdrawal_deadline = $illuminateDeadline;
        $event->shouldReceive('withdrawalCloseAt')->andReturn($illuminateDeadline);

        $categoryEvent = Mockery::mock(CategoryEvent::class)->makePartial();
        $categoryEvent->shouldReceive('getAttribute')->with('event')->andReturn($event);

        $reg = Mockery::mock(CategoryEventRegistration::class)->makePartial();
        $reg->user_id = $userId;
        $reg->status = $status;
        $reg->pf_transaction_id = $pfTxId;
        $reg->shouldReceive('getAttribute')->with('categoryEvent')->andReturn($categoryEvent);
        $reg->shouldReceive('getAttribute')->with('pf_transaction_id')->andReturn($pfTxId);

        return $reg;
    }

    private function user(int $id): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->shouldReceive('hasAnyRole')->andReturn(false);
        return $user;
    }

    // -----------------------------------------------------------------------
    // canWithdraw — ownership
    // -----------------------------------------------------------------------

    public function test_can_withdraw_fails_for_non_owner(): void
    {
        $reg = $this->regWithEvent(1, 'active', Carbon::now()->addDays(10));

        $result = $reg->canWithdraw($this->user(99));

        $this->assertFalse($result['ok']);
        $this->assertEquals('not_owner', $result['reason']);
        $this->assertFalse($result['refund_allowed']);
    }

    // -----------------------------------------------------------------------
    // canWithdraw — already withdrawn
    // -----------------------------------------------------------------------

    public function test_can_withdraw_fails_when_already_withdrawn(): void
    {
        $reg = $this->regWithEvent(1, 'withdrawn', Carbon::now()->addDays(10));

        $result = $reg->canWithdraw($this->user(1));

        $this->assertFalse($result['ok']);
        $this->assertEquals('already_withdrawn', $result['reason']);
    }

    // -----------------------------------------------------------------------
    // canWithdraw — deadline passed → no refund
    // -----------------------------------------------------------------------

    public function test_can_withdraw_after_deadline_disallows_refund(): void
    {
        $reg = $this->regWithEvent(1, 'active', Carbon::now()->subDays(1));

        $result = $reg->canWithdraw($this->user(1));

        $this->assertTrue($result['ok']);
        $this->assertEquals('late_withdraw', $result['reason']);
        $this->assertFalse($result['refund_allowed']);
    }

    // -----------------------------------------------------------------------
    // canWithdraw — before deadline → refund allowed
    // -----------------------------------------------------------------------

    public function test_can_withdraw_before_deadline_allows_refund(): void
    {
        $reg = $this->regWithEvent(1, 'active', Carbon::now()->addDays(5));

        $result = $reg->canWithdraw($this->user(1));

        $this->assertTrue($result['ok']);
        $this->assertEquals('allowed', $result['reason']);
        $this->assertTrue($result['refund_allowed']);
    }

    // -----------------------------------------------------------------------
    // is_paid accessor
    // -----------------------------------------------------------------------

    public function test_is_paid_true_when_pf_transaction_id_is_set(): void
    {
        $reg = new CategoryEventRegistration([
            'pf_transaction_id' => 'PF-12345',
        ]);

        $this->assertTrue($reg->is_paid);
    }

    public function test_is_paid_false_when_no_pf_transaction_id(): void
    {
        $reg = new CategoryEventRegistration([
            'pf_transaction_id' => null,
        ]);

        // Mock payfastTransaction returning null
        $reg = Mockery::mock(CategoryEventRegistration::class)->makePartial();
        $reg->pf_transaction_id = null;
        $reg->shouldReceive('getAttribute')->with('payfastTransaction')->andReturn(null);
        $reg->shouldReceive('getAttribute')->with('pf_transaction_id')->andReturn(null);

        $this->assertFalse($reg->getIsPaidAttribute());
    }

    // -----------------------------------------------------------------------
    // Refund status helpers
    // -----------------------------------------------------------------------

    public function test_is_bank_refund(): void
    {
        $reg = new CategoryEventRegistration(['refund_method' => 'bank']);
        $this->assertTrue($reg->isBankRefund());
    }

    public function test_is_not_bank_refund_when_wallet(): void
    {
        $reg = new CategoryEventRegistration(['refund_method' => 'wallet']);
        $this->assertFalse($reg->isBankRefund());
    }

    public function test_is_wallet_refund(): void
    {
        $reg = new CategoryEventRegistration(['refund_method' => 'wallet']);
        $this->assertTrue($reg->isWalletRefund());
    }

    // -----------------------------------------------------------------------
    // Constants
    // -----------------------------------------------------------------------

    public function test_refund_pending_constant(): void
    {
        $this->assertEquals('pending', CategoryEventRegistration::REFUND_PENDING);
    }

    public function test_refund_completed_constant(): void
    {
        $this->assertEquals('completed', CategoryEventRegistration::REFUND_COMPLETED);
    }
}
