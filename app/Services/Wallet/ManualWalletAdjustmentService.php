<?php

namespace App\Services\Wallet;

use App\Domain\Payments\Services\LedgerService;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualWalletAdjustmentService
{
    public function __construct(private LedgerService $ledger)
    {
    }

    public function apply(Wallet $wallet, string $type, float $amount, string $idempotencyKey, array $meta = []): WalletTransaction
    {
        validator(['type' => $type, 'amount' => (string) $amount, 'idempotency_key' => $idempotencyKey], [
            'type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01', 'regex:/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();
        $sourceId = $this->sourceId($idempotencyKey);
        $meta['idempotency_key'] = $idempotencyKey;
        $meta['actor_id'] = auth()->id();
        $meta['reference'] = trim((string) ($meta['reference'] ?? ''));

        return DB::transaction(function () use ($wallet, $type, $amount, $sourceId, $idempotencyKey, $meta) {
            Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            // Resolve a completed attempt before checking the remaining balance.
            $existing = WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('source_type', 'manual')
                ->where('source_id', $sourceId)
                ->lockForUpdate()->first();

            if ($existing) {
                if ($existing->type !== $type
                    || number_format((float) $existing->amount, 2, '.', '') !== number_format($amount, 2, '.', '')
                    || data_get($existing->meta, 'idempotency_key') !== $idempotencyKey
                    || data_get($existing->meta, 'actor_id') !== $meta['actor_id']
                    || trim((string) data_get($existing->meta, 'reference', '')) !== $meta['reference']) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This adjustment was already recorded with different details or by another administrator.']);
                }
                return $existing;
            }

            return $type === 'credit'
                ? $this->ledger->appendWalletCredit($wallet, $amount, 'manual', $sourceId, $meta)
                : $this->ledger->appendWalletDebit($wallet, $amount, 'manual', $sourceId, $meta);
        });
    }

    private function sourceId(string $idempotencyKey): int
    {
        // A 60-bit positive integer fits signed BIGINT on every supported database.
        return (int) hexdec(substr(hash('sha256', $idempotencyKey), 0, 15));
    }
}
