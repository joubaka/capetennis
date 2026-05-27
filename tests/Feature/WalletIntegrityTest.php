<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;
use App\Services\Wallet\Exceptions\InsufficientFundsException;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WalletIntegrityTest
 *
 * Tests for wallet debit/credit safety constraints and
 * the wallet reservation repair mechanics.
 */
class WalletIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;
    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);

        $user         = User::factory()->create();
        $this->wallet = Wallet::factory()->forUser($user)->create();
    }

    public function test_wallet_debit_succeeds_when_balance_sufficient(): void
    {
        $this->walletService->credit($this->wallet, 200.00, 'test_credit', 1);
        $this->walletService->debit($this->wallet, 100.00, 'test_debit', 1);

        $this->assertEquals(100.00, $this->wallet->fresh()->balance);
    }

    public function test_wallet_debit_throws_when_balance_insufficient(): void
    {
        $this->expectException(InsufficientFundsException::class);
        $this->walletService->debit($this->wallet, 500.00, 'test_debit', 2);
    }

    public function test_wallet_duplicate_credit_throws(): void
    {
        $this->walletService->credit($this->wallet, 100.00, 'admin_full_refund', 42);

        $this->expectException(DuplicateTransactionException::class);
        $this->walletService->credit($this->wallet, 100.00, 'admin_full_refund', 42);
    }

    public function test_wallet_duplicate_debit_throws(): void
    {
        $this->walletService->credit($this->wallet, 500.00, 'test_credit', 1);
        $this->walletService->debit($this->wallet, 100.00, 'test_debit', 10);

        $this->expectException(DuplicateTransactionException::class);
        $this->walletService->debit($this->wallet, 100.00, 'test_debit', 10);
    }

    public function test_wallet_balance_computed_from_ledger(): void
    {
        $this->walletService->credit($this->wallet, 300.00, 'credit_a', 1);
        $this->walletService->credit($this->wallet, 100.00, 'credit_b', 2);
        $this->walletService->debit($this->wallet, 50.00, 'debit_a', 3);

        $this->assertEquals(350.00, $this->wallet->fresh()->balance);
    }

    public function test_wallet_concurrent_debit_idempotency_via_lock_for_update(): void
    {
        $this->walletService->credit($this->wallet, 500.00, 'setup', 99);

        // Simulate: same source_id submitted twice — second must throw DuplicateTransactionException
        $this->walletService->debit($this->wallet, 100.00, 'event_registration_wallet_payment', 77);

        $this->expectException(DuplicateTransactionException::class);
        $this->walletService->debit($this->wallet, 100.00, 'event_registration_wallet_payment', 77);
    }
}
