<?php

namespace App\Domain\Finance\Services;

use App\Models\Event;
use App\Models\EventExpense;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventExpenseBatchService
{
    public static function version(EventExpense $expense): string
    {
        return hash('sha256', json_encode($expense->getRawOriginal(), JSON_THROW_ON_ERROR));
    }

    public function save(Event $event, array $lines): void
    {
        DB::transaction(function () use ($event, $lines) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            foreach ($lines as $index => $line) {
                $query = EventExpense::where('event_id', $event->id)->lockForUpdate();
                $expense = ! empty($line['id']) ? $query->findOrFail($line['id'])
                    : $query->where('batch_key', $line['batch_key'])->first();
                if ($expense && ($expense->reimbursed_at || in_array($expense->expense_type, ['payfast', 'cape_tennis_fee'], true))) {
                    throw ValidationException::withMessages(["lines.$index.description" => 'This expense is reimbursed or managed automatically. Reload the list to continue.']);
                }
                $quantity = $line['quantity'] ?? null;
                $price = $line['unit_price'] ?? null;
                $cents = $quantity !== null && $price !== null
                    ? EventExpenseAmounts::lineTotal((string) $quantity, (string) $price)
                    : EventExpenseAmounts::cents($line['amount']);
                if ($cents > 9999999999) {
                    throw ValidationException::withMessages(["lines.$index.amount" => 'An expense must be below R100,000,000.']);
                }
                $fields = [
                    'expense_type' => $line['expense_type'], 'description' => $line['description'] ?? null,
                    'recipient_name' => $line['recipient_name'] ?? null,
                    'paid_by_convenor_id' => $line['paid_by_convenor_id'] ?? null,
                    'venue_id' => $line['venue_id'] ?? null,
                    'quantity' => $quantity, 'unit_price' => $price,
                    'amount' => EventExpenseAmounts::decimal($cents), 'date' => $line['date'],
                ];
                $version = $expense ? self::version($expense) : null;
                $replayedCreation = empty($line['id']) && $expense !== null;
                $expense ??= new EventExpense;
                $expense->forceFill($fields);
                if ($replayedCreation && $expense->isDirty(array_keys($fields))) {
                    throw ValidationException::withMessages(["lines.$index.description" => 'This new expense was already saved with different details. Reload the list before changing it.']);
                }
                // An identical retry is harmless; a stale edit that would overwrite a change is rejected.
                if (! empty($line['id']) && ! hash_equals($version, $line['version']) && $expense->isDirty(array_keys($fields))) {
                    throw ValidationException::withMessages(["lines.$index.description" => 'This expense changed while you were editing. Reload the list and apply your changes again.']);
                }
                if (! $expense->exists) {
                    $expense->forceFill(['event_id' => $event->id, 'batch_key' => $line['batch_key']]);
                }
                $expense->save();
            }
        });
    }
}
