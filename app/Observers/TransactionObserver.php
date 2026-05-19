<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    public function creating(Transaction $transaction): void
    {
        if (FinanceMutationScope::allows('payment_transaction_write')) {
            return;
        }

        Log::warning('FINANCE LOCKDOWN: direct payment transaction insert detected', [
            'pf_payment_id' => $transaction->pf_payment_id,
            'transaction_type' => $transaction->transaction_type,
            'event_id' => $transaction->event_id,
            'custom_int5' => $transaction->custom_int5,
            'scopes' => FinanceMutationScope::current(),
        ]);
    }

    public function updating(Transaction $transaction): void
    {
        if (FinanceMutationScope::allows('payment_transaction_write')) {
            return;
        }

        Log::warning('FINANCE LOCKDOWN: payment transaction update detected', [
            'transaction_id' => $transaction->id,
            'dirty' => array_keys($transaction->getDirty()),
            'scopes' => FinanceMutationScope::current(),
        ]);
    }
}
