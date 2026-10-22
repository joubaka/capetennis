<?php

namespace App\Domain\Finance\Services;

use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\EventExpense;
use App\Models\EventPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventExpenseReimbursementService
{
    public function record(Event $event, User $actor, array $data): EventPayout
    {
        return DB::transaction(function () use ($event, $actor, $data) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $director = EventConvenor::where('event_id', $event->id)->whereKey($data['convenor_id'])->lockForUpdate()->first();
            if (! $director) {
                throw ValidationException::withMessages(['convenor_id' => 'Choose a director assigned to this event.']);
            }
            $fields = [
                'event_id' => $event->id, 'convenor_id' => $director->id,
                'amount' => EventExpenseAmounts::decimal(EventExpenseAmounts::cents($data['amount'])),
                'purpose' => 'expense_reimbursement', 'funding_source' => 'registration_entries',
                'paid_at' => $data['paid_at'] ?? null, 'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ];
            $existing = EventPayout::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                $existing->fill($fields);
                if ($existing->isDirty(array_keys($fields))) {
                    throw ValidationException::withMessages(['amount' => 'This submission was already recorded with different details. Reload before recording another payment.']);
                }
                return $existing;
            }
            $expenses = EventExpense::where('event_id', $event->id)->where('paid_by_convenor_id', $director->id)
                ->whereNotIn('expense_type', ['payfast', 'cape_tennis_fee'])->lockForUpdate()->get();
            $total = $expenses->sum(fn ($expense) => self::expenseCents($expense));
            $legacy = $expenses->whereNotNull('reimbursed_at')->sum(fn ($expense) => self::expenseCents($expense));
            $recorded = self::recordedCents($event->id, $director->id);
            $amount = EventExpenseAmounts::cents($data['amount']);
            if ($amount <= 0 || $amount > $total - $legacy - $recorded) {
                throw ValidationException::withMessages(['amount' => 'The reimbursement must be positive and cannot exceed the director’s outstanding expenses.']);
            }
            return EventPayout::create($fields + [
                'recipient_name' => $director->user->name, 'paid_by' => $actor->id,
                'recipient_user_id' => $director->user_id, 'recorded_by_name' => $actor->name,
                'payment_method' => 'recorded_payment', 'idempotency_key' => $data['idempotency_key'],
            ]);
        });
    }

    public static function expenseCents(EventExpense $expense): int
    {
        return $expense->quantity !== null && $expense->unit_price !== null
            ? EventExpenseAmounts::lineTotal($expense->quantity, $expense->unit_price)
            : EventExpenseAmounts::cents($expense->amount);
    }

    public static function recordedCents(int $eventId, int $directorId): int
    {
        return EventPayout::where('event_id', $eventId)->where('convenor_id', $directorId)
            ->where('purpose', 'expense_reimbursement')->get(['amount'])->sum(fn ($payout) => EventExpenseAmounts::cents($payout->amount));
    }

    public function reimburseWholeExpense(EventExpense $expense, User $actor): void
    {
        DB::transaction(function () use ($expense, $actor) {
            Event::whereKey($expense->event_id)->lockForUpdate()->firstOrFail();
            $expense = EventExpense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($expense->reimbursed_at) {
                return;
            }
            if (in_array($expense->expense_type, ['payfast', 'cape_tennis_fee'], true) || ! $expense->approved_at || ! $expense->paid_by_convenor_id) {
                throw ValidationException::withMessages(['expense' => 'Only approved operational expenses with a paying director can be reimbursed.']);
            }
            if (self::recordedCents($expense->event_id, $expense->paid_by_convenor_id) > 0) {
                throw ValidationException::withMessages(['expense' => 'Record reimbursements for this director in Registration reconciliation to avoid counting the same payment twice.']);
            }
            $expense->update(['reimbursed_at' => now(), 'reimbursed_by' => $actor->id]);
        });
    }
}
