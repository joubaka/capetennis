<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventPayout;
use App\Models\SiteSetting;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperAdminFinanceController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  INDEX – All-events financial summary                               */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        $allEvents = Event::with(['incomeItems', 'convenors.user'])
            ->orderByDesc('start_date')
            ->get();

        $allTransactions = Transaction::with(['order.items'])
            ->where('transaction_type', 'Registration')
            ->where('amount_gross', '>', 0)
            ->where('is_test', false)
            ->get()
            ->groupBy('event_id');

        $allRefunds = CategoryEventRegistration::with([
                'categoryEvent',
                'payfastTransaction.order.items',
            ])
            ->where('status', 'withdrawn')
            ->where('refund_status', 'completed')
            ->whereHas('payfastTransaction', fn ($q) => $q->where('is_test', false))
            ->get()
            ->groupBy(fn ($r) => $r->categoryEvent->event_id);

        $allPayouts = EventPayout::all()->groupBy('event_id');

        $financeByEvent = $allEvents->map(function ($event) use ($allTransactions, $allRefunds, $allPayouts) {
            $feePerEntry = (float) $event->cape_tennis_fee;
            $txForEvent  = $allTransactions->get($event->id, collect());

            $paymentLedger = $txForEvent->map(function ($tx) use ($feePerEntry) {
                $payfastGross = round((float) $tx->amount_gross, 2);
                $walletUsed   = round((float) optional($tx->order)->wallet_reserved, 2);
                $entryCount   = max(1, $tx->order?->items?->count() ?? 0);
                $pfFee        = SiteSetting::calculatePayfastFee($payfastGross);
                $capeFee      = round($feePerEntry * $entryCount, 2);

                return [
                    'gross'   => $payfastGross + $walletUsed,
                    'fee'     => -$pfFee,
                    'capeFee' => -$capeFee,
                    'net'     => round($payfastGross + $walletUsed - $pfFee - $capeFee, 2),
                    'items'   => $tx->order?->items ?? collect(),
                ];
            });

            $refundLedger = $allRefunds->get($event->id, collect())
                ->map(function ($reg) use ($feePerEntry) {
                    $payment = $reg->paymentInfo();
                    if (empty($payment)) {
                        return null;
                    }
                    $grossPaid  = (float) ($payment['gross'] ?? 0);
                    $payfastFee = abs((float) ($payment['fee'] ?? 0));

                    return [
                        'gross'   => -$grossPaid,
                        'fee'     => +$payfastFee,
                        'capeFee' => +$feePerEntry,
                        'net'     => round(-$grossPaid + $payfastFee + $feePerEntry, 2),
                        'items'   => collect(),
                    ];
                })
                ->filter();

            $ledger = $paymentLedger->merge($refundLedger);

            $totalGross   = round($ledger->sum('gross'), 2);
            $netIncome    = round($ledger->sum('net'), 2);
            $totalPaidOut = $allPayouts->get($event->id, collect())->sum('amount');

            $totalEntries = $event->isTeam()
                ? $txForEvent->count()
                : $paymentLedger->flatMap(fn ($r) => $r['items'])->count();

            return [
                'event'          => $event,
                'total_gross'    => $totalGross,
                'total_income'   => $netIncome,
                'total_entries'  => $totalEntries,
                'total_paid_out' => $totalPaidOut,
                'balance'        => round($netIncome - $totalPaidOut, 2),
            ];
        });

        $financeSummary = [
            'total_gross'    => $financeByEvent->sum('total_gross'),
            'total_income'   => $financeByEvent->sum('total_income'),
            'total_entries'  => $financeByEvent->sum('total_entries'),
            'total_paid_out' => $financeByEvent->sum('total_paid_out'),
            'balance'        => $financeByEvent->sum('balance'),
        ];

        return view('backend.superadmin.finances', compact('financeByEvent', 'financeSummary'));
    }

    /* ------------------------------------------------------------------ */
    /*  SHOW – Single event: transactions + payouts                        */
    /* ------------------------------------------------------------------ */

    public function show(Event $event)
    {
        $feePerEntry = (float) $event->cape_tennis_fee;
        $isTeamEvent = $event->isTeam();

        // ── Payment rows ─────────────────────────────────────────────────
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
                'type'          => 'payment',
                'created_at'    => $tx->created_at,
                'player'        => optional($tx->user)->name,
                'method'        => $method,
                'gross'         => $grossTx,
                'fee'           => $pfFeeTx,
                'capeFee'       => $capeFeeTx,
                'net'           => $netTx,
                'pf_payment_id' => $tx->pf_payment_id,
                'tx_id'         => $tx->id,
                'paid_at'       => $tx->created_at,
                'order'         => $tx->order,
                'entryCount'    => $entryCount,
                'payfastGross'  => $payfastGross,
                'walletUsed'    => $walletUsed,
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
            $payment = $reg->paymentInfo();
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

        // ── Payout rows ───────────────────────────────────────────────────
        $payoutModels = EventPayout::with(['convenor.user', 'paidByUser'])
            ->where('event_id', $event->id)
            ->orderByDesc('paid_at')
            ->get();

        $payoutRows = $payoutModels->map(fn ($p) => (object) [
            'type'       => 'payout',
            'created_at' => $p->paid_at ?? $p->created_at,
            'player'     => $p->display_name,
            'method'     => $p->payment_method,
            'gross'      => -$p->amount,
            'fee'        => 0,
            'capeFee'    => 0,
            'net'        => -$p->amount,
            'description' => $p->description,
            'reference'  => $p->reference,
        ]);

        // ── Merged ledger ─────────────────────────────────────────────────
        $transactions = collect()
            ->merge($paymentRows)
            ->merge($refundRows)
            ->merge($payoutRows)
            ->sortByDesc('created_at')
            ->values();

        // ── Totals ────────────────────────────────────────────────────────
        $totalGross          = $paymentRows->sum('gross') + $refundRows->sum('gross');
        $totalPayfastFees    = $paymentRows->sum('fee') + $refundRows->sum('fee');
        $totalCapeTennisFees = $paymentRows->sum('capeFee') + $refundRows->sum('capeFee');
        $netTournamentIncome = $totalGross + $totalPayfastFees + $totalCapeTennisFees;
        $totalPaidOut        = $payoutModels->sum('amount');
        $balance             = round($netTournamentIncome - $totalPaidOut, 2);

        $totalEntries = $isTeamEvent
            ? $paymentRows->count()
            : $paymentRows->flatMap(fn ($t) => optional($t->order)->items ?? collect())->count();

        $refundCount = $refundRows->count();

        // ── Convenors for payout form ─────────────────────────────────────
        $convenors = $event->convenors()->with('user')
            ->orderByRaw("FIELD(role, 'hoof', 'hulp', 'admin')")
            ->get();

        return view('backend.superadmin.event-finances', compact(
            'event',
            'transactions',
            'payoutModels',
            'convenors',
            'feePerEntry',
            'isTeamEvent',
            'totalEntries',
            'refundCount',
            'totalGross',
            'totalPayfastFees',
            'totalCapeTennisFees',
            'netTournamentIncome',
            'totalPaidOut',
            'balance'
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  STORE PAYOUT                                                       */
    /* ------------------------------------------------------------------ */

    public function storePayout(Request $request, Event $event)
    {
        $validated = $request->validate([
            'convenor_id'    => 'nullable|exists:event_convenors,id',
            'recipient_name' => 'nullable|string|max:150',
            'amount'         => 'required|numeric|min:0.01',
            'description'    => 'nullable|string|max:255',
            'payment_method' => 'required|string|max:50',
            'reference'      => 'nullable|string|max:150',
            'paid_at'        => 'nullable|date',
        ]);

        EventPayout::create([
            'event_id'       => $event->id,
            'convenor_id'    => $validated['convenor_id'] ?? null,
            'recipient_name' => $validated['recipient_name'] ?? null,
            'amount'         => $validated['amount'],
            'description'    => $validated['description'] ?? null,
            'payment_method' => $validated['payment_method'],
            'reference'      => $validated['reference'] ?? null,
            'paid_by'        => Auth::id(),
            'paid_at'        => $validated['paid_at'] ?? now(),
        ]);

        return back()->with('success', 'Payout recorded successfully.');
    }

    /* ------------------------------------------------------------------ */
    /*  DESTROY PAYOUT                                                     */
    /* ------------------------------------------------------------------ */

    public function destroyPayout(EventPayout $payout)
    {
        $event = $payout->event;
        $payout->delete();

        return redirect()
            ->route('superadmin.finances.event', $event)
            ->with('success', 'Payout deleted.');
    }
}
