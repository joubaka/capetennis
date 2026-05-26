<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entries;

use App\Domain\Entries\Services\EntryEligibilityService;
use App\Domain\Entries\Services\EntryService;
use App\Domain\Entries\StateMachine\EntryStateMachine;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit / feature tests for the canonical Entry domain.
 *
 * Coverage:
 *  - EntryStateMachine transitions
 *  - EntryEligibilityService guards
 *  - EntryService withdraw / transfer / lock flows
 *  - Duplicate prevention
 *  - Withdrawal deadline enforcement
 *  - Locked draw protection
 */
class EntryServiceTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // EntryStateMachine
    // -----------------------------------------------------------------------

    public function test_state_machine_allows_valid_transitions(): void
    {
        $sm = new EntryStateMachine();

        $this->assertTrue($sm->canTransition(EntryStateMachine::STATE_PAID, EntryStateMachine::STATE_WITHDRAWN));
        $this->assertTrue($sm->canTransition(EntryStateMachine::STATE_WITHDRAWN, EntryStateMachine::STATE_REFUND_REQUESTED));
        $this->assertTrue($sm->canTransition(EntryStateMachine::STATE_REFUND_REQUESTED, EntryStateMachine::STATE_REFUNDED));
        $this->assertTrue($sm->canTransition(EntryStateMachine::STATE_PAID, EntryStateMachine::STATE_CANCELLED, isAdmin: true));
    }

    public function test_state_machine_blocks_invalid_transitions(): void
    {
        $sm = new EntryStateMachine();

        $this->assertFalse($sm->canTransition(EntryStateMachine::STATE_REFUNDED, EntryStateMachine::STATE_PAID));
        $this->assertFalse($sm->canTransition(EntryStateMachine::STATE_WITHDRAWN, EntryStateMachine::STATE_PAID));
        $this->assertFalse($sm->canTransition(EntryStateMachine::STATE_DRAFT, EntryStateMachine::STATE_WITHDRAWN));
    }

    public function test_state_machine_blocks_cancelled_for_non_admin(): void
    {
        $sm = new EntryStateMachine();

        $this->assertFalse($sm->canTransition(EntryStateMachine::STATE_PAID, EntryStateMachine::STATE_CANCELLED, isAdmin: false));
        $this->assertTrue($sm->canTransition(EntryStateMachine::STATE_PAID, EntryStateMachine::STATE_CANCELLED, isAdmin: true));
    }

    public function test_state_machine_throws_on_invalid_transition(): void
    {
        $sm = new EntryStateMachine();

        $this->expectException(\RuntimeException::class);
        $sm->assertTransition(EntryStateMachine::STATE_REFUNDED, EntryStateMachine::STATE_PAID);
    }

    public function test_state_machine_resolves_legacy_states(): void
    {
        $sm = new EntryStateMachine();

        $this->assertSame(
            EntryStateMachine::STATE_PAID,
            $sm->resolveFromLegacy('active', null, 1)
        );

        $this->assertSame(
            EntryStateMachine::STATE_WITHDRAWN,
            $sm->resolveFromLegacy('withdrawn', now()->toDateTimeString(), 1)
        );

        $this->assertSame(
            EntryStateMachine::STATE_DRAFT,
            $sm->resolveFromLegacy('active', null, 0)
        );
    }

    // -----------------------------------------------------------------------
    // EntryEligibilityService
    // -----------------------------------------------------------------------

    public function test_eligibility_throws_when_category_locked(): void
    {
        $categoryEvent = CategoryEvent::factory()->locked()->create();

        $eligibility = new EntryEligibilityService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/i');

        $eligibility->assertCanAddAdmin($categoryEvent, 1);
    }

    public function test_eligibility_throws_when_withdrawals_disabled(): void
    {
        SiteSetting::set('withdrawal_allowed', '0');

        $eligibility = new EntryEligibilityService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/disabled/i');

        $eligibility->assertWithdrawalsOpen(isAdmin: false);
    }

    public function test_eligibility_admin_bypasses_withdrawal_switch(): void
    {
        SiteSetting::set('withdrawal_allowed', '0');

        $eligibility = new EntryEligibilityService();

        // Should not throw for admins
        $eligibility->assertWithdrawalsOpen(isAdmin: true);

        $this->assertTrue(true);
    }

    public function test_eligibility_throws_when_registration_already_withdrawn(): void
    {
        $entry = CategoryEventRegistration::factory()->withdrawn()->create();

        $eligibility = new EntryEligibilityService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already withdrawn/i');

        $eligibility->assertNotWithdrawn($entry);
    }

    public function test_eligibility_blocks_withdraw_from_locked_draw_for_non_admin(): void
    {
        $categoryEvent = CategoryEvent::factory()->locked()->create();
        $entry = CategoryEventRegistration::factory()
            ->for($categoryEvent)
            ->create(['status' => 'active']);

        $eligibility = new EntryEligibilityService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/draw has been finalised/i');

        $eligibility->assertDrawNotLocked($entry, isAdmin: false);
    }

    public function test_eligibility_admin_can_withdraw_from_locked_draw(): void
    {
        $categoryEvent = CategoryEvent::factory()->locked()->create();
        $entry = CategoryEventRegistration::factory()
            ->for($categoryEvent)
            ->create(['status' => 'active']);

        $eligibility = new EntryEligibilityService();

        // Should not throw
        $eligibility->assertDrawNotLocked($entry, isAdmin: true);

        $this->assertTrue(true);
    }

    // -----------------------------------------------------------------------
    // EntryEligibilityService — transfer guards
    // -----------------------------------------------------------------------

    public function test_eligibility_blocks_transfer_to_locked_category(): void
    {
        $event = Event::factory()->create();
        $fromCategory = CategoryEvent::factory()->for($event)->create();
        $toCategory   = CategoryEvent::factory()->for($event)->locked()->create();

        $entry = CategoryEventRegistration::factory()
            ->for($fromCategory)
            ->create(['status' => 'active']);

        $user = User::factory()->create();

        $eligibility = new EntryEligibilityService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/i');

        $eligibility->assertCanTransfer($entry, $toCategory, $user);
    }

    public function test_eligibility_blocks_transfer_to_different_event(): void
    {
        $event1 = Event::factory()->create();
        $event2 = Event::factory()->create();

        $fromCategory = CategoryEvent::factory()->for($event1)->create();
        $toCategory   = CategoryEvent::factory()->for($event2)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($fromCategory)
            ->create(['status' => 'active']);

        $user = User::factory()->create();

        $eligibility = new EntryEligibilityService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/different event/i');

        $eligibility->assertCanTransfer($entry, $toCategory, $user);
    }

    public function test_eligibility_blocks_duplicate_in_target_category(): void
    {
        $event = Event::factory()->create();
        $fromCategory = CategoryEvent::factory()->for($event)->create();
        $toCategory   = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($fromCategory)
            ->create(['status' => 'active']);

        // Pre-create a duplicate in the target category
        CategoryEventRegistration::factory()
            ->for($toCategory)
            ->create(['registration_id' => $entry->registration_id]);

        $user = User::factory()->create();

        $eligibility = new EntryEligibilityService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already registered/i');

        $eligibility->assertCanTransfer($entry, $toCategory, $user);
    }

    // -----------------------------------------------------------------------
    // Draw safety edge cases
    // -----------------------------------------------------------------------

    public function test_cannot_withdraw_from_locked_draw_as_player(): void
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            'withdrawal_deadline' => now()->addDays(7),
        ]);
        $categoryEvent = CategoryEvent::factory()->for($event)->locked()->create();

        $entry = CategoryEventRegistration::factory()
            ->for($categoryEvent)
            ->create([
                'user_id' => $user->id,
                'status'  => 'active',
            ]);

        // canWithdraw() is on the model — it should return ok: false for draw_locked
        $result = $entry->canWithdraw($user);

        $this->assertFalse($result['ok']);
        $this->assertSame('draw_locked', $result['reason']);
    }

    public function test_withdrawal_after_deadline_is_allowed_but_no_refund(): void
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            'withdrawal_deadline' => now()->subDays(1),
        ]);
        $categoryEvent = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($categoryEvent)
            ->create([
                'user_id' => $user->id,
                'status'  => 'active',
            ]);

        $result = $entry->canWithdraw($user);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['refund_allowed']);
        $this->assertSame('late_withdraw', $result['reason']);
    }

    public function test_withdrawal_before_deadline_allows_refund(): void
    {
        $user = User::factory()->create();

        $event = Event::factory()->create([
            'withdrawal_deadline' => now()->addDays(7),
        ]);
        $categoryEvent = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($categoryEvent)
            ->create([
                'user_id' => $user->id,
                'status'  => 'active',
            ]);

        $result = $entry->canWithdraw($user);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['refund_allowed']);
    }

    // -----------------------------------------------------------------------
    // EntryService — withdraw (unit, mocked DB)
    // -----------------------------------------------------------------------

    public function test_entry_service_throws_if_already_withdrawn(): void
    {
        $user = User::factory()->create();
        $entry = CategoryEventRegistration::factory()->withdrawn()->create(['user_id' => $user->id]);

        $service = app(EntryService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already withdrawn/i');

        $service->withdrawEntry($entry, $user);
    }

    // -----------------------------------------------------------------------
    // Registration state transitions
    // -----------------------------------------------------------------------

    public function test_full_state_flow_draft_to_refunded(): void
    {
        $sm = new EntryStateMachine();

        $state = EntryStateMachine::STATE_DRAFT;

        $state = $this->doTransition($sm, $state, EntryStateMachine::STATE_RESERVED);
        $state = $this->doTransition($sm, $state, EntryStateMachine::STATE_PAID);
        $state = $this->doTransition($sm, $state, EntryStateMachine::STATE_WITHDRAWN);
        $state = $this->doTransition($sm, $state, EntryStateMachine::STATE_REFUND_REQUESTED);
        $state = $this->doTransition($sm, $state, EntryStateMachine::STATE_REFUNDED);

        $this->assertSame(EntryStateMachine::STATE_REFUNDED, $state);
    }

    private function doTransition(EntryStateMachine $sm, string $from, string $to): string
    {
        $sm->assertTransition($from, $to);
        return $to;
    }

    // -----------------------------------------------------------------------
    // P0 HOTFIX 1 — withdrawal removes RR draw-group membership
    // -----------------------------------------------------------------------

    public function test_withdraw_entry_as_admin_removes_draw_group_membership(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($ce)
            ->create(['user_id' => $user->id, 'status' => 'active']);

        $registrationId = $entry->registration_id;

        // Build a RR draw group and place the player inside it
        $draw = \App\Models\Draw::create([
            'drawName'          => 'RR',
            'drawType_id'       => 1,
            'category_event_id' => $ce->id,
            'event_id'          => $event->id,
        ]);

        $group = \App\Models\DrawGroup::create([
            'draw_id' => $draw->id,
            'name'    => 'A',
        ]);

        \App\Models\DrawGroupRegistration::create([
            'draw_group_id'   => $group->id,
            'registration_id' => $registrationId,
        ]);

        $this->assertDatabaseHas('draw_group_registrations', [
            'draw_group_id'   => $group->id,
            'registration_id' => $registrationId,
        ]);

        app(EntryService::class)->withdrawEntryAsAdmin($entry, $user);

        $this->assertDatabaseMissing('draw_group_registrations', [
            'draw_group_id'   => $group->id,
            'registration_id' => $registrationId,
        ]);
    }

    public function test_withdrawn_entry_status_is_withdrawn(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($ce)
            ->create(['user_id' => $user->id, 'status' => 'active']);

        app(EntryService::class)->withdrawEntryAsAdmin($entry, $user);

        $this->assertDatabaseHas('category_event_registrations', [
            'id'     => $entry->id,
            'status' => 'withdrawn',
        ]);
    }

    public function test_withdrawn_entry_excluded_from_active_scope(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($ce)
            ->create(['user_id' => $user->id, 'status' => 'active']);

        app(EntryService::class)->withdrawEntryAsAdmin($entry, $user);

        $active = CategoryEventRegistration::where('category_event_id', $ce->id)
            ->where('status', 'active')
            ->where('id', $entry->id)
            ->exists();

        $this->assertFalse($active);
    }

    // -----------------------------------------------------------------------
    // P0 HOTFIX 2 — admin add enforces lock and duplicate checks
    // -----------------------------------------------------------------------

    public function test_admin_add_blocked_when_category_locked(): void
    {
        $admin = User::factory()->create();
        $ce    = CategoryEvent::factory()->locked()->create();

        $player = \App\Models\Player::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/i');

        app(EntryService::class)->addPlayerAsAdmin($ce, $player->id, $admin);
    }

    public function test_admin_add_blocked_when_player_already_active(): void
    {
        $admin = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $player = \App\Models\Player::factory()->create();

        // First add succeeds
        app(EntryService::class)->addPlayerAsAdmin($ce, $player->id, $admin);

        // Second add must be blocked
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already registered/i');

        app(EntryService::class)->addPlayerAsAdmin($ce, $player->id, $admin);
    }

    public function test_admin_add_creates_audit_transaction_record(): void
    {
        $admin  = User::factory()->create();
        $event  = Event::factory()->create();
        $ce     = CategoryEvent::factory()->for($event)->create();
        $player = \App\Models\Player::factory()->create();

        app(EntryService::class)->addPlayerAsAdmin($ce, $player->id, $admin);

        $this->assertDatabaseHas('transactions_pf', [
            'transaction_type'  => 'Registration',
            'category_event_id' => $ce->id,
            'player_id'         => $player->id,
            'item_name'         => 'Admin Entry',
        ]);
    }

    // -----------------------------------------------------------------------
    // P0 HOTFIX 4 — hybridCancel uses server-side wallet_reserved only
    // -----------------------------------------------------------------------

    public function test_hybrid_cancel_resets_wallet_reserved_from_db(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $order = \App\Models\RegistrationOrder::create([
            'user_id'           => $user->id,
            'pay_status'        => 0,
            'payfast_paid'      => false,
            'wallet_reserved'   => 150.00,
            'payfast_amount_due'=> 100.00,
            'wallet_debited'    => false,
        ]);

        $this->actingAs($user);

        // Attempt cancel with a tampered client wallet amount — the controller
        // must ignore any request body and reset from the order record only.
        $response = $this->get(route('registration.hybrid.cancel', ['orderId' => $order->id]));

        $response->assertRedirect();

        $order->refresh();

        // Server must zero out wallet_reserved regardless of any client-supplied value
        $this->assertEquals(0, (float) $order->wallet_reserved);
        $this->assertEquals(0, (float) $order->payfast_amount_due);
    }

    public function test_hybrid_cancel_does_not_debit_wallet(): void
    {
        $user   = User::factory()->create();
        $wallet = \App\Models\Wallet::factory()->forUser($user)->create();

        // Seed a credit of 500 so we can verify it remains untouched
        \App\Models\WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type'      => 'credit',
            'amount'    => 500.00,
        ]);

        $order = \App\Models\RegistrationOrder::create([
            'user_id'            => $user->id,
            'pay_status'         => 0,
            'payfast_paid'       => false,
            'wallet_reserved'    => 150.00,
            'payfast_amount_due' => 100.00,
            'wallet_debited'     => false,
        ]);

        $this->actingAs($user);

        $this->get(route('registration.hybrid.cancel', ['orderId' => $order->id]));

        // Wallet balance must be untouched — cancel must never debit
        $wallet->refresh();
        $this->assertEquals(500.00, $wallet->balance);
    }

    // -----------------------------------------------------------------------
    // HOTFIX 4 — removePlayer() sets status = 'withdrawn'
    // -----------------------------------------------------------------------

    public function test_remove_player_sets_status_to_withdrawn(): void
    {
        $admin = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($ce)
            ->create(['status' => 'active']);

        $registration = \App\Models\Registration::find($entry->registration_id);

        app(\App\Domain\Entries\Services\EntryService::class)->removePlayer($ce, $registration, $admin);

        $this->assertDatabaseHas('category_event_registrations', [
            'id'     => $entry->id,
            'status' => 'withdrawn',
        ]);
    }

    public function test_remove_player_excluded_from_active_scope(): void
    {
        $admin = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($ce)
            ->create(['status' => 'active']);

        $registration = \App\Models\Registration::find($entry->registration_id);

        app(\App\Domain\Entries\Services\EntryService::class)->removePlayer($ce, $registration, $admin);

        $exists = CategoryEventRegistration::where('id', $entry->id)
            ->active()
            ->exists();

        $this->assertFalse($exists, 'Removed player must not appear in active scope');
    }

    public function test_remove_player_sets_withdrawn_by(): void
    {
        $admin = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($ce)
            ->create(['status' => 'active']);

        $registration = \App\Models\Registration::find($entry->registration_id);

        app(\App\Domain\Entries\Services\EntryService::class)->removePlayer($ce, $registration, $admin);

        $this->assertDatabaseHas('category_event_registrations', [
            'id'          => $entry->id,
            'withdrawn_by' => $admin->id,
        ]);
    }

    public function test_remove_player_blocked_when_category_locked(): void
    {
        $admin = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->locked()->create();

        $entry = CategoryEventRegistration::factory()
            ->for($ce)
            ->create(['status' => 'active']);

        $registration = \App\Models\Registration::find($entry->registration_id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/i');

        app(\App\Domain\Entries\Services\EntryService::class)->removePlayer($ce, $registration, $admin);
    }

    // -----------------------------------------------------------------------
    // HOTFIX 5 — markWithdrawn() is idempotent: double-call is safe
    // -----------------------------------------------------------------------

    public function test_mark_withdrawn_is_idempotent_on_double_call(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($ce)
            ->create(['user_id' => $user->id, 'status' => 'active']);

        // First call
        $entry->markWithdrawn($user, 'self');

        $entry->refresh();
        $this->assertEquals('withdrawn', $entry->status);
        $firstWithdrawnAt = $entry->withdrawn_at;

        // Second call — must not throw, must not change withdrawn_at or create duplicate log
        $entry->markWithdrawn($user, 'self');

        $entry->refresh();
        $this->assertEquals('withdrawn', $entry->status);
        $this->assertEquals($firstWithdrawnAt->toDateTimeString(), $entry->withdrawn_at->toDateTimeString());
    }

    public function test_mark_withdrawn_does_not_duplicate_activity_log(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($ce)
            ->create(['user_id' => $user->id, 'status' => 'active']);

        $entry->markWithdrawn($user, 'self');
        $entry->markWithdrawn($user, 'self'); // second call — idempotent

        $logCount = \Spatie\Activitylog\Models\Activity::where('log_name', 'withdrawal')
            ->where('subject_type', get_class($entry))
            ->where('subject_id', $entry->id)
            ->count();

        $this->assertEquals(1, $logCount, 'markWithdrawn must write exactly one activity log entry');
    }

    public function test_mark_withdrawn_resets_refund_fields_to_zero(): void
    {
        $user  = User::factory()->create();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $entry = CategoryEventRegistration::factory()
            ->for($ce)
            ->create([
                'user_id'      => $user->id,
                'status'       => 'active',
                'refund_gross' => 100.00,
                'refund_fee'   => 10.00,
                'refund_net'   => 90.00,
            ]);

        $entry->markWithdrawn($user, 'self');

        $entry->refresh();
        $this->assertEquals(0, $entry->refund_gross);
        $this->assertEquals(0, $entry->refund_fee);
        $this->assertEquals(0, $entry->refund_net);
        $this->assertNull($entry->refund_method);
        $this->assertEquals('not_refunded', $entry->refund_status);
    }
}
