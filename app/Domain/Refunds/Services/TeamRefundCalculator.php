<?php

namespace App\Domain\Refunds\Services;

use App\Models\SiteSetting;
use App\Models\TeamPaymentOrder;

final class TeamRefundCalculator
{
    public function calculate(TeamPaymentOrder $order): array
    {
        $gross = round((float) $order->total_amount, 2);
        $fee = SiteSetting::calculateWithdrawalFee($gross);
        $net = max(0, round($gross - $fee, 2));

        $payfastGross = $order->payfast_paid
            ? min($gross, round((float) $order->payfast_amount_due, 2))
            : 0.0;
        $walletGross = $order->wallet_debited
            ? min($gross - $payfastGross, round((float) $order->wallet_reserved, 2))
            : 0.0;

        $payfastNet = max(0, round($payfastGross - $fee, 2));
        $walletNet = max(0, round($net - $payfastNet, 2));

        return compact('gross', 'fee', 'net', 'payfastGross', 'walletGross', 'payfastNet', 'walletNet');
    }
}
