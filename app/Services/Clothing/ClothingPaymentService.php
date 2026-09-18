<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Domain\Payments\Services\PaymentOrchestrator;
use App\Models\ClothingOrder;
use App\Models\TeamSelectionInvitation;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClothingPaymentService
{
    public function __construct(private PaymentOrchestrator $payments)
    {
    }

    public function finalizePayfast(int $orderId, string $paymentId, float $receivedAmount): ClothingOrder
    {
        return FinanceMutationScope::run('payment_state_write', function () use ($orderId, $paymentId, $receivedAmount) {
            return DB::transaction(function () use ($orderId, $paymentId, $receivedAmount) {
                $orderSnapshot = ClothingOrder::query()->findOrFail($orderId);
                $invitation = TeamSelectionInvitation::query()
                    ->where('event_id', $orderSnapshot->event_id)
                    ->where('team_id', $orderSnapshot->team_id)
                    ->where('player_id', $orderSnapshot->player_id)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();
                if ($invitation?->clothing_decision === 'not_required') {
                    throw ValidationException::withMessages(['payment' => 'The player has confirmed that no clothing is required.']);
                }

                $order = ClothingOrder::query()->lockForUpdate()->findOrFail($orderId);
                if ((int) $order->event_id !== (int) $orderSnapshot->event_id
                    || (int) $order->team_id !== (int) $orderSnapshot->team_id
                    || (int) $order->player_id !== (int) $orderSnapshot->player_id) {
                    throw new \RuntimeException('The clothing order relationship changed during payment finalization.');
                }
                $expected = round((float) $order->payfast_amount_due, 2);
                if ($expected <= 0 || abs(round($receivedAmount, 2) - $expected) > 0.01) {
                    throw new \RuntimeException('PayFast clothing amount does not match the order total.');
                }
                if ($paymentId === '') throw new \RuntimeException('PayFast payment reference is missing.');
                if ((int) $order->pay_status === 1 || (bool) $order->payfast_paid) {
                    if (($order->payfast_pf_payment_id ?: $order->pf_id) !== $paymentId) {
                        throw new \RuntimeException('Paid clothing order has a different PayFast reference.');
                    }
                    return $order;
                }
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
