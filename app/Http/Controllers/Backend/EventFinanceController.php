<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventExpense;
use App\Models\EventIncomeItem;
use App\Models\SiteSetting;
use App\Models\Transaction;
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
        // Uses the same logic as EventTransactionController (source of truth):
        //   - Excludes test transactions (is_test = false)
        //   - Recalculates PayFast fee via SiteSetting::calculatePayfastFee()
        //   - Adds wallet amounts to gross (order->wallet_reserved)
        //   - Includes completed refunds as negative ledger entries
        $feePerEntry = (float) $event->cape_tennis_fee;
        $isTeamEvent = $event->isTeam();

        $transactions = Transaction::with([
            'user',
            'order.items.player',
            'order.items.category_event.category',
        ])
            ->where('event_id', $event->id)
            ->where('transaction_type', 'Registration')
            ->where('amount_gross', '>', 0)
            ->where('is_test', false)
            ->orderByDesc('created_at')
            ->get();

        // Build payment ledger rows — mirrors EventTransactionController exactly
        $paymentLedger = $transactions->map(function ($tx) use ($feePerEntry) {
            $payfastGross = round((float) $tx->amount_gross, 2);
            $walletUsed   = round((float) optional($tx->order)->wallet_reserved, 2);
            $entryCount   = max(1, $tx->order?->items?->count() ?? 0);
            $pfFee        = SiteSetting::calculatePayfastFee($payfastGross);
            $capeFee      = round($feePerEntry * $entryCount, 2);

            return [
                'gross'       => $payfastGross + $walletUsed,
                'payfast_fee' => $pfFee,
                'cape_fee'    => $capeFee,
                'net'         => round($payfastGross + $walletUsed - $pfFee - $capeFee, 2),
                'items'       => $tx->order?->items ?? collect(),
            ];
        });

        // Load completed refunds and build refund ledger rows
        $refundRegs = CategoryEventRegistration::with([
            'players',
            'categoryEvent.category',
            'payfastTransaction.order.items',
        ])
            ->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
            ->where('status', 'withdrawn')
            ->where('refund_status', 'completed')
            ->whereHas('payfastTransaction', fn ($q) => $q->where('is_test', false))
            ->get();

        $refundLedger = $refundRegs->map(function ($reg) use ($feePerEntry) {
            $payment    = $reg->paymentInfo();
            if (empty($payment)) {
                return null;
            }
            $grossPaid  = (float) ($payment['gross'] ?? 0);
            $payfastFee = abs((float) ($payment['fee'] ?? 0));

            return [
                'gross'       => -$grossPaid,
                'payfast_fee' => -$payfastFee,
                'cape_fee'    => -$feePerEntry,
                'net'         => round(-$grossPaid + $payfastFee + $feePerEntry, 2),
                'items'       => collect(),
            ];
        })->filter()->values();

        $ledger = $paymentLedger->merge($refundLedger);

        // Totals using positive magnitudes (consistent with original variable conventions)
        $totalGross          = round($ledger->sum('gross'), 2);
        $totalPayfastFees    = abs(round($ledger->sum('payfast_fee'), 2));
        $totalCapeTennisFees = abs(round($ledger->sum('cape_fee'), 2));
        $netRegistrationIncome = round($ledger->sum('net'), 2);

        // Entry count: payments only, same as EventTransactionController
        $totalEntries = $isTeamEvent
            ? $transactions->count()
            : $paymentLedger->flatMap(fn ($r) => $r['items'])->count();

        // ── Manual income items ───────────────────────────────────────────
        $incomeItems     = $event->incomeItems()->get();
        $totalIncomeItems = $incomeItems->sum(fn($i) => $i->calculatedTotal());
        $grandTotalIncome = $netRegistrationIncome + $totalIncomeItems;

        // ── Convenors (Hoof first, then Hulp, then others) ───────────────
        $convenors = $event->convenors()
            ->with('user')
            ->orderByRaw("FIELD(role, 'hoof', 'hulp', 'admin')")
            ->get();

        // ── Expenses ─────────────────────────────────────────────────────
        $expenses = EventExpense::where('event_id', $event->id)
            ->with(['paidByConvenor.user', 'approvedByUser', 'reimbursedByUser'])
            ->orderByDesc('created_at')
            ->get();

        // Auto-sync PayFast and Cape Tennis Fee from transactions if none exist yet
        $this->autoSyncSystemExpenses($event, $totalPayfastFees, $totalCapeTennisFees, $expenses);

        // Refresh after potential sync
        $expenses = EventExpense::where('event_id', $event->id)
            ->with(['paidByConvenor.user', 'approvedByUser', 'reimbursedByUser'])
            ->orderByDesc('created_at')
            ->get();

        $expenseTypes = $this->expenseTypes();

        // Group expenses by paying convenor for per-convenor sections
        $expensesByConvenor = $expenses->groupBy('paid_by_convenor_id');

        // Group expenses by type (for summary sidebar)
        $expensesByType = $expenses->groupBy('expense_type');

        $totalExpenses    = $expenses->sum(fn($e) => $e->calculatedAmount());
        $totalBudget      = $expenses->whereNotNull('budget_amount')->sum('budget_amount');
        $pendingApproval  = $expenses->whereNull('approved_at')->count();
        $pendingReimbursement = $expenses->whereNotNull('approved_at')->whereNull('reimbursed_at')->count();

        // ── Per-convenor totals for reconciliation ────────────────────────
        $recon = $convenors->map(function ($convenor) use ($expenses, $grandTotalIncome, $totalExpenses) {
            $paid = $expenses
                ->where('paid_by_convenor_id', $convenor->id)
                ->sum(fn($e) => $e->calculatedAmount());

            // System expenses (payfast + cape_tennis_fee) are deducted from gross — not from convenors
            $systemExpenses = $expenses
                ->where('paid_by_convenor_id', $convenor->id)
                ->whereIn('expense_type', ['payfast', 'cape_tennis_fee'])
                ->sum(fn($e) => $e->calculatedAmount());

            $operationalPaid = $paid - $systemExpenses;

            return [
                'convenor'       => $convenor,
                'total_paid'     => $paid,
                'owed_back'      => $paid,          // full amount owed back from event funds
                'reimbursed'     => $expenses
                    ->where('paid_by_convenor_id', $convenor->id)
                    ->whereNotNull('reimbursed_at')
                    ->sum(fn($e) => $e->calculatedAmount()),
            ];
        });

        // Budget cap warning threshold (90%)
        $budgetCapWarning = $event->budget_cap
            ? ($totalExpenses / $event->budget_cap) >= 0.9
            : false;

        $netProfit = $grandTotalIncome - $totalExpenses;

        return view('backend.event.finances', compact(
            'event',
            'transactions',
            'totalGross',
            'totalPayfastFees',
            'totalCapeTennisFees',
            'totalEntries',
            'feePerEntry',
            'netRegistrationIncome',
            'incomeItems',
            'totalIncomeItems',
            'grandTotalIncome',
            'convenors',
            'expenses',
            'expenseTypes',
            'expensesByConvenor',
            'expensesByType',
            'totalExpenses',
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

        return back()->with('success', 'Expense added successfully.');
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

        return back()->with('success', 'Expense updated successfully.');
    }

    public function destroyExpense(EventExpense $expense)
    {
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        $expense->delete();

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

        return back()->with('success', 'Expense approved.');
    }

    public function reimburseExpense(EventExpense $expense)
    {
        $expense->update([
            'reimbursed_at' => now(),
            'reimbursed_by' => Auth::id(),
        ]);

        return back()->with('success', 'Reimbursement marked.');
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

        return back()->with('success', 'Income item added.');
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

        return back()->with('success', 'Income item updated.');
    }

    public function destroyIncomeItem(EventIncomeItem $item)
    {
        $item->delete();

        return back()->with('success', 'Income item deleted.');
    }

    /* ------------------------------------------------------------------ */
    /*  PRIVATE HELPERS                                                    */
    /* ------------------------------------------------------------------ */

    private function expenseTypes(): array
    {
        return [
            'balls'           => 'Balle',
            'venue'           => 'Bane (Venue)',
            'convenors'       => 'Convenors',
            'medals'          => 'Medalies',
            'couriers'        => 'Koeriersdiens',
            'airtime'         => 'Airtime/Data',
            'petrol'          => 'Petrol',
            'admin_fee'       => 'Adminfooi',
            'accommodation'   => 'Akkommodasie',
            'extras'          => 'Ekstra\'s',
            'payfast'         => 'PayFast Fooie',
            'cape_tennis_fee' => 'Cape Tennis Fooi',
            'other'           => 'Ander',
        ];
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
     * Auto-create locked system expense rows for PayFast fees and Cape Tennis fees
     * derived from actual transaction data, if they don't already exist.
     */
    private function autoSyncSystemExpenses(
        Event $event,
        float $totalPayfastFees,
        float $totalCapeTennisFees,
        $expenses
    ): void {
        $hasPayfast  = $expenses->whereIn('expense_type', ['payfast'])->count() > 0;
        $hasCT       = $expenses->whereIn('expense_type', ['cape_tennis_fee'])->count() > 0;

        if (!$hasPayfast && abs($totalPayfastFees) > 0) {
            EventExpense::create([
                'event_id'    => $event->id,
                'expense_type' => 'payfast',
                'description'  => 'PayFast fees (auto-synced)',
                'amount'       => abs($totalPayfastFees),
                'date'         => now(),
            ]);
        }

        if (!$hasCT && $totalCapeTennisFees > 0) {
            EventExpense::create([
                'event_id'    => $event->id,
                'expense_type' => 'cape_tennis_fee',
                'description'  => 'Cape Tennis fee (auto-synced)',
                'amount'       => $totalCapeTennisFees,
                'date'         => now(),
            ]);
        }
    }
}
