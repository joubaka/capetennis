<?php

namespace App\Domain\Refunds\Services;

use App\Domain\Payments\Services\LedgerService;
use App\Events\RefundCompleted;
use App\Models\Wallet;
use App\Support\FinanceMutationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RefundExecutionService
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    public function executeWalletRefund(
        Model $refundEntity,
        Wallet $wallet,
        float $amount,
        string $sourceType,
        int $sourceId,
        array $meta = [],
        array $statusOverrides = []
    ): Model {
        $entityClass = get_class($refundEntity);

        $transitioned = false;
        $completed = FinanceMutationScope::run('refund_state_write', function () use (
            $refundEntity,
            $entityClass,
            $wallet,
            $amount,
            $sourceType,
            $sourceId,
            $meta,
            $statusOverrides,
            &$transitioned
        ) {
            return DB::transaction(function () use (
                $refundEntity,
                $entityClass,
                $wallet,
                $amount,
                $sourceType,
                $sourceId,
                $meta,
                $statusOverrides,
                &$transitioned
            ) {
                /** @var Model $locked */
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());

                if (($locked->refund_status ?? null) === 'completed') {
                    return $locked;
                }

                $transitioned = true;
                $this->ledgerService->appendWalletCredit($wallet, $amount, $sourceType, $sourceId, $meta);

                if ($this->supportsAttribute($locked, 'refund_method')) {
                    $locked->refund_method = $statusOverrides['refund_method'] ?? ($locked->refund_method ?? 'wallet');
                }
                if ($this->supportsAttribute($locked, 'refund_status')) {
                    $locked->refund_status = 'completed';
                }
                if ($this->supportsAttribute($locked, 'refunded_at')) {
                    $locked->refunded_at = now();
                }

                if (array_key_exists('refund_gross', $statusOverrides)) {
                    $locked->refund_gross = $statusOverrides['refund_gross'];
                }
                if (array_key_exists('refund_fee', $statusOverrides)) {
                    $locked->refund_fee = $statusOverrides['refund_fee'];
                }
                if (array_key_exists('refund_net', $statusOverrides)) {
                    $locked->refund_net = $statusOverrides['refund_net'];
                }

                $locked->save();

                return $locked;
            });
        });

        if ($transitioned) {
            $payload = ['type' => 'wallet'] + $meta;
            $dispatchFn = function () use ($completed, $payload) {
                event(new RefundCompleted($completed, $payload));
            };
            app()->runningUnitTests()
                ? $dispatchFn()
                : DB::afterCommit($dispatchFn);
        }

        return $completed;
    }

    public function executeBankRefund(Model $refundEntity, array $statusOverrides = []): Model
    {
        $entityClass = get_class($refundEntity);

        $transitioned = false;
        $completed = FinanceMutationScope::run('refund_state_write', function () use ($refundEntity, $entityClass, $statusOverrides, &$transitioned) {
            return DB::transaction(function () use ($refundEntity, $entityClass, $statusOverrides, &$transitioned) {
                /** @var Model $locked */
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());

                if (($locked->refund_status ?? null) === 'completed') {
                    return $locked;
                }

                $transitioned = true;
                if ($this->supportsAttribute($locked, 'refund_method')) {
                    $locked->refund_method = $statusOverrides['refund_method'] ?? ($locked->refund_method ?? 'bank');
                }
                if ($this->supportsAttribute($locked, 'refund_status')) {
                    $locked->refund_status = 'completed';
                }
                if ($this->supportsAttribute($locked, 'refunded_at')) {
                    $locked->refunded_at = now();
                }

                if (array_key_exists('refund_gross', $statusOverrides)) {
                    $locked->refund_gross = $statusOverrides['refund_gross'];
                }
                if (array_key_exists('refund_fee', $statusOverrides)) {
                    $locked->refund_fee = $statusOverrides['refund_fee'];
                }
                if (array_key_exists('refund_net', $statusOverrides)) {
                    $locked->refund_net = $statusOverrides['refund_net'];
                }

                $locked->save();

                return $locked;
            });
        });

        if ($transitioned) {
            $dispatchFn = function () use ($completed) {
                event(new RefundCompleted($completed, ['type' => 'bank']));
            };
            app()->runningUnitTests()
                ? $dispatchFn()
                : DB::afterCommit($dispatchFn);
        }

        return $completed;
    }
    private function supportsAttribute(Model $model, string $attribute): bool
    {
        return array_key_exists($attribute, $model->getAttributes())
            || in_array($attribute, $model->getFillable(), true);
    }
}
