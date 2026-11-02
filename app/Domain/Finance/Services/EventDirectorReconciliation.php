<?php

namespace App\Domain\Finance\Services;

use Illuminate\Support\Collection;

class EventDirectorReconciliation
{
    /** General payments reduce the combined balance without inventing an expense/profit split. */
    public static function build(Collection $directors, Collection $expenses, Collection $payments, float $netProfit): Collection
    {
        $shares = EventProfitAllocation::cents($directors, $netProfit);

        return $directors->map(function ($director) use ($expenses, $payments, $shares) {
            $paid = $expenses->where('paid_by_convenor_id', $director->id)
                ->sum(fn ($expense) => EventExpenseReimbursementService::expenseCents($expense));
            $directorPayments = $payments->where('convenor_id', $director->id);
            $reimbursed = $expenses->where('paid_by_convenor_id', $director->id)->whereNotNull('reimbursed_at')
                ->sum(fn ($expense) => EventExpenseReimbursementService::expenseCents($expense))
                + $directorPayments->where('purpose', 'expense_reimbursement')->sum(fn ($payment) => EventExpenseAmounts::cents($payment->amount));
            $generalPaid = $directorPayments->where('purpose', 'director_payment')->sum(fn ($payment) => EventExpenseAmounts::cents($payment->amount));
            $share = $shares[$director->id] ?? 0;

            return [
                'convenor' => $director, 'total_paid' => $paid / 100.0, 'owed_back' => $paid / 100.0,
                'reimbursed' => $reimbursed / 100.0, 'director_paid' => $generalPaid / 100.0,
                'profit_share_pct' => (float) ($director->profit_share_pct ?? 0),
                'profit_share_amount' => $share / 100.0,
                'final_payout' => ($paid - $reimbursed + $share - $generalPaid) / 100.0,
            ];
        });
    }
}
