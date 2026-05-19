<?php

namespace Tests\Unit;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;
use App\Services\Wallet\Exceptions\InsufficientFundsException;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WalletService – Financial Safety Test Suite
 *
 * Proves that:
 *   1. Wallet debit succeeds once.
 *   2. Duplicate debit is prevented (idempotency guard throws).
 *   3. Insufficient balance fails safely (balance unchanged).
 *   4. Rollback leaves no partial transaction.
 *   5. Every debit/credit produces exactly one ledger entry.
 */
class WalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WalletService::class);
    }

    // -------------------------------------------------------------------------
    // 1. Wallet debit succeeds once
    // -------------------------------------------------------------------------

    public function test_wallet_debit_succeeds_once_and_produces_ledger_entry(): void
    {
        $wallet = Wallet::factory()->create();
        $this->fundWallet($wallet, 200);

        $this->service->debit($wallet, 50, 'order', 1);

        $this->assertEquals(-50.0 + 200.0, $wallet->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'debit')
            ->count());
    }

    public function test_wallet_credit_succeeds_once_and_produces_ledger_entry(): void
    {
        $wallet = Wallet::factory()->create();

        $this->service->credit($wallet, 100, 'refund', 1);

        $this->assertEquals(100.0, $wallet->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->count());
    }

    // -------------------------------------------------------------------------
    // 2. Duplicate debit is ignored / prevented
    // -------------------------------------------------------------------------

    public function test_duplicate_debit_throws_duplicate_transaction_exception(): void
    {
        $this->expectException(DuplicateTransactionException::class);

        $wallet = Wallet::factory()->create();
        $this->fundWallet($wallet, 500);

        $this->service->debit($wallet, 50, 'order', 99);
        // Second call with identical (source_type, source_id) — must throw
        $this->service->debit($wallet, 50, 'order', 99);
    }

    public function test_duplicate_debit_does_not_create_second_ledger_entry(): void
    {
        $wallet = Wallet::factory()->create();
        $this->fundWallet($wallet, 500);

        $this->service->debit($wallet, 50, 'order', 77);

        try {
            $this->service->debit($wallet, 50, 'order', 77);
        } catch (DuplicateTransactionException) {
            // expected
        }

        $this->assertSame(1, WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'debit')
            ->where('source_id', 77)
            ->count());
    }

    public function test_duplicate_credit_throws_duplicate_transaction_exception(): void
    {
        $this->expectException(DuplicateTransactionException::class);

        $wallet = Wallet::factory()->create();
        $this->service->credit($wallet, 100, 'refund', 5);
        $this->service->credit($wallet, 100, 'refund', 5);
    }

    // -------------------------------------------------------------------------
    // 3. Insufficient balance fails safely
    // -------------------------------------------------------------------------

    public function test_debit_with_insufficient_balance_throws_exception(): void
    {
        $this->expectException(InsufficientFundsException::class);

        $wallet = Wallet::factory()->create();
        $this->fundWallet($wallet, 30);

        $this->service->debit($wallet, 100, 'order', 200); // 100 > 30
    }

    public function test_insufficient_balance_leaves_wallet_balance_unchanged(): void
    {
        $wallet = Wallet::factory()->create();
        $this->fundWallet($wallet, 30);

        try {
            $this->service->debit($wallet, 100, 'order', 201);
        } catch (InsufficientFundsException) {
            // expected
        }

        $this->assertEquals(30.0, $wallet->fresh()->balance);
    }

    // -------------------------------------------------------------------------
    // 4. Rollback leaves no partial transaction
    // -------------------------------------------------------------------------

    public function test_failed_debit_does_not_create_ledger_entry(): void
    {
        $wallet = Wallet::factory()->create();
        // Wallet is empty — debit will fail

        try {
            $this->service->debit($wallet, 50, 'order', 300);
        } catch (InsufficientFundsException) {
            // expected
        }

        $this->assertSame(
            0,
            WalletTransaction::where('wallet_id', $wallet->id)->count(),
            'No ledger entry should exist when debit fails due to insufficient funds'
        );
    }

    public function test_failed_debit_does_not_alter_balance(): void
    {
        $wallet = Wallet::factory()->create();
        $this->fundWallet($wallet, 10);

        try {
            $this->service->debit($wallet, 999, 'order', 400);
        } catch (InsufficientFundsException) {
            // expected
        }

        $this->assertEquals(10.0, $wallet->fresh()->balance);
    }

    // -------------------------------------------------------------------------
    // 5. Every debit has a ledger entry (no direct balance mutation)
    // -------------------------------------------------------------------------

    public function test_balance_is_always_derived_from_ledger(): void
    {
        $wallet = Wallet::factory()->create();

        // Apply three credits and one debit
        $this->service->credit($wallet, 100, 'top_up', 1);
        $this->service->credit($wallet, 50,  'bonus',  2);
        $this->service->debit($wallet, 30,   'order',  3);

        // Balance must equal sum of ledger
        $ledgerBalance = WalletTransaction::where('wallet_id', $wallet->id)
            ->get()
            ->reduce(function (float $carry, WalletTransaction $tx) {
                return $tx->type === 'credit'
                    ? $carry + (float) $tx->amount
                    : $carry - (float) $tx->amount;
            }, 0.0);

        $this->assertEquals(round($ledgerBalance, 2), round($wallet->fresh()->balance, 2));
        $this->assertSame(3, WalletTransaction::where('wallet_id', $wallet->id)->count());
    }

    // -------------------------------------------------------------------------
    // 6. Invalid amounts rejected
    // -------------------------------------------------------------------------

    public function test_debit_zero_throws_invalid_argument(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $wallet = Wallet::factory()->create();
        $this->service->debit($wallet, 0, 'order', 1);
    }

    public function test_credit_zero_throws_invalid_argument(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $wallet = Wallet::factory()->create();
        $this->service->credit($wallet, 0, 'refund', 1);
    }

    public function test_debit_negative_throws_invalid_argument(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $wallet = Wallet::factory()->create();
        $this->service->debit($wallet, -10, 'order', 1);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function fundWallet(Wallet $wallet, float $amount): void
    {
        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'credit',
            'amount'      => $amount,
            'source_type' => 'test_seed',
            'source_id'   => 0,
            'meta'        => [],
        ]);
    }
}
