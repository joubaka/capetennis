<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinanceReconcileWalletsCommand extends Command
{
    protected $signature = 'finance:reconcile-wallets';

    protected $description = 'Report wallet reconciliation mismatches only';

    public function handle(): int
    {
        $rows = Wallet::query()
            ->select('wallets.id')
            ->selectRaw("COALESCE(SUM(CASE WHEN wallet_transactions.type = 'credit' THEN wallet_transactions.amount ELSE -wallet_transactions.amount END), 0) AS ledger_balance")
            ->leftJoin('wallet_transactions', 'wallet_transactions.wallet_id', '=', 'wallets.id')
            ->groupBy('wallets.id')
            ->get();

        $mismatches = [];
        foreach ($rows as $row) {
            $wallet = Wallet::find($row->id);
            $accessorBalance = round((float) ($wallet?->balance ?? 0), 2);
            $ledgerBalance = round((float) $row->ledger_balance, 2);

            if ($accessorBalance !== $ledgerBalance) {
                $mismatches[] = [
                    'wallet_id' => $row->id,
                    'accessor_balance' => $accessorBalance,
                    'ledger_balance' => $ledgerBalance,
                ];
            }
        }

        if ($mismatches === []) {
            return self::SUCCESS;
        }

        $this->table(['wallet_id', 'accessor_balance', 'ledger_balance'], $mismatches);

        return self::FAILURE;
    }
}
