<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventExpense;
use App\Models\Transaction;
use Illuminate\Http\Request;

class EventFinanceController extends Controller
{
    /**
     * Display the convenor finances page with income and expenses.
     */
    public function index(Event $event)
    {
        // Get registration income (same logic as transactions page)
        $feePerEntry = (float) $event->cape_tennis_fee;
        
        $transactions = Transaction::with([
            'user',
            'order.items.player',
            'order.items.category_event.category',
        ])
            ->where('event_id', $event->id)
            ->where('transaction_type', 'Registration')
            ->where('amount_gross', '>', 0)
            ->orderByDesc('created_at')
            ->get();

        // Calculate income totals
        $totalGross = $transactions->sum('amount_gross');
        $totalPayfastFees = $transactions->sum('amount_fee');
        $totalEntries = $transactions->sum(fn($t) => $t->order?->items?->count() ?? 1);
        $totalCapeTennisFees = $totalEntries * $feePerEntry;
        $netRegistrationIncome = $totalGross - abs($totalPayfastFees) - $totalCapeTennisFees;

        // Get expenses
        $expenses = EventExpense::where('event_id', $event->id)
            ->orderByDesc('created_at')
            ->get();

        // Group expenses by type
        $expenseTypes = [
            'balls' => 'Balls',
            'venue' => 'Venue',
            'convenors' => 'Convenors',
            'data' => 'Data',
            'petrol' => 'Petrol',
            'accommodation' => 'Accommodation',
            'cape_tennis_fee' => 'Cape Tennis Fee',
            'payfast' => 'PayFast Fees',
            'other' => 'Other',
        ];

        $expensesByType = $expenses->groupBy('expense_type');
        $totalExpenses = $expenses->sum('amount');

        // Calculate net profit/loss
        $netProfit = $netRegistrationIncome - $totalExpenses;

        return view('backend.event.finances', compact(
            'event',
            'transactions',
            'totalGross',
            'totalPayfastFees',
            'totalCapeTennisFees',
            'totalEntries',
            'feePerEntry',
            'netRegistrationIncome',
            'expenses',
            'expenseTypes',
            'expensesByType',
            'totalExpenses',
            'netProfit'
        ));
    }

    /**
     * Store a new expense.
     */
    public function storeExpense(Request $request, Event $event)
    {
        $validated = $request->validate([
            'expense_type' => 'required|string|max:50',
            'convenor_name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'nullable|date',
        ]);

        EventExpense::create([
            'event_id' => $event->id,
            'expense_type' => $validated['expense_type'],
            'convenor_name' => $validated['convenor_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'date' => $validated['date'] ?? now(),
        ]);

        return back()->with('success', 'Expense added successfully.');
    }

    /**
     * Update an existing expense.
     */
    public function updateExpense(Request $request, EventExpense $expense)
    {
        $validated = $request->validate([
            'expense_type' => 'required|string|max:50',
            'convenor_name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'nullable|date',
        ]);

        $expense->update($validated);

        return back()->with('success', 'Expense updated successfully.');
    }

    /**
     * Delete an expense.
     */
    public function destroyExpense(EventExpense $expense)
    {
        $expense->delete();

        return back()->with('success', 'Expense deleted successfully.');
    }
}
