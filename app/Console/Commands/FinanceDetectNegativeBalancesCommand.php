<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use Illuminate\Console\Command;

class FinanceDetectNegativeBalancesCommand extends Command
{
    protected $signature = 'finance:detect-negative-balances';

    protected $description = 'Report wallets with negative balances only';

    public function handle(): int
    {
        $rows = Wallet::all()
            ->map(fn (Wallet $wallet) => [
                'wallet_id' => $wallet->id,
                'balance' => round((float) $wallet->balance, 2),
            ])
            ->filter(fn (array $row) => $row['balance'] < 0)
            ->values()
            ->all();

        if ($rows === []) {
            return self::SUCCESS;
        }

        $this->table(['wallet_id', 'balance'], $rows);

        return self::FAILURE;
    }
}
