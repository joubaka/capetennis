<?php

namespace App\Domain\Payments\Services;

use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Models\TeamPaymentOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentOrchestrator
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    public function initiatePayment(Model $order, float $walletApplied, float $remainingAmount): Model
    {
        $orderClass = get_class($order);

        return DB::transaction(function () use ($order, $orderClass, $walletApplied, $remainingAmount) {
            /** @var Model $locked */
            $locked = $orderClass::query()->lockForUpdate()->findOrFail($order->getKey());

            if ((int) ($locked->pay_status ?? 0) === 1 || (bool) ($locked->payfast_paid ?? false)) {
                return $locked;
            }

            $locked->wallet_reserved = round($walletApplied, 2);
            $locked->payfast_amount_due = round($remainingAmount, 2);
            $locked->wallet_debited = false;
            $locked->save();

            return $locked;
        });
    }

    public function finalizePayment(Model $order, array $context = []): Model
    {
        $orderClass = get_class($order);

        try {
            $finalized = DB::transaction(function () use ($order, $orderClass, $context) {
                /** @var Model $locked */
                $locked = $orderClass::query()->lockForUpdate()->with('user.wallet')->findOrFail($order->getKey());

                if ((int) ($locked->pay_status ?? 0) === 1 || (bool) ($locked->payfast_paid ?? false)) {
                    return $locked;
                }

                $walletReserved = (float) ($locked->wallet_reserved ?? 0);
                if ($walletReserved > 0 && !(bool) ($locked->wallet_debited ?? false)) {
                    $wallet = $locked->user?->wallet;
                    if (!$wallet) {
                        throw new \RuntimeException("Wallet missing for payment order {$locked->getKey()}");
                    }

                    $this->ledgerService->appendWalletDebit(
                        $wallet,
                        $walletReserved,
                        $context['wallet_source_type'] ?? $this->defaultWalletSourceType($locked),
                        (int) $locked->getKey(),
                        $context['wallet_meta'] ?? ['order_id' => $locked->getKey()]
                    );

                    $locked->wallet_debited = true;
                }

                $locked->pay_status = 1;
                $locked->payfast_paid = true;

                if (array_key_exists('pf_payment_id', $context)) {
                    $locked->payfast_pf_payment_id = $context['pf_payment_id'];
                }

                if (array_key_exists('payfast_amount_due', $context)) {
                    $locked->payfast_amount_due = round((float) $context['payfast_amount_due'], 2);
                }

                $locked->save();

                return $locked;
            });
        } catch (\Throwable $e) {
            event(new PaymentFailed($order, $context, $e->getMessage()));
            throw $e;
        }

        event(new PaymentCompleted($finalized, $context));

        return $finalized;
    }

    public function cancelPayment(Model $order): Model
    {
        $orderClass = get_class($order);

        return DB::transaction(function () use ($order, $orderClass) {
            /** @var Model $locked */
            $locked = $orderClass::query()->lockForUpdate()->findOrFail($order->getKey());
            $locked->wallet_reserved = 0;
            $locked->payfast_amount_due = 0;
            $locked->save();

            return $locked;
        });
    }

    public function reconcilePayment(Model $order): array
    {
        $walletReserved = round((float) ($order->wallet_reserved ?? 0), 2);
        $due = round((float) ($order->payfast_amount_due ?? 0), 2);
        $isPaid = (int) ($order->pay_status ?? 0) === 1 || (bool) ($order->payfast_paid ?? false);

        return [
            'order_id' => $order->getKey(),
            'wallet_reserved' => $walletReserved,
            'payfast_amount_due' => $due,
            'is_paid' => $isPaid,
            'requires_wallet_debit' => $walletReserved > 0 && !(bool) ($order->wallet_debited ?? false),
        ];
    }

    private function defaultWalletSourceType(Model $order): string
    {
        return $order instanceof TeamPaymentOrder
            ? 'team_registration_wallet_payment'
            : 'event_registration_wallet_payment';
    }
}

