<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transaction;
use App\Models\SiteSetting;

class TransactionFeeFixSeeder extends Seeder
{
    /**
     * Calculate PayFast fee using site settings.
     */
    private function calculatePayfastFee(float $amount): float
    {
        return SiteSetting::calculatePayfastFee($amount);
    }

    public function run()
    {
        // Find transactions with amount_fee = 0 or null but have amount_gross > 0
        $transactions = Transaction::where('transaction_type', 'Registration')
            ->where('amount_gross', '>', 0)
            ->where(function ($q) {
                $q->whereNull('amount_fee')
                  ->orWhere('amount_fee', 0);
            })
            ->get();

        $updated = 0;

        foreach ($transactions as $t) {
            $fee = $this->calculatePayfastFee((float) $t->amount_gross);
            $net = round((float) $t->amount_gross - $fee, 2);

            $t->amount_fee = $fee;
            $t->amount_net = $net;
            $t->save();

            $updated++;
        }

        echo "\n=== TRANSACTION FEE FIX COMPLETE ===\n";
        echo "Updated: {$updated} transactions with calculated PayFast fees\n";
    }
}
