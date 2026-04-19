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

    public function test_credit_creates_a_wallet_transaction(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 0]);

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

    public function test_credit_rejects_non_positive_amount(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        $this->expectException(\InvalidArgumentException::class);

        $this->walletService->credit($wallet, 0, 'test', 1);
    }

    public function test_credit_throws_on_duplicate_transaction(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 0]);

        // First credit succeeds
        $this->walletService->credit($wallet, 50.00, 'order', 999);

        // Same source_type + source_id → duplicate
        $this->expectException(DuplicateTransactionException::class);
        $this->walletService->credit($wallet, 50.00, 'order', 999);
    }

    public function test_debit_creates_a_debit_transaction(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 200]);

        $transaction = $this->walletService->debit(
            $wallet,
            75.00,
            'refund',
            42
        );

        $this->assertEquals('debit', $transaction->type);
        $this->assertEquals(75.00, $transaction->amount);
    }

    public function test_debit_throws_on_insufficient_funds(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 10]);

        $this->expectException(InsufficientFundsException::class);

        $this->walletService->debit($wallet, 50.00, 'refund', 1);
    }

    public function test_debit_rejects_non_positive_amount(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        $this->expectException(\InvalidArgumentException::class);

        $this->walletService->debit($wallet, -5.00, 'refund', 1);
    }
}
