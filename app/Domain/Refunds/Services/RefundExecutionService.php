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

        $completed = FinanceMutationScope::run('refund_state_write', function () use (
            $refundEntity,
            $entityClass,
            $wallet,
            $amount,
            $sourceType,
            $sourceId,
            $meta,
            $statusOverrides
        ) {
            return DB::transaction(function () use (
                $refundEntity,
                $entityClass,
                $wallet,
                $amount,
                $sourceType,
                $sourceId,
                $meta,
                $statusOverrides
            ) {
                /** @var Model $locked */
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());

                if (($locked->refund_status ?? null) === 'completed') {
                    return $locked;
                }

                $this->ledgerService->appendWalletCredit($wallet, $amount, $sourceType, $sourceId, $meta);

                $locked->refund_method = $statusOverrides['refund_method'] ?? ($locked->refund_method ?? 'wallet');
                $locked->refund_status = 'completed';
                $locked->refunded_at = now();

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

        DB::afterCommit(function () use ($completed, $meta) {
            event(new RefundCompleted($completed, ['type' => 'wallet'] + $meta));
        });

        return $completed;
    }

    public function executeBankRefund(Model $refundEntity, array $statusOverrides = []): Model
    {
        $entityClass = get_class($refundEntity);

        $completed = FinanceMutationScope::run('refund_state_write', function () use ($refundEntity, $entityClass, $statusOverrides) {
            return DB::transaction(function () use ($refundEntity, $entityClass, $statusOverrides) {
                /** @var Model $locked */
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());

                if (($locked->refund_status ?? null) === 'completed') {
                    return $locked;
                }

                $locked->refund_method = $statusOverrides['refund_method'] ?? ($locked->refund_method ?? 'bank');
                $locked->refund_status = 'completed';
                $locked->refunded_at = now();

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

        DB::afterCommit(function () use ($completed) {
            event(new RefundCompleted($completed, ['type' => 'bank']));
        });

        return $completed;
    }
}
