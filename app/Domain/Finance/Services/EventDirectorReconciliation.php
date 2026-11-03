<?php

namespace App\Domain\Finance\Services;

use App\Models\Event;
use App\Models\EventPayout;
use Illuminate\Support\Collection;

class EventDirectorReconciliation
{
    public static function paymentsForEvent(Event $event): Collection
    {
        return EventPayout::with(['paidByUser', 'convenor.user'])->where('event_id', $event->id)
            ->where(fn ($query) => $query->whereNull('purpose')->orWhereIn('purpose', [
                '', 'expense_reimbursement', 'director_payment', 'registration_payout', 'payout_reversal',
            ]))->orderByDesc('id')->get();
    }

    public static function signedPaymentCents(EventPayout $payment): int
    {
        $amount = (string) $payment->amount;
        return str_starts_with($amount, '-') ? -EventExpenseAmounts::cents(substr($amount, 1)) : EventExpenseAmounts::cents($amount);
    }

    public static function hasUnresolvedPayments(Collection $directors, Collection $payments, $director): bool
    {
        $corrected = $payments->where('purpose', 'payout_reversal')->pluck('reversal_of_id');
        return $payments->contains(function ($payment) use ($directors, $director, $corrected) {
            if ((int) $payment->event_id !== (int) $director->event_id) return false;
            if ($payment->purpose === 'payout_reversal' || $corrected->contains($payment->id)) return false;
            if ($payment->convenor_id) {
                $boundDirector = $directors->firstWhere('id', $payment->convenor_id);
                if (! $boundDirector) return true;
                return $payment->recipient_user_id
                    && (int) $payment->recipient_user_id !== (int) $boundDirector->user_id;
            }
            if (! $payment->recipient_user_id) return true;
            $matches = $directors->where('user_id', $payment->recipient_user_id);
            if ($matches->isEmpty()) return true;
            return (int) $payment->recipient_user_id === (int) $director->user_id
                && $matches->count() !== 1;
        });
    }
    /** General payments reduce the combined balance without inventing an expense/profit split. */
    public static function build(Collection $directors, Collection $expenses, Collection $payments, float $netProfit): Collection
    {
        $shares = EventProfitAllocation::cents($directors, $netProfit);

        return $directors->map(function ($director) use ($directors, $expenses, $payments, $shares) {
            $paid = $expenses->where('paid_by_convenor_id', $director->id)
                ->sum(fn ($expense) => EventExpenseReimbursementService::expenseCents($expense));
            $directorPayments = $payments->filter(function ($payment) use ($director, $directors) {
                if ((int) $payment->event_id !== (int) $director->event_id) return false;
                if ($payment->convenor_id) return (int) $payment->convenor_id === (int) $director->id;
                return $payment->recipient_user_id && (int) $payment->recipient_user_id === (int) $director->user_id
                    && $directors->where('user_id', $director->user_id)->count() === 1;
            });
            $reimbursed = $expenses->where('paid_by_convenor_id', $director->id)->whereNotNull('reimbursed_at')
                ->sum(fn ($expense) => EventExpenseReimbursementService::expenseCents($expense))
                + $directorPayments->where('purpose', 'expense_reimbursement')->sum(fn ($payment) => EventExpenseAmounts::cents($payment->amount));
            $generalPaid = $directorPayments->where('purpose', '!=', 'expense_reimbursement')->sum(fn ($payment) => self::signedPaymentCents($payment));
            $share = $shares[$director->id] ?? 0;

            return [
                'convenor' => $director, 'total_paid' => $paid / 100.0, 'owed_back' => $paid / 100.0,
                'reimbursed' => $reimbursed / 100.0, 'director_paid' => $generalPaid / 100.0,
                'profit_share_pct' => (float) ($director->profit_share_pct ?? 0),
                'payment_resolution_required' => self::hasUnresolvedPayments($directors, $payments, $director),
                'profit_share_amount' => $share / 100.0,
                'final_payout' => ($paid - $reimbursed + $share - $generalPaid) / 100.0,
            ];
        });
    }
}
