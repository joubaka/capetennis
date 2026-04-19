<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\Exceptions\InsufficientFundsException;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = new WalletService();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Create a wallet and seed it with a credit transaction so balance > 0. */
    private function walletWithBalance(float $amount): Wallet
    {
        $wallet = Wallet::factory()->create();
        if ($amount > 0) {
            WalletTransaction::factory()->credit()->create([
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'source_type' => 'seed',
                'source_id' => rand(10000, 99999),
            ]);
        }
        return $wallet;
    }

    // -----------------------------------------------------------------------
    // credit()
    // -----------------------------------------------------------------------

    public function test_credit_creates_a_wallet_transaction(): void
    {
        $wallet = Wallet::factory()->create();

        $transaction = $this->walletService->credit(
            $wallet,
            100.00,
            'test',
            1,
            ['note' => 'Test credit']
        );

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('credit', $transaction->type);
        $this->assertEquals(100.00, $transaction->amount);
        $this->assertEquals($wallet->id, $transaction->wallet_id);
    }

    public function test_credit_persists_to_database(): void
    {
        $wallet = Wallet::factory()->create();

        $this->walletService->credit($wallet, 200.00, 'order', 1);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => 200.00,
            'source_type' => 'order',
            'source_id' => 1,
        ]);
    }

    public function test_credit_increases_computed_balance(): void
    {
        $wallet = Wallet::factory()->create();

        $this->walletService->credit($wallet, 150.00, 'order', 5);

        $this->assertEquals(150.00, $wallet->balance);
    }

    public function test_credit_rejects_non_positive_amount(): void
    {
        $wallet = Wallet::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit amount must be positive');

        $this->walletService->credit($wallet, 0, 'test', 1);
    }

    public function test_credit_rejects_negative_amount(): void
    {
        $wallet = Wallet::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->walletService->credit($wallet, -10.00, 'test', 1);
    }

    public function test_credit_throws_on_duplicate_transaction(): void
    {
        $wallet = Wallet::factory()->create();

        // First credit succeeds
        $this->walletService->credit($wallet, 50.00, 'order', 999);

        // Same source_type + source_id → duplicate
        $this->expectException(DuplicateTransactionException::class);
        $this->walletService->credit($wallet, 50.00, 'order', 999);
    }

    public function test_credit_allows_different_source_ids(): void
    {
        $wallet = Wallet::factory()->create();

        $this->walletService->credit($wallet, 50.00, 'order', 1);
        $this->walletService->credit($wallet, 50.00, 'order', 2); // different source_id → ok

        $this->assertEquals(100.00, $wallet->balance);
    }

    public function test_credit_stores_meta(): void
    {
        $wallet = Wallet::factory()->create();

        $tx = $this->walletService->credit(
            $wallet,
            50.00,
            'order',
            7,
            ['note' => 'Birthday bonus', 'ref' => 'BX-001']
        );

        $this->assertEquals('Birthday bonus', $tx->meta['note']);
        $this->assertEquals('BX-001', $tx->meta['ref']);
    }

    // -----------------------------------------------------------------------
    // debit()
    // -----------------------------------------------------------------------

    public function test_debit_creates_a_debit_transaction(): void
    {
        $wallet = $this->walletWithBalance(200);

        $transaction = $this->walletService->debit(
            $wallet,
            75.00,
            'refund',
            42
        );

        $this->assertEquals('debit', $transaction->type);
        $this->assertEquals(75.00, $transaction->amount);
    }

    public function test_debit_persists_to_database(): void
    {
        $wallet = $this->walletWithBalance(100);

        $this->walletService->debit($wallet, 40.00, 'withdrawal', 3);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => 40.00,
            'source_type' => 'withdrawal',
            'source_id' => 3,
        ]);
    }

    public function test_debit_reduces_computed_balance(): void
    {
        $wallet = $this->walletWithBalance(200);

        $this->walletService->debit($wallet, 60.00, 'refund', 10);

        $this->assertEquals(140.00, $wallet->balance);
    }

    public function test_debit_throws_on_insufficient_funds(): void
    {
        $wallet = $this->walletWithBalance(10);

        $this->expectException(InsufficientFundsException::class);

        $this->walletService->debit($wallet, 50.00, 'refund', 1);
    }

    public function test_debit_rejects_non_positive_amount(): void
    {
        $wallet = $this->walletWithBalance(100);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Debit amount must be positive');

        $this->walletService->debit($wallet, -5.00, 'refund', 1);
    }

    public function test_debit_rejects_zero_amount(): void
    {
        $wallet = $this->walletWithBalance(100);

        $this->expectException(\InvalidArgumentException::class);

        $this->walletService->debit($wallet, 0, 'refund', 1);
    }

    public function test_debit_throws_on_duplicate_debit_transaction(): void
    {
        $wallet = $this->walletWithBalance(500);

        $this->walletService->debit($wallet, 50.00, 'refund', 1);

        $this->expectException(DuplicateTransactionException::class);
        $this->walletService->debit($wallet, 50.00, 'refund', 1);
    }

    // -----------------------------------------------------------------------
    // Balance accuracy
    // -----------------------------------------------------------------------

    public function test_balance_reflects_multiple_credits_and_debits(): void
    {
        $wallet = Wallet::factory()->create();

        $this->walletService->credit($wallet, 100.00, 'order', 1);
        $this->walletService->credit($wallet, 50.00, 'order', 2);
        $this->walletService->debit($wallet, 30.00, 'refund', 10);

        $this->assertEquals(120.00, $wallet->balance);
    }
}

