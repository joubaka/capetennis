<?php

namespace Tests\Unit;

use App\Models\Event;
use Carbon\Carbon;
use Tests\TestCase;

class EventModelTest extends TestCase
{
    // -----------------------------------------------------------------------
    // registrationClosesAt()
    // -----------------------------------------------------------------------

    public function test_registration_closes_at_returns_null_when_no_start_date(): void
    {
        $event = new Event(['deadline' => 5]);

        $this->assertNull($event->registrationClosesAt());
    }

    public function test_registration_closes_at_returns_null_when_no_deadline(): void
    {
        $event = new Event(['start_date' => '2030-06-01', 'deadline' => null]);

        $this->assertNull($event->registrationClosesAt());
    }

    public function test_registration_closes_at_subtracts_deadline_days(): void
    {
        $event = new Event([
            'start_date' => Carbon::parse('2030-06-10'),
            'deadline' => 7,
        ]);

        $expected = Carbon::parse('2030-06-03');

        $this->assertTrue($event->registrationClosesAt()->isSameDay($expected));
    }

    // -----------------------------------------------------------------------
    // withdrawalCloseAt()
    // -----------------------------------------------------------------------

    public function test_withdrawal_close_at_uses_explicit_withdrawal_deadline(): void
    {
        $deadline = Carbon::parse('2030-05-20 12:00:00');
        $event = new Event([
            'start_date' => Carbon::parse('2030-06-10'),
            'deadline' => 7,
            'withdrawal_deadline' => $deadline,
        ]);

        $this->assertTrue($event->withdrawalCloseAt()->eq($deadline));
    }

    public function test_withdrawal_close_at_falls_back_to_entry_close_when_no_withdrawal_deadline(): void
    {
        $event = new Event([
            'start_date' => Carbon::parse('2030-06-10'),
            'deadline' => 10,
            'withdrawal_deadline' => null,
        ]);

        // entryCloseAt = start_date - (deadline - 1) days = 2030-06-10 - 9 days = 2030-06-01
        $expected = Carbon::parse('2030-06-01');

        $this->assertTrue($event->withdrawalCloseAt()->isSameDay($expected));
    }

    // -----------------------------------------------------------------------
    // canWithdraw()
    // -----------------------------------------------------------------------

    public function test_can_withdraw_returns_true_when_no_withdrawal_deadline(): void
    {
        $event = new Event(['withdrawal_deadline' => null]);

        $this->assertTrue($event->canWithdraw());
    }

    public function test_can_withdraw_returns_true_when_deadline_is_in_future(): void
    {
        $event = new Event([
            'withdrawal_deadline' => Carbon::now()->addDays(5),
        ]);

        $this->assertTrue($event->canWithdraw());
    }

    public function test_can_withdraw_returns_false_when_deadline_has_passed(): void
    {
        $event = new Event([
            'withdrawal_deadline' => Carbon::now()->subDays(1),
        ]);

        $this->assertFalse($event->canWithdraw());
    }
}
