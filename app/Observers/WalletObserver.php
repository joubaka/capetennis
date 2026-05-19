<?php

namespace App\Observers;

use App\Models\Wallet;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\Log;

class WalletObserver
{
    public function saving(Wallet $wallet): void
    {
        if (!$wallet->isDirty('balance')) {
            return;
        }

        if (FinanceMutationScope::allows('wallet_balance_write')) {
            return;
        }

        Log::warning('FINANCE LOCKDOWN: direct wallet balance mutation detected', [
            'wallet_id' => $wallet->id,
            'original_balance' => $wallet->getOriginal('balance'),
            'new_balance' => $wallet->balance,
            'scopes' => FinanceMutationScope::current(),
        ]);
    }
}
