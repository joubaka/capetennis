<?php

namespace Tests\Unit;

use App\Services\Wallet\WalletService;
use Tests\TestCase;

class WalletServiceUnitTest extends TestCase
{
    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = new WalletService();
    }

    public function test_wallet_service_instantiates(): void
    {
        $this->assertInstanceOf(WalletService::class, $this->walletService);
    }

    public function test_credit_throws_for_zero_amount(): void
    {
        $wallet = new \App\Models\Wallet(['balance' => 100]);
        $wallet->id = 1;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit amount must be positive');

        $this->walletService->credit($wallet, 0, 'test', 1);
    }

    public function test_credit_throws_for_negative_amount(): void
    {
        $wallet = new \App\Models\Wallet(['balance' => 100]);
        $wallet->id = 1;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit amount must be positive');

        $this->walletService->credit($wallet, -10, 'test', 1);
    }

    public function test_debit_throws_for_zero_amount(): void
    {
        $wallet = new \App\Models\Wallet(['balance' => 100]);
        $wallet->id = 1;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Debit amount must be positive');

        $this->walletService->debit($wallet, 0, 'test', 1);
    }
}
