<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\EventExpense;
use App\Models\EventIncomeItem;
use App\Models\ExpenseType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EventFinanceController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  INDEX                                                              */
    /* ------------------------------------------------------------------ */

    public function index(Event $event)
    {
        // ── Registration income (from PayFast transactions) ──────────────
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

        $totalGross          = $transactions->sum('amount_gross');
        $totalPayfastFees    = $transactions->sum('amount_fee');
        $totalEntries        = $transactions->sum(fn($t) => $t->order?->items?->count() ?? 1);
        $totalCapeTennisFees = $totalEntries * $feePerEntry;
        $netRegistrationIncome = $totalGross - abs($totalPayfastFees) - $totalCapeTennisFees;

        // ── Income breakdown by category (individual) or by group (team) ──
        $incomeByCategory = collect();
        foreach ($transactions as $t) {
            $items     = $t->order?->items ?? collect();
            $itemCount = $items->count();
            // Skip transactions with no order items to avoid division by zero below
            if ($itemCount === 0) {
                continue;
            }
            $amtPerItem = (float) $t->amount_gross / $itemCount;
            foreach ($items as $item) {
                $catName = $item->category_event?->category?->name ?? 'Unknown';
                $current = $incomeByCategory->get($catName, ['entries' => 0, 'amount' => 0.0]);
                $incomeByCategory->put($catName, [
                    'entries' => $current['entries'] + 1,
                    'amount'  => $current['amount'] + $amtPerItem,
                ]);
            }
        }
        $incomeByCategory = $incomeByCategory->sortKeys();

        // ── Manual income items ───────────────────────────────────────────
        $incomeItems      = $event->incomeItems()->get();
        $totalIncomeItems = $incomeItems->sum(fn($i) => $i->calculatedTotal());
        // grandTotalIncome = net registration (after PayFast + CT deductions) + manual items
        $grandTotalIncome = $netRegistrationIncome + $totalIncomeItems;

        // ── Convenors (Hoof first, then Hulp, then others) ───────────────
        $convenors = $event->convenors()
            ->with('user')
            ->orderByRaw("FIELD(role, 'hoof', 'hulp', 'admin')")
            ->get();

        // ── All expenses ──────────────────────────────────────────────────
        $expenses = EventExpense::where('event_id', $event->id)
            ->with(['paidByConvenor.user', 'approvedByUser', 'reimbursedByUser'])
            ->orderByDesc('created_at')
            ->get();

        // Auto-sync (create or update) PayFast and Cape Tennis Fee rows
        $this->autoSyncSystemExpenses($event, $totalPayfastFees, $totalCapeTennisFees, $expenses, $totalEntries, $feePerEntry);

        // Refresh after potential sync
        $expenses = EventExpense::where('event_id', $event->id)
            ->with(['paidByConvenor.user', 'approvedByUser', 'reimbursedByUser'])
            ->orderByDesc('created_at')
            ->get();

        $expenseTypes = $this->expenseTypes();

        // ── Split: system fee rows vs. operational expense rows ───────────
        // System fees (payfast, cape_tennis_fee) are already deducted from gross income
        // in $grandTotalIncome. They must NOT be double-counted in $totalExpenses.
        $systemTypes         = ['payfast', 'cape_tennis_fee'];
        $operationalExpenses = $expenses->reject(fn($e) => in_array($e->expense_type, $systemTypes));
        $systemExpenseRows   = $expenses->filter(fn($e) => in_array($e->expense_type, $systemTypes));

        // Group operational expenses by paying convenor for per-convenor sections
        $expensesByConvenor = $operationalExpenses->groupBy('paid_by_convenor_id');

        // Group operational expenses by type (for summary accordion)
        $expensesByType = $operationalExpenses->groupBy('expense_type');

        // Totals use operational expenses only — system fees are income deductions, not expenses
        $totalExpenses        = $operationalExpenses->sum(fn($e) => $e->calculatedAmount());
        $totalSystemFees      = $systemExpenseRows->sum(fn($e) => $e->calculatedAmount());
        $totalBudget          = $operationalExpenses->whereNotNull('budget_amount')->sum('budget_amount');
        $pendingApproval      = $operationalExpenses->whereNull('approved_at')->count();
        $pendingReimbursement = $operationalExpenses->whereNotNull('approved_at')->whereNull('reimbursed_at')->count();

        // ── Per-convenor totals for reconciliation (operational only) ─────
        $recon = $convenors->map(function ($convenor) use ($operationalExpenses) {
            $paid = $operationalExpenses
                ->where('paid_by_convenor_id', $convenor->id)
                ->sum(fn($e) => $e->calculatedAmount());

            return [
                'convenor'   => $convenor,
                'total_paid' => $paid,
                'owed_back'  => $paid,
                'reimbursed' => $operationalExpenses
                    ->where('paid_by_convenor_id', $convenor->id)
                    ->whereNotNull('reimbursed_at')
                    ->sum(fn($e) => $e->calculatedAmount()),
            ];
        });

        // Budget cap warning threshold (90%) — based on operational expenses only
        $budgetCapWarning = $event->budget_cap
            ? ($totalExpenses / $event->budget_cap) >= 0.9
            : false;

        // Net profit: grandTotalIncome is already net of system fees;
        // subtract only operational expenses to avoid double-counting.
        $netProfit = $grandTotalIncome - $totalExpenses;

        // All expense types including system types (for the manage-types modal)
        $allExpenseTypes = ExpenseType::ordered()->get();

        return view('backend.event.finances', compact(
            'event',
            'transactions',
            'totalGross',
            'totalPayfastFees',
            'totalCapeTennisFees',
            'totalEntries',
            'feePerEntry',
            'netRegistrationIncome',
            'incomeByCategory',
            'incomeItems',
            'totalIncomeItems',
            'grandTotalIncome',
            'convenors',
            'expenses',
            'expenseTypes',
            'allExpenseTypes',
            'expensesByConvenor',
            'expensesByType',
            'totalExpenses',
            'totalSystemFees',
            'totalBudget',
            'pendingApproval',
            'pendingReimbursement',
            'recon',
            'budgetCapWarning',
            'netProfit'
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  EXPENSES – CRUD                                                    */
    /* ------------------------------------------------------------------ */

    public function storeExpense(Request $request, Event $event)
    {
        $validated = $request->validate([
            'expense_type'          => 'required|string|max:50',
            'paid_by_convenor_id'   => 'nullable|exists:event_convenors,id',
            'convenor_name'         => 'nullable|string|max:100',
            'description'           => 'nullable|string|max:255',
            'recipient_name'        => 'nullable|string|max:150',
            'amount'                => 'required|numeric|min:0',
            'quantity'              => 'nullable|numeric|min:0',
            'unit_price'            => 'nullable|numeric|min:0',
            'budget_amount'         => 'nullable|numeric|min:0',
            'date'                  => 'nullable|date',
            'receipt'               => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        // Compute amount from qty × unit_price when both provided
        $amount = $this->resolveAmount($validated);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')
                ->store("event_receipts/{$event->id}", 'public');
        }

        EventExpense::create([
            'event_id'             => $event->id,
            'expense_type'         => $validated['expense_type'],
            'paid_by_convenor_id'  => $validated['paid_by_convenor_id'] ?? null,
            'convenor_name'        => $validated['convenor_name'] ?? null,
            'description'          => $validated['description'] ?? null,
            'recipient_name'       => $validated['recipient_name'] ?? null,
            'amount'               => $amount,
            'quantity'             => $validated['quantity'] ?? null,
            'unit_price'           => $validated['unit_price'] ?? null,
            'budget_amount'        => $validated['budget_amount'] ?? null,
            'receipt_path'         => $receiptPath,
            'date'                 => $validated['date'] ?? now(),
        ]);

        return $request->wantsJson()
            ? response()->json(['message' => 'Expense added successfully.'])
            : back()->with('success', 'Expense added successfully.');
    }

    public function updateExpense(Request $request, EventExpense $expense)
    {
        $validated = $request->validate([
            'expense_type'          => 'required|string|max:50',
            'paid_by_convenor_id'   => 'nullable|exists:event_convenors,id',
            'convenor_name'         => 'nullable|string|max:100',
            'description'           => 'nullable|string|max:255',
            'recipient_name'        => 'nullable|string|max:150',
            'amount'                => 'required|numeric|min:0',
            'quantity'              => 'nullable|numeric|min:0',
            'unit_price'            => 'nullable|numeric|min:0',
            'budget_amount'         => 'nullable|numeric|min:0',
            'date'                  => 'nullable|date',
            'receipt'               => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $amount = $this->resolveAmount($validated);

        $updates = array_merge($validated, ['amount' => $amount]);
        unset($updates['receipt']);

        if ($request->hasFile('receipt')) {
            // Delete old receipt if exists
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $updates['receipt_path'] = $request->file('receipt')
                ->store("event_receipts/{$expense->event_id}", 'public');
        }

        $expense->update($updates);

        return $request->wantsJson()
            ? response()->json(['message' => 'Expense updated successfully.'])
            : back()->with('success', 'Expense updated successfully.');
    }

    public function destroyExpense(EventExpense $expense)
    {
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        $expense->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Expense deleted.']);
        }

        return back()->with('success', 'Expense deleted.');
    }

    /* ------------------------------------------------------------------ */
    /*  EXPENSES – APPROVAL & REIMBURSEMENT                               */
    /* ------------------------------------------------------------------ */

    public function approveExpense(EventExpense $expense)
    {
        $expense->update([
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        return request()->wantsJson()
            ? response()->json(['message' => 'Expense approved.'])
            : back()->with('success', 'Expense approved.');
    }

    public function reimburseExpense(EventExpense $expense)
    {
        $expense->update([
            'reimbursed_at' => now(),
            'reimbursed_by' => Auth::id(),
        ]);

        return request()->wantsJson()
            ? response()->json(['message' => 'Reimbursement marked.'])
            : back()->with('success', 'Reimbursement marked.');
    }

    /* ------------------------------------------------------------------ */
    /*  INCOME ITEMS – CRUD                                                */
    /* ------------------------------------------------------------------ */

    public function storeIncomeItem(Request $request, Event $event)
    {
        $validated = $request->validate([
            'label'      => 'required|string|max:255',
            'quantity'   => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'total'      => 'nullable|numeric|min:0',
            'source'     => 'nullable|string|max:255',
            'date'       => 'nullable|date',
        ]);

        $total = ($validated['quantity'] ?? null) !== null && ($validated['unit_price'] ?? null) !== null
            ? (float) $validated['quantity'] * (float) $validated['unit_price']
            : (float) ($validated['total'] ?? 0);

        EventIncomeItem::create([
            'event_id'   => $event->id,
            'label'      => $validated['label'],
            'quantity'   => $validated['quantity'] ?? null,
            'unit_price' => $validated['unit_price'] ?? null,
            'total'      => $total,
            'source'     => $validated['source'] ?? null,
            'date'       => $validated['date'] ?? null,
        ]);

        return $request->wantsJson()
            ? response()->json(['message' => 'Income item added.'])
            : back()->with('success', 'Income item added.');
    }

    public function updateIncomeItem(Request $request, EventIncomeItem $item)
    {
        $validated = $request->validate([
            'label'      => 'required|string|max:255',
            'quantity'   => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'total'      => 'nullable|numeric|min:0',
            'source'     => 'nullable|string|max:255',
            'date'       => 'nullable|date',
        ]);

        $total = ($validated['quantity'] ?? null) !== null && ($validated['unit_price'] ?? null) !== null
            ? (float) $validated['quantity'] * (float) $validated['unit_price']
            : (float) ($validated['total'] ?? $item->total);

        $item->update(array_merge($validated, ['total' => $total]));

        return $request->wantsJson()
            ? response()->json(['message' => 'Income item updated.'])
            : back()->with('success', 'Income item updated.');
    }

    public function destroyIncomeItem(EventIncomeItem $item)
    {
        $item->delete();

        return request()->wantsJson()
            ? response()->json(['message' => 'Income item deleted.'])
            : back()->with('success', 'Income item deleted.');
    }

    /* ------------------------------------------------------------------ */
    /*  PRIVATE HELPERS                                                    */
    /* ------------------------------------------------------------------ */

    private function expenseTypes(): array
    {
        return ExpenseType::asOptions();
    }

    /**
     * If qty × unit_price are both provided, override the submitted amount.
     */
    private function resolveAmount(array $validated): float
    {
        if (
            !empty($validated['quantity']) &&
            !empty($validated['unit_price'])
        ) {
            return (float) $validated['quantity'] * (float) $validated['unit_price'];
        }

        return (float) $validated['amount'];
    }

    /**
     * Auto-create or update system expense rows for PayFast fees and Cape Tennis fees
     * derived from actual transaction data. Rows are updated whenever the live totals
     * change (e.g. after new registrations come in).
     *
     * Quantity and unit_price are stored so the "Quantity × Price" column renders
     * meaningful data rather than dashes:
     *   - Cape Tennis fee: entries × fee_per_entry
     *   - PayFast fee:     entries × avg_payfast_fee_per_entry (percentage-based, so this
     *                      is an effective/average rate — not a fixed tariff)
     */
    private function autoSyncSystemExpenses(
        Event $event,
        float $totalPayfastFees,
        float $totalCapeTennisFees,
        $expenses,
        int   $totalEntries = 0,
        float $feePerEntry  = 0.0
    ): void {
        $payfastRow = $expenses->where('expense_type', 'payfast')->first();
        $ctRow      = $expenses->where('expense_type', 'cape_tennis_fee')->first();

        $payfastAmount = abs($totalPayfastFees);

        // Average PayFast fee per entry (percentage-based, so this is effective/average)
        $payfastUnitPrice = ($totalEntries > 0) ? round($payfastAmount / $totalEntries, 4) : 0.0;

        if ($payfastAmount > 0) {
            $payfastData = [
                'description'  => 'PayFast fees (auto-synced)',
                'amount'       => $payfastAmount,
                'quantity'     => $totalEntries ?: null,
                'unit_price'   => $payfastUnitPrice ?: null,
            ];
            if (!$payfastRow) {
                EventExpense::create(array_merge($payfastData, [
                    'event_id'     => $event->id,
                    'expense_type' => 'payfast',
                    'date'         => now(),
                ]));
            } elseif (
                (float) $payfastRow->amount    !== $payfastAmount       ||
                (int)   $payfastRow->quantity  !== $totalEntries        ||
                (float) $payfastRow->unit_price !== $payfastUnitPrice
            ) {
                $payfastRow->update($payfastData);
            }
        }

        if ($totalCapeTennisFees > 0) {
            $ctData = [
                'description'  => 'Cape Tennis fee (auto-synced)',
                'amount'       => $totalCapeTennisFees,
                'quantity'     => $totalEntries ?: null,
                'unit_price'   => $feePerEntry ?: null,
            ];
            if (!$ctRow) {
                EventExpense::create(array_merge($ctData, [
                    'event_id'     => $event->id,
                    'expense_type' => 'cape_tennis_fee',
                    'date'         => now(),
                ]));
            } elseif (
                (float) $ctRow->amount    !== $totalCapeTennisFees ||
                (int)   $ctRow->quantity  !== $totalEntries        ||
                (float) $ctRow->unit_price !== $feePerEntry
            ) {
                $ctRow->update($ctData);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  CONVENORS – CRUD (per event, called from finance page)             */
    /* ------------------------------------------------------------------ */

    public function storeConvenor(Request $request, Event $event)
    {
        $validated = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'role'       => 'nullable|string|max:20',
            'starts_at'  => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $exists = EventConvenor::where('event_id', $event->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'This user is already a convenor for this event.'], 422);
            }
            return back()->with('error', 'This user is already a convenor for this event.');
        }

        EventConvenor::create([
            'event_id'   => $event->id,
            'user_id'    => $validated['user_id'],
            'role'       => $validated['role'] ?? 'hulp',
            'starts_at'  => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return $request->wantsJson()
            ? response()->json(['message' => 'Convenor added.'])
            : back()->with('success', 'Convenor added.');
    }

    public function updateConvenor(Request $request, EventConvenor $convenor)
    {
        $validated = $request->validate([
            'role'       => 'nullable|string|max:20',
            'starts_at'  => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $convenor->update([
            'role'       => $validated['role'] ?? $convenor->role,
            'starts_at'  => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return $request->wantsJson()
            ? response()->json(['message' => 'Convenor updated.'])
            : back()->with('success', 'Convenor updated.');
    }

    public function destroyConvenor(EventConvenor $convenor)
    {
        $convenor->delete();

        return request()->wantsJson()
            ? response()->json(['message' => 'Convenor removed.'])
            : back()->with('success', 'Convenor removed.');
    }
}
