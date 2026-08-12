<?php

namespace Tests\Unit;

use App\Domain\Refunds\Services\TeamRefundCalculator;
use App\Models\TeamPaymentOrder;
use Tests\TestCase;

class TeamRefundCalculatorTest extends TestCase
{
    public function test_wallet_only_refund_stays_in_wallet(): void
    {
        $result = app(TeamRefundCalculator::class)->calculate(new TeamPaymentOrder([
            'total_amount' => 285,
            'wallet_reserved' => 285,
            'wallet_debited' => true,
        ]));

        $this->assertSame(28.5, $result['fee']);
        $this->assertEquals(0.0, $result['payfastNet']);
        $this->assertSame(256.5, $result['walletNet']);
    }

    public function test_payfast_only_refund_is_capped_to_payfast_payment(): void
    {
        $result = app(TeamRefundCalculator::class)->calculate(new TeamPaymentOrder([
            'total_amount' => 285,
            'payfast_amount_due' => 285,
            'payfast_paid' => true,
        ]));

        $this->assertSame(256.5, $result['payfastNet']);
        $this->assertEquals(0.0, $result['walletNet']);
    }

    public function test_hybrid_refund_returns_each_source_without_over_refunding(): void
    {
        $result = app(TeamRefundCalculator::class)->calculate(new TeamPaymentOrder([
            'total_amount' => 570,
            'wallet_reserved' => 285,
            'wallet_debited' => true,
            'payfast_amount_due' => 285,
            'payfast_paid' => true,
        ]));

        $this->assertSame(57.0, $result['fee']);
        $this->assertSame(228.0, $result['payfastNet']);
        $this->assertSame(285.0, $result['walletNet']);
        $this->assertSame(513.0, $result['payfastNet'] + $result['walletNet']);
    }
}
