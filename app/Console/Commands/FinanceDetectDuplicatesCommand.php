<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinanceDetectDuplicatesCommand extends Command
{
    protected $signature = 'finance:detect-duplicates';

    protected $description = 'Report duplicate financial records only';

    public function handle(): int
    {
        $hasOutput = false;

        $walletDuplicates = DB::table('wallet_transactions')
            ->select('wallet_id', 'source_type', 'source_id', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('wallet_id', 'source_type', 'source_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($walletDuplicates->isNotEmpty()) {
            $hasOutput = true;
            $this->info('wallet_transactions duplicates');
            $this->table(['wallet_id', 'source_type', 'source_id', 'duplicate_count'], $walletDuplicates->map(fn ($row) => (array) $row)->all());
        }

        $paymentDuplicates = DB::table('transactions_pf')
            ->select('pf_payment_id', DB::raw('COUNT(*) as duplicate_count'))
            ->whereNotNull('pf_payment_id')
            ->groupBy('pf_payment_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($paymentDuplicates->isNotEmpty()) {
            $hasOutput = true;
            $this->info('transactions_pf duplicates');
            $this->table(['pf_payment_id', 'duplicate_count'], $paymentDuplicates->map(fn ($row) => (array) $row)->all());
        }

        if (DB::getSchemaBuilder()->hasTable('team_payment_orders')) {
            $teamDuplicates = DB::table('team_payment_orders')
                ->select('team_id', 'player_id', 'event_id', DB::raw('COUNT(*) as duplicate_count'))
                ->groupBy('team_id', 'player_id', 'event_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            if ($teamDuplicates->isNotEmpty()) {
                $hasOutput = true;
                $this->info('team_payment_orders duplicates');
                $this->table(['team_id', 'player_id', 'event_id', 'duplicate_count'], $teamDuplicates->map(fn ($row) => (array) $row)->all());
            }
        }

        return $hasOutput ? self::FAILURE : self::SUCCESS;
    }
}
