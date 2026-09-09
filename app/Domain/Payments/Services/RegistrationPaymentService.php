<?php

namespace App\Domain\Payments\Services;

use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;

class RegistrationPaymentService
{
    public function __construct(
        private PaymentOrchestrator $paymentOrchestrator
    ) {
    }

    public function reservePayment(RegistrationOrder $order, float $walletApplied, float $remainingAmount): RegistrationOrder
    {
        /** @var RegistrationOrder $reserved */
        $reserved = $this->paymentOrchestrator->initiatePayment($order, $walletApplied, $remainingAmount);

        return $reserved;
    }

    public function cancelPayment(RegistrationOrder $order): RegistrationOrder
    {
        /** @var RegistrationOrder $cancelled */
        $cancelled = $this->paymentOrchestrator->cancelPayment($order);

        return $cancelled;
    }

    public function finalizePayment(RegistrationOrder $order, array $context = []): RegistrationOrder
    {
        /** @var RegistrationOrder $finalized */
        $finalized = $this->paymentOrchestrator->finalizePayment($order, $context);
        $this->markOrderRegistrationsPaid($finalized, $context['pf_payment_id'] ?? null, $context['user_id'] ?? $finalized->user_id);

        return $finalized;
    }

    public function finalizeWalletPayment(RegistrationOrder $order, array $context = []): RegistrationOrder
    {
        return DB::transaction(function () use ($order, $context) {
            $locked = RegistrationOrder::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($order->id);

            $total = round((float) $locked->items->sum('item_price'), 2);
            $reserved = round((float) $locked->wallet_reserved, 2);
            $payfastDue = round((float) $locked->payfast_amount_due, 2);

            if ($total <= 0 || $payfastDue !== 0.0 || $reserved !== $total) {
                throw new \RuntimeException('Wallet payment cannot complete while an unpaid balance remains.');
            }

            return $this->finalizePayment($locked, $context + [
                'payment_method' => 'wallet',
                'payfast_amount_due' => 0,
            ]);
        });
    }

    public function markOrderRegistrationsPaid(RegistrationOrder $order, ?string $pfPaymentId, ?int $userId = null): void
    {
        FinanceMutationScope::run('registration_payment_state_write', function () use ($order, $pfPaymentId, $userId) {
            DB::transaction(function () use ($order, $pfPaymentId, $userId) {
                $lockedOrder = RegistrationOrder::query()
                    ->lockForUpdate()
                    ->with('items')
                    ->findOrFail($order->id);

                foreach ($lockedOrder->items as $item) {
                    $registration = Registration::find($item->registration_id);
                    if (!$registration) {
                        continue;
                    }

                    $registration->players()->syncWithoutDetaching([$item->player_id]);
                    $registration->categoryEvents()->syncWithoutDetaching([
                        $item->category_event_id => [
                            'payment_status_id' => 1,
                            'user_id' => $userId,
                            'pf_transaction_id' => $pfPaymentId,
                        ],
                    ]);
                }
            });
        });
    }

    public function markFreeOrderPaid(RegistrationOrder $order): RegistrationOrder
    {
        return FinanceMutationScope::run(
            ['payment_state_write', 'registration_payment_state_write'],
            function () use ($order) {
                return DB::transaction(function () use ($order) {
                    $lockedOrder = RegistrationOrder::query()
                        ->lockForUpdate()
                        ->with('items')
                        ->findOrFail($order->id);

                    foreach ($lockedOrder->items as $item) {
                        $registration = Registration::find($item->registration_id);
                        if ($registration) {
                            $registration->categoryEvents()->updateExistingPivot($item->category_event_id, [
                                'payment_status_id' => 1,
                            ]);
                        }
                    }

                    $lockedOrder->pay_status = 1;
                    $lockedOrder->payfast_paid = false;
                    $lockedOrder->wallet_debited = false;
                    $lockedOrder->wallet_reserved = 0;
                    $lockedOrder->payfast_amount_due = 0;
                    $lockedOrder->payment_method = 'free';
                    if (array_key_exists('status', $lockedOrder->getAttributes())) {
                        $lockedOrder->status = 'completed';
                    }
                    $lockedOrder->save();

                    return $lockedOrder;
                });
            }
        );
    }
}
