<?php

namespace App\Domain\Payments\Services;

use App\Events\WalletCredited;
use App\Events\WalletDebited;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\FinanceMutationScope;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function __construct(private WalletService $walletService)
    {
    }

    public function appendWalletCredit(
        Wallet $wallet,
        float $amount,
        string $sourceType,
        int $sourceId,
        array $meta = []
    ): WalletTransaction {
        $transaction = FinanceMutationScope::run(['wallet_transaction_write', 'ledger_write'], function () use (
            $wallet,
            $amount,
            $sourceType,
            $sourceId,
            $meta
        ) {
            return $this->walletService->credit($wallet, $amount, $sourceType, $sourceId, $meta);
        });

        DB::afterCommit(function () use ($transaction, $sourceType, $sourceId, $meta) {
            event(new WalletCredited($transaction, ['source_type' => $sourceType, 'source_id' => $sourceId] + $meta));
        });

        return $transaction;
    }

    public function appendWalletDebit(
        Wallet $wallet,
        float $amount,
        string $sourceType,
        int $sourceId,
        array $meta = []
    ): WalletTransaction {
        $transaction = FinanceMutationScope::run(['wallet_transaction_write', 'ledger_write'], function () use (
            $wallet,
            $amount,
            $sourceType,
            $sourceId,
            $meta
        ) {
            return $this->walletService->debit($wallet, $amount, $sourceType, $sourceId, $meta);
        });

        DB::afterCommit(function () use ($transaction, $sourceType, $sourceId, $meta) {
            event(new WalletDebited($transaction, ['source_type' => $sourceType, 'source_id' => $sourceId] + $meta));
        });

        return $transaction;
    }

    public function auditReference(Model $model, array $context = []): string
    {
        $class = class_basename($model);
        $id = $model->getKey();

        return "{$class}:{$id}:" . md5(json_encode($context));
    }
}
