<?php

namespace Tests\Unit;

use App\Domain\Payments\Services\LedgerService;
use App\Domain\Payments\Services\PaymentOrchestrator;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Models\RegistrationOrder;
use App\Models\User;
use App\Models\Wallet;
use Database\Factories\WalletFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * PaymentOrchestrator – Financial Safety Test Suite
 *
 * Proves that:
 *   1. The first ITN / payment finalization completes the order.
 *   2. Duplicate ITN / concurrent duplicate calls leave the order unchanged.
 *   3. The wallet portion is never debited twice.
 *   4. An order's pay_status transitions to "completed" exactly once.
 */
class PaymentOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    private PaymentOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = app(PaymentOrchestrator::class);
    }

    // -------------------------------------------------------------------------
    // 1. First ITN completes payment
    // -------------------------------------------------------------------------

    public function test_first_itn_sets_pay_status_and_payfast_paid(): void
    {
        [$order] = $this->makeOrder(walletReserved: 0, payfastDue: 100);

        $result = $this->orchestrator->finalizePayment($order, [
            'pf_payment_id' => 'PF123',
            'payfast_amount_due' => 100,
        ]);

        $this->assertTrue((bool) $result->pay_status);
        $this->assertTrue((bool) $result->payfast_paid);
        $this->assertEquals('PF123', $result->payfast_pf_payment_id);
    }

    public function test_first_itn_debits_wallet_when_wallet_reserved_is_positive(): void
    {
        [$order, $wallet] = $this->makeOrder(walletReserved: 50, payfastDue: 50);

        $this->orchestrator->finalizePayment($order);

        $order->refresh();
        $this->assertTrue($order->wallet_debited);
        // Seed funded wallet_reserved + 500 = 550, debit = 50 → expected 500
        $this->assertEquals(500.0, $wallet->fresh()->balance);
    }

    public function test_first_itn_does_not_debit_wallet_when_reserved_is_zero(): void
    {
        [$order, $wallet] = $this->makeOrder(walletReserved: 0, payfastDue: 100);

        $this->orchestrator->finalizePayment($order);

        $order->refresh();
        $this->assertFalse((bool) $order->wallet_debited);
        $this->assertEquals(0.0, $wallet->fresh()->balance);
    }

    // -------------------------------------------------------------------------
    // 2. Duplicate ITN does nothing
    // -------------------------------------------------------------------------

    public function test_duplicate_itn_does_not_change_already_completed_order(): void
    {
        [$order] = $this->makeOrder(walletReserved: 0, payfastDue: 100);

        $this->orchestrator->finalizePayment($order, ['pf_payment_id' => 'PF-FIRST']);
        $order->refresh();

        // Second ITN — different PF id to prove it's truly ignored
        $this->orchestrator->finalizePayment($order, ['pf_payment_id' => 'PF-SECOND']);
        $order->refresh();

        $this->assertEquals('PF-FIRST', $order->payfast_pf_payment_id, 'PF id must not be overwritten on duplicate ITN');
    }

    public function test_duplicate_itn_returns_the_same_order_unchanged(): void
    {
        [$order] = $this->makeOrder(walletReserved: 0, payfastDue: 100);

        $first  = $this->orchestrator->finalizePayment($order);
        $second = $this->orchestrator->finalizePayment($order);

        $this->assertEquals($first->getKey(), $second->getKey());
        $this->assertTrue((bool) $second->pay_status);
    }

    // -------------------------------------------------------------------------
    // 3. Wallet portion is not debited twice
    // -------------------------------------------------------------------------

    public function test_wallet_is_not_debited_twice_on_duplicate_call(): void
    {
        [$order, $wallet] = $this->makeOrder(walletReserved: 80, payfastDue: 0);

        // First finalization
        $this->orchestrator->finalizePayment($order);
        $order->refresh();

        $balanceAfterFirst = $wallet->fresh()->balance;

        // Simulate a duplicate call (e.g. from a second ITN delivery)
        $this->orchestrator->finalizePayment($order);
        $order->refresh();

        $balanceAfterSecond = $wallet->fresh()->balance;

        $this->assertEquals($balanceAfterFirst, $balanceAfterSecond, 'Wallet balance must not change on duplicate finalization');
        // Seed: walletReserved + 500 = 580; debit = 80 → expected 500
        $this->assertEquals(500.0, $balanceAfterSecond);
    }

    // -------------------------------------------------------------------------
    // 4. Registration/order status is not completed twice
    // -------------------------------------------------------------------------

    public function test_order_pay_status_only_set_once(): void
    {
        [$order] = $this->makeOrder(walletReserved: 0, payfastDue: 100);

        $this->orchestrator->finalizePayment($order);
        $this->orchestrator->finalizePayment($order);

        $order->refresh();

        $this->assertSame(1, (int) $order->pay_status);
        $this->assertSame(1, (int) $order->payfast_paid);
    }

    // -------------------------------------------------------------------------
    // 5. PaymentCompleted event dispatched exactly once
    // -------------------------------------------------------------------------

    public function test_payment_completed_event_dispatched_once(): void
    {
        Event::fake([PaymentCompleted::class]);

        [$order] = $this->makeOrder(walletReserved: 0, payfastDue: 100);

        $this->orchestrator->finalizePayment($order);
        $this->orchestrator->finalizePayment($order); // duplicate

        Event::assertDispatchedTimes(PaymentCompleted::class, 1);
    }

    // -------------------------------------------------------------------------
    // 6. initiatePayment preserves reserved amounts
    // -------------------------------------------------------------------------

    public function test_initiate_payment_sets_wallet_reserved_and_payfast_due(): void
    {
        [$order] = $this->makeOrder(walletReserved: 0, payfastDue: 0);

        $this->orchestrator->initiatePayment($order, 30.0, 70.0);

        $order->refresh();

        $this->assertEquals(30.0, (float) $order->wallet_reserved);
        $this->assertEquals(70.0, (float) $order->payfast_amount_due);
        $this->assertFalse((bool) $order->wallet_debited);
    }

    public function test_initiate_payment_is_skipped_for_already_paid_order(): void
    {
        [$order] = $this->makeOrder(walletReserved: 0, payfastDue: 0);

        // Pre-mark as paid
        $order->update(['pay_status' => true, 'payfast_paid' => true]);

        $this->orchestrator->initiatePayment($order, 30.0, 70.0);

        $order->refresh();

        // Should remain 0 — the initiate was skipped
        $this->assertEquals(0.0, (float) $order->wallet_reserved);
    }

    // -------------------------------------------------------------------------
    // 7. cancelPayment zeroes amounts
    // -------------------------------------------------------------------------

    public function test_cancel_payment_zeroes_reserved_amounts(): void
    {
        [$order] = $this->makeOrder(walletReserved: 50, payfastDue: 50);

        $this->orchestrator->cancelPayment($order);

        $order->refresh();
        $this->assertEquals(0.0, (float) $order->wallet_reserved);
        $this->assertEquals(0.0, (float) $order->payfast_amount_due);
    }

    // -------------------------------------------------------------------------
    // 8. Missing wallet throws when wallet is required
    // -------------------------------------------------------------------------

    public function test_finalize_payment_throws_when_wallet_required_but_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('user wallet not found');

        // Create user WITHOUT a wallet
        $user = User::factory()->create();
        $order = RegistrationOrder::create([
            'user_id'            => $user->id,
            'wallet_reserved'    => 50,
            'payfast_amount_due' => 0,
            'wallet_debited'     => false,
            'payfast_paid'       => false,
            'pay_status'         => false,
        ]);

        $this->orchestrator->finalizePayment($order);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Creates a RegistrationOrder with a funded wallet for its user.
     *
     * @return array{RegistrationOrder, Wallet}
     */
    private function makeOrder(float $walletReserved, float $payfastDue): array
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();

        // Pre-fund wallet so debit succeeds
        if ($walletReserved > 0) {
            \App\Models\WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'type'        => 'credit',
                'amount'      => $walletReserved + 500, // plenty of balance
                'source_type' => 'test_seed',
                'source_id'   => 1,
                'meta'        => [],
            ]);
        }

        $order = RegistrationOrder::create([
            'user_id'            => $user->id,
            'wallet_reserved'    => $walletReserved,
            'payfast_amount_due' => $payfastDue,
            'wallet_debited'     => false,
            'payfast_paid'       => false,
            'pay_status'         => false,
        ]);

        return [$order, $wallet];
    }
}
