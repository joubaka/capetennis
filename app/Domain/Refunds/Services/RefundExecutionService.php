<?php

namespace App\Domain\Refunds\Services;

use App\Domain\Payments\Services\LedgerService;
use App\Events\RefundCompleted;
use App\Models\User;
use App\Models\Wallet;
use App\Support\FinanceMutationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /**
     * Record a refund that PayFast has already completed outside this app.
     *
     * This method never contacts PayFast or moves money. It only reconciles an
     * exact, independently verified reversal against the paid withdrawn entry.
     */
    public function recordExternalPayfastRefund(
        Model $refundEntity,
        string $pfPaymentId,
        float $grossAmount,
        string $refundedAt,
        ?User $actor = null,
        array $evidence = []
    ): Model {
        $entityClass = get_class($refundEntity);
        $expectedGross = round($grossAmount, 2);
        $effectiveAt = CarbonImmutable::parse($refundedAt);
        $transitioned = false;

        if ($expectedGross <= 0) {
            throw ValidationException::withMessages(['refund' => 'External refund amount must be positive.']);
        }

        $completed = FinanceMutationScope::run('refund_state_write', function () use (
            $entityClass,
            $refundEntity,
            $pfPaymentId,
            $expectedGross,
            $effectiveAt,
            &$transitioned
        ) {
            return DB::transaction(function () use (
                $entityClass,
                $refundEntity,
                $pfPaymentId,
                $expectedGross,
                $effectiveAt,
                &$transitioned
            ) {
                /** @var Model $locked */
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());

                if (($locked->refund_status ?? null) === 'completed') {
                    $sameRefund = ($locked->refund_method ?? null) === 'payfast'
                        && round((float) ($locked->refund_gross ?? 0), 2) === $expectedGross
                        && (string) ($locked->pf_transaction_id ?? '') === $pfPaymentId;
                    if (! $sameRefund) {
                        throw ValidationException::withMessages(['refund' => 'A different completed refund is already recorded.']);
                    }

                    return $locked;
                }

                if (($locked->status ?? null) !== 'withdrawn'
                    || (int) ($locked->payment_status_id ?? 0) !== 1
                    || (string) ($locked->pf_transaction_id ?? '') !== $pfPaymentId) {
                    throw ValidationException::withMessages([
                        'refund' => 'External PayFast evidence does not match a paid withdrawn entry.',
                    ]);
                }

                $transitioned = true;
                $locked->refund_method = 'payfast';
                $locked->refund_status = 'completed';
                $locked->refund_gross = $expectedGross;
                $locked->refund_fee = 0;
                $locked->refund_net = $expectedGross;
                $locked->refunded_at = $effectiveAt;
                $locked->save();

                return $locked;
            });
        });

        if ($transitioned) {
            $payload = ['type' => 'external_payfast', 'pf_payment_id' => $pfPaymentId] + $evidence;
            $activity = activity('refund')->performedOn($completed);
            if ($actor) {
                $activity->causedBy($actor);
            }
            $activity->withProperties($payload + [
                    'refund_gross' => $expectedGross,
                    'refunded_at' => $effectiveAt->toDateTimeString(),
                ])
                ->log('Reconciled externally completed PayFast refund');
            $dispatchFn = fn () => event(new RefundCompleted($completed, $payload));
            app()->runningUnitTests() ? $dispatchFn() : DB::afterCommit($dispatchFn);
        }

        return $completed;
    }

    /**
     * Close a pending refund without moving money.
     *
     * A waiver is deliberately distinct from a completed refund: refunded_at
     * remains null and no RefundCompleted event or ledger entry is created.
     */
    public function waiveRefund(Model $refundEntity, int $waivedBy, string $reason): Model
    {
        $entityClass = get_class($refundEntity);

        return FinanceMutationScope::run('refund_state_write', function () use ($entityClass, $refundEntity, $waivedBy, $reason) {
            return DB::transaction(function () use ($entityClass, $refundEntity, $waivedBy, $reason) {
                /** @var Model $locked */
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());

                if (($locked->refund_status ?? null) !== 'pending') {
                    throw ValidationException::withMessages([
                        'refund' => 'Only a pending refund can be waived.',
                    ]);
                }

                $locked->fill([
                    'refund_status' => 'waived',
                    'refund_waived_at' => now(),
                    'refund_waived_by' => $waivedBy,
                    'refund_waiver_reason' => trim($reason),
                    'refunded_at' => null,
                ]);
                $locked->save();

                return $locked;
            });
        });
    }

    public function executeSplitRefund(
        Model $refundEntity,
        ?Wallet $wallet,
        float $walletAmount,
        string $sourceType,
        int $sourceId,
        array $meta = [],
        array $statusOverrides = []
    ): Model {
        $entityClass = get_class($refundEntity);

        return FinanceMutationScope::run('refund_state_write', function () use ($entityClass, $refundEntity, $wallet, $walletAmount, $sourceType, $sourceId, $meta, $statusOverrides) {
            return DB::transaction(function () use ($entityClass, $refundEntity, $wallet, $walletAmount, $sourceType, $sourceId, $meta, $statusOverrides) {
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());
                if (($locked->refund_status ?? null) === 'completed') {
                    return $locked;
                }

                if ($walletAmount > 0) {
                    if (! $wallet) {
                        throw new \RuntimeException('Wallet not found for split refund.');
                    }
                    $this->ledgerService->appendWalletCredit($wallet, $walletAmount, $sourceType, $sourceId, $meta);
                }

                $locked->fill($statusOverrides + [
                    'refund_method' => 'payfast',
                    'refund_status' => 'completed',
                    'refunded_at' => now(),
                ]);
                $locked->refund_status = 'completed';
                $locked->refunded_at = now();
                $locked->save();

                return $locked;
            });
        });
    }
    private function supportsAttribute(Model $model, string $attribute): bool
    {
        return array_key_exists($attribute, $model->getAttributes())
            || in_array($attribute, $model->getFillable(), true);
    }
}
