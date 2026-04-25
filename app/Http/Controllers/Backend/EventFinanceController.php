<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\EventExpense;
use App\Models\EventIncomeItem;
use App\Models\EventVenueConvenor;
use App\Models\ExpenseType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // Net profit: grandTotalIncome is already net of system fees;
        // subtract only operational expenses to avoid double-counting.
        $netProfit = $grandTotalIncome - $totalExpenses;

        // ── Per-convenor totals for reconciliation (operational only) ─────
        $recon = $convenors->map(function ($convenor) use ($operationalExpenses, $netProfit) {
            $paid = $operationalExpenses
                ->where('paid_by_convenor_id', $convenor->id)
                ->sum(fn($e) => $e->calculatedAmount());

            $profitSharePct    = (float) ($convenor->profit_share_pct ?? 0);
            $profitShareAmount = $netProfit > 0 ? round($netProfit * $profitSharePct / 100, 2) : 0.0;

            $reimbursed = $operationalExpenses
                ->where('paid_by_convenor_id', $convenor->id)
                ->whereNotNull('reimbursed_at')
                ->sum(fn($e) => $e->calculatedAmount());

            return [
                'convenor'           => $convenor,
                'total_paid'         => $paid,
                'owed_back'          => $paid,
                'reimbursed'         => $reimbursed,
                'profit_share_pct'   => $profitSharePct,
                'profit_share_amount'=> $profitShareAmount,
                // Final payout = expenses owed back + profit share
                'final_payout'       => ($paid - $reimbursed) + $profitShareAmount,
            ];
        });

        // Budget cap warning threshold (90%) — based on operational expenses only
        $budgetCapWarning = $event->budget_cap
            ? ($totalExpenses / $event->budget_cap) >= 0.9
            : false;

        // All expense types including system types (for the manage-types modal)
        $allExpenseTypes = ExpenseType::ordered()->get();

        // ── Venue convenors for this event ────────────────────────────────
        $venueConvenors = $event->venueConvenors()->get();

        // ── Per-venue entry counts (via draws linked to this event) ──────
        // draws → draw_venues → venues, entries counted from category_event_registrations
        $venueEntrySummary = Venue::query()
            ->join('draw_venues', 'draw_venues.venue_id', '=', 'venues.id')
            ->join('draws', 'draws.id', '=', 'draw_venues.draw_id')
            ->join('category_events', 'category_events.id', '=', 'draws.category_event_id')
            ->join('category_event_registrations', 'category_event_registrations.category_event_id', '=', 'category_events.id')
            ->where('draws.event_id', $event->id)
            ->select(
                'venues.id',
                'venues.name',
                DB::raw('COUNT(DISTINCT category_event_registrations.registration_id) as entry_count')
            )
            ->groupBy('venues.id', 'venues.name')
            ->orderBy('venues.name')
            ->get();

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
            'netProfit',
            'venueEntrySummary',
            'venueConvenors'
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  EXPENSES – CRUD                                                    */
    /* ------------------------------------------------------------------ */

    public function storeExpense(Request $request, Event $event)
    {
        $validated = $request->validate([
            'expense_type'            => 'required|string|max:50',
            'paid_by_convenor_ids'    => 'nullable|array',
            'paid_by_convenor_ids.*'  => 'integer|exists:event_convenors,id',
            'paid_by_convenor_id'     => 'nullable|exists:event_convenors,id',
            'convenor_name'           => 'nullable|string|max:100',
            'description'             => 'nullable|string|max:255',
            'recipient_name'          => 'nullable|string|max:150',
            'amount'                  => 'required|numeric|min:0',
            'quantity'                => 'nullable|numeric|min:0',
            'unit_price'              => 'nullable|numeric|min:0',
            'budget_amount'           => 'nullable|numeric|min:0',
            'date'                    => 'nullable|date',
            'receipt'                 => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        // Compute amount from qty × unit_price when both provided
        $amount = $this->resolveAmount($validated);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')
                ->store("event_receipts/{$event->id}", 'public');
        }

        $base = [
            'event_id'             => $event->id,
            'expense_type'         => $validated['expense_type'],
            'convenor_name'        => $validated['convenor_name'] ?? null,
            'description'          => $validated['description'] ?? null,
            'recipient_name'       => $validated['recipient_name'] ?? null,
            'amount'               => $amount,
            'quantity'             => $validated['quantity'] ?? null,
            'unit_price'           => $validated['unit_price'] ?? null,
            'budget_amount'        => $validated['budget_amount'] ?? null,
            'receipt_path'         => $receiptPath,
            'date'                 => $validated['date'] ?? now(),
        ];

        // Multi-director mode: create one expense per selected director
        $convenorIds = $validated['paid_by_convenor_ids'] ?? null;
        if (!empty($convenorIds)) {
            foreach ($convenorIds as $convenorId) {
                EventExpense::create(array_merge($base, ['paid_by_convenor_id' => $convenorId]));
            }
            $count = count($convenorIds);
            $msg = $count === 1 ? 'Expense added successfully.' : "{$count} expenses added successfully.";
        } else {
            EventExpense::create(array_merge($base, [
                'paid_by_convenor_id' => $validated['paid_by_convenor_id'] ?? null,
            ]));
            $msg = 'Expense added successfully.';
        }

        return $request->wantsJson()
            ? response()->json(['message' => $msg])
            : back()->with('success', $msg);
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
        $request->validate([
            'user_ids'         => 'required|array|min:1',
            'user_ids.*'       => 'integer|exists:users,id',
            'role'             => 'nullable|string|max:20',
            'profit_share_pct' => 'nullable|numeric|min:0|max:100',
            'starts_at'        => 'nullable|date',
            'expires_at'       => 'nullable|date|after_or_equal:starts_at',
        ]);

        $role            = $request->input('role', 'hulp');
        $profitSharePct  = $request->input('profit_share_pct');
        $startsAt        = $request->input('starts_at');
        $expiresAt       = $request->input('expires_at');

        $added    = 0;
        $skipped  = 0;
        $created  = [];

        foreach ($request->input('user_ids') as $userId) {
            $exists = EventConvenor::where('event_id', $event->id)
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $convenor = EventConvenor::create([
                'event_id'         => $event->id,
                'user_id'          => $userId,
                'role'             => $role,
                'profit_share_pct' => $profitSharePct ?? null,
                'starts_at'        => $startsAt ?? null,
                'expires_at'       => $expiresAt ?? null,
            ]);

            $convenor->load('user');
            $created[] = [
                'id'          => $convenor->id,
                'user_name'   => $convenor->user->name ?? 'Unknown',
                'role'        => $convenor->role,
                'destroy_url' => route('admin.events.finances.convenor.destroy', $convenor),
            ];
            $added++;
        }

        $message = $added === 1 ? '1 event director added.' : "{$added} event directors added.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (already assigned).";
        }

        if ($added === 0) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }
            return back()->with('error', $message);
        }

        return $request->wantsJson()
            ? response()->json(['message' => $message, 'convenors' => $created])
            : back()->with('success', $message);
    }

    public function updateConvenor(Request $request, EventConvenor $convenor)
    {
        $validated = $request->validate([
            'role'             => 'nullable|string|max:20',
            'profit_share_pct' => 'nullable|numeric|min:0|max:100',
            'starts_at'        => 'nullable|date',
            'expires_at'       => 'nullable|date|after_or_equal:starts_at',
        ]);

        $convenor->update([
            'role'             => $validated['role'] ?? $convenor->role,
            'profit_share_pct' => array_key_exists('profit_share_pct', $validated)
                                    ? $validated['profit_share_pct']
                                    : $convenor->profit_share_pct,
            'starts_at'        => $validated['starts_at'] ?? null,
            'expires_at'       => $validated['expires_at'] ?? null,
        ]);

        return $request->wantsJson()
            ? response()->json(['message' => 'Convenor updated.'])
            : back()->with('success', 'Convenor updated.');
    }

    public function destroyConvenor(EventConvenor $convenor)
    {
        $id = $convenor->id;
        $convenor->delete();

        return request()->wantsJson()
            ? response()->json(['message' => 'Convenor removed.', 'id' => $id])
            : back()->with('success', 'Convenor removed.');
    }

    /* ------------------------------------------------------------------ */
    /*  VENUE CONVENORS – CRUD                                             */
    /* ------------------------------------------------------------------ */

    public function storeVenueConvenor(Request $request, Event $event)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
        ]);

        $exists = EventVenueConvenor::where('event_id', $event->id)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            $message = 'A venue convenor with that name already exists for this event.';
            return $request->wantsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        $vc = EventVenueConvenor::create([
            'event_id' => $event->id,
            'name'     => $validated['name'],
        ]);

        $message = "Venue convenor \"{$vc->name}\" added.";

        return $request->wantsJson()
            ? response()->json([
                'message'       => $message,
                'venueConvenor' => [
                    'id'          => $vc->id,
                    'name'        => $vc->name,
                    'destroy_url' => route('admin.events.finances.venue-convenor.destroy', $vc),
                ],
            ])
            : back()->with('success', $message);
    }

    public function destroyVenueConvenor(EventVenueConvenor $venueConvenor)
    {
        $id   = $venueConvenor->id;
        $name = $venueConvenor->name;
        $venueConvenor->delete();

        return request()->wantsJson()
            ? response()->json(['message' => "Venue convenor \"{$name}\" removed.", 'id' => $id])
            : back()->with('success', "Venue convenor \"{$name}\" removed.");
    }
}
