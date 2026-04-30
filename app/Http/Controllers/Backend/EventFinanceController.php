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
        $feePerEntry = (float) $event->cape_tennis_fee;
        $isTeamEvent = $event->isTeam();

        // ── Payment transactions ──────────────────────────────────────────
        $rawTransactions = Transaction::with([
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

        // Build payment rows — same DTO format as EventTransactionController
        $paymentRows = $rawTransactions->map(function ($tx) use ($feePerEntry) {
            $items        = collect(optional($tx->order)->items ?? []);
            $entryCount   = max(1, $items->count());
            $payfastGross = round((float) $tx->amount_gross, 2);
            $walletUsed   = round((float) optional($tx->order)->wallet_reserved, 2);
            $grossTx      = $payfastGross + $walletUsed;
            $pfFeeTx      = -1 * SiteSetting::calculatePayfastFee($payfastGross);
            $capeFeeTx    = -1 * round($feePerEntry * $entryCount, 2);
            $netTx        = round($grossTx + $pfFeeTx + $capeFeeTx, 2);
            $method       = $walletUsed > 0 ? 'PayFast + Wallet' : 'PayFast';

            return (object) [
                'type'         => 'payment',
                'created_at'   => $tx->created_at,
                'player'       => optional($tx->user)->name,
                'method'       => $method,
                'gross'        => $grossTx,
                'fee'          => $pfFeeTx,
                'capeFee'      => $capeFeeTx,
                'net'          => $netTx,
                'pf_payment_id' => $tx->pf_payment_id,
                'tx_id'        => $tx->id,
                'paid_at'      => $tx->created_at,
                'order'        => $tx->order,
                'entryCount'   => $entryCount,
                'payfastGross' => $payfastGross,
                'walletUsed'   => $walletUsed,
            ];
        });

        // ── Refund rows ───────────────────────────────────────────────────
        $refundRegs = CategoryEventRegistration::with([
            'players',
            'categoryEvent.category',
            'payfastTransaction',
        ])
            ->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
            ->where('status', 'withdrawn')
            ->where('refund_status', 'completed')
            ->whereHas('payfastTransaction', fn ($q) => $q->where('is_test', false))
            ->get();

        $refundRows = $refundRegs->map(function ($reg) use ($feePerEntry) {
            $payment    = $reg->paymentInfo();
            if (empty($payment)) {
                return null;
            }
            $grossPaid  = (float) ($payment['gross'] ?? 0);
            $payfastFee = abs((float) ($payment['fee'] ?? 0));

            return (object) [
                'type'          => 'refund',
                'created_at'    => $reg->refunded_at ?? $reg->updated_at,
                'player'        => $reg->display_name,
                'category'      => optional($reg->categoryEvent->category)->name,
                'method'        => ucfirst($reg->refund_method ?? ''),
                'pf_payment_id' => $payment['pf_payment_id'] ?? null,
                'tx_id'         => $payment['transaction_id'] ?? null,
                'paid_at'       => $payment['paid_at'] ?? null,
                'gross'         => -$grossPaid,
                'fee'           => +$payfastFee,
                'capeFee'       => +$feePerEntry,
                'net'           => (-$grossPaid + $payfastFee + $feePerEntry),
            ];
        })->filter()->values();

        // ── Merged ledger ─────────────────────────────────────────────────
        $transactions = collect()
            ->merge($paymentRows)
            ->merge($refundRows)
            ->sortByDesc('created_at')
            ->values();

        // ── Totals ────────────────────────────────────────────────────────
        $totalGross          = $transactions->sum('gross');
        $totalPayfastFees    = $transactions->sum('fee');
        $totalCapeTennisFees = $transactions->sum('capeFee');
        $netTournamentIncome = $transactions->sum('net');

        $totalEntries = $isTeamEvent
            ? $paymentRows->count()
            : $paymentRows->flatMap(fn ($t) => optional($t->order)->items ?? collect())->count();

        $refundCount = $refundRows->count();

        return view('backend.event.finances', [
            'event'               => $event,
            'transactions'        => $transactions,
            'feePerEntry'         => $feePerEntry,
            'isTeamEvent'         => $isTeamEvent,
            'totalEntries'        => $totalEntries,
            'refundCount'         => $refundCount,
            'totalGross'          => $totalGross,
            'totalPayfastFees'    => $totalPayfastFees,
            'totalCapeTennisFees' => $totalCapeTennisFees,
            'netTournamentIncome' => $netTournamentIncome,
        ]);
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
