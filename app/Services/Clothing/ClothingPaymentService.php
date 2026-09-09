<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Domain\Payments\Services\PaymentOrchestrator;
use App\Models\ClothingOrder;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;

final class ClothingPaymentService
{
    public function __construct(private PaymentOrchestrator $payments)
    {
    }

    public function finalizePayfast(int $orderId, string $paymentId, float $receivedAmount): ClothingOrder
    {
        return FinanceMutationScope::run('payment_state_write', function () use ($orderId, $paymentId, $receivedAmount) {
            return DB::transaction(function () use ($orderId, $paymentId, $receivedAmount) {
                $order = ClothingOrder::query()->lockForUpdate()->findOrFail($orderId);
                if ((int) $order->pay_status === 1 || (bool) $order->payfast_paid) return $order;

                $expected = round((float) $order->payfast_amount_due, 2);
                if ($expected <= 0 || abs(round($receivedAmount, 2) - $expected) > 0.01) {
                    throw new \RuntimeException('PayFast clothing amount does not match the order total.');
                }
                if ($paymentId === '') throw new \RuntimeException('PayFast payment reference is missing.');
                if (ClothingOrder::where('payfast_pf_payment_id', $paymentId)->whereKeyNot($order->id)->exists()) {
                    throw new \RuntimeException('PayFast payment reference has already been used.');
                }

                $finalized = $this->payments->finalizePayment($order, [
                    'pf_payment_id' => $paymentId,
                    'payfast_amount_due' => $expected,
                    'payment_method' => 'payfast',
                ]);
                $finalized->forceFill([
                    'pf_id' => $paymentId,
                    'paid_at' => $finalized->paid_at ?: now(),
                    'amount_paid' => $receivedAmount,
                ])->save();

                return $finalized;
            });
        });
    }
}
