<?php

namespace App\Services\Wallet;

use App\Domain\Payments\Services\LedgerService;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;

class ManualWalletAdjustmentService
{
    public function __construct(private LedgerService $ledger)
    {
    }

    public function apply(Wallet $wallet, string $type, float $amount, string $idempotencyKey, array $meta = []): WalletTransaction
    {
        $sourceId = $this->sourceId($idempotencyKey);
        $meta['idempotency_key'] = $idempotencyKey;

        try {
            return $type === 'credit'
                ? $this->ledger->appendWalletCredit($wallet, $amount, 'manual', $sourceId, $meta)
                : $this->ledger->appendWalletDebit($wallet, $amount, 'manual', $sourceId, $meta);
        } catch (DuplicateTransactionException $exception) {
            $existing = WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('source_type', 'manual')
                ->where('source_id', $sourceId)
                ->first();

            if ($existing
                && $existing->type === $type
                && abs((float) $existing->amount - $amount) < 0.005
                && data_get($existing->meta, 'idempotency_key') === $idempotencyKey) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function sourceId(string $idempotencyKey): int
    {
        // A 60-bit positive integer fits signed BIGINT on every supported database.
        return (int) hexdec(substr(hash('sha256', $idempotencyKey), 0, 15));
    }
}
