<?php

namespace App\Observers;

use App\Models\WalletTransaction;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\Log;

class WalletTransactionObserver
{
    public function creating(WalletTransaction $transaction): void
    {
        if (FinanceMutationScope::allows('wallet_transaction_write', 'ledger_write')) {
            return;
        }

        Log::warning('FINANCE LOCKDOWN: direct wallet transaction insert detected', [
            'wallet_id' => $transaction->wallet_id,
            'source_type' => $transaction->source_type,
            'source_id' => $transaction->source_id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'scopes' => FinanceMutationScope::current(),
        ]);
    }

    public function updating(WalletTransaction $transaction): void
    {
        if (FinanceMutationScope::allows('wallet_transaction_update')) {
            return;
        }

        Log::warning('FINANCE LOCKDOWN: wallet transaction update detected', [
            'transaction_id' => $transaction->id,
            'dirty' => array_keys($transaction->getDirty()),
            'scopes' => FinanceMutationScope::current(),
        ]);
    }

    public function deleting(WalletTransaction $transaction): void
    {
        if (FinanceMutationScope::allows('wallet_transaction_delete')) {
            return;
        }

        Log::warning('FINANCE LOCKDOWN: wallet transaction delete detected', [
            'transaction_id' => $transaction->id,
            'wallet_id' => $transaction->wallet_id,
            'scopes' => FinanceMutationScope::current(),
        ]);
    }
}
