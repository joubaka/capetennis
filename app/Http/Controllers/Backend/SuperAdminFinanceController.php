<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventPayout;
use App\Models\SiteSetting;
use App\Models\TeamPaymentOrder;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use App\Models\RegistrationOrder;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SuperAdminFinanceController extends Controller
{
    public function __construct(private FinancialLedgerService $ledger)
    {
    }

    /* ------------------------------------------------------------------ */
    /*  INDEX – All-events financial summary                               */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        $allEvents = Event::with(['incomeItems', 'convenors.user'])
            ->orderByDesc('start_date')
            ->get();

        // ── Financial Year helpers ────────────────────────────────────────────
        $availableFYs = $allEvents
            ->filter(fn($e) => $e->start_date)
            ->map(fn($e) => (string) $e->start_date->year)
            ->unique()
            ->sort()
            ->values();

        $currentFY = request('fy');
        if (! $availableFYs->contains($currentFY)) {
            $currentFY = $availableFYs->last() ?? (string) now()->year;
        }

        $eventsForFY = $allEvents->filter(
            fn($e) => $e->start_date && (string) $e->start_date->year === $currentFY
        );

        // ── Build per-event ledger summaries via shared service ───────────────
        $financeByEvent = $eventsForFY->map(
            fn($event) => $this->ledger->buildFySummaryRow($event)
        );

        $financeSummary = [
            'gross_payments'   => round($financeByEvent->sum('gross_payments'), 2),
            'completed_refunds' => round($financeByEvent->sum('completed_refunds'), 2),
            'pending_refunds'  => round($financeByEvent->sum('pending_refunds'), 2),
            'total_gross'      => round($financeByEvent->sum('gross_payments'), 2),
            'total_income'     => round($financeByEvent->sum('total_income'), 2),
            'total_entries'    => $financeByEvent->sum('total_entries'),
            'total_paid_out'   => round($financeByEvent->sum('total_paid_out'), 2),
            'balance'          => round($financeByEvent->sum('balance'), 2),
        ];

        return view('backend.superadmin.finances', compact(
            'financeByEvent',
            'financeSummary',
            'availableFYs',
            'currentFY'
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  SHOW – Single event: transactions + payouts                        */
    /* ------------------------------------------------------------------ */

    public function show(Event $event)
    {
        $isTeamEvent = $event->isTeam();
        $feePerEntry = (float) $event->cape_tennis_fee;

        // ── Build ledger via shared service ──────────────────────────────
        $ledgerData  = $this->ledger->buildForEvent($event);
        $paymentRows = $ledgerData['paymentRows'];
        $refundRows  = $ledgerData['refundRows'];
        $payoutRows  = $ledgerData['payoutRows'];
        $totals      = $ledgerData['totals'];

        // ── Merged chronological ledger for the view ──────────────────────
        $transactions = collect()
            ->merge($paymentRows)
            ->merge($refundRows)
            ->merge($payoutRows)
            ->sortByDesc('created_at')
            ->values();

        // ── Totals (aliased for view compatibility) ───────────────────────
        $totalGross          = $totals['gross_payments'];
        $totalPayfastFees    = $totals['pf_fees'];
        $totalCapeTennisFees = $totals['cape_fees'];
        $netTournamentIncome = $totals['net_revenue'];
        $totalPaidOut        = $totals['total_paid_out'];
        $balance             = $totals['balance'];
        $grossPayments       = $totals['gross_payments'];
        $completedRefunds    = $totals['completed_refunds'];
        $pendingRefunds      = $totals['pending_refunds'];

        $totalEntries = $isTeamEvent
            ? $paymentRows->count()
            : $paymentRows->sum(fn($t) => $t->entryCount ?? 1);

        $refundCount = $refundRows->count();

        // ── Payout models for the form ────────────────────────────────────
        $payoutModels = EventPayout::with(['convenor.user', 'paidByUser'])
            ->where('event_id', $event->id)
            ->orderByDesc('paid_at')
            ->get();

        // ── Convenors for payout form ─────────────────────────────────────
        $convenors = $event->convenors()->with('user')
            ->orderByRaw("FIELD(role, 'hoof', 'hulp', 'admin')")
            ->get();

        // ── Registrations eligible for super-admin full refund ─────────────
        $eligibleForRefund = CategoryEventRegistration::with([
                'players',
                'user',
                'categoryEvent.category',
                'payfastTransaction',
            ])
            ->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
            ->whereHas('payfastTransaction', fn ($q) => $q->where('is_test', false))
            ->where(fn ($q) => $q
                ->whereNull('refund_status')
                ->orWhere('refund_status', '!=', 'completed')
            )
            ->get();

        $eligibleTeamOrders = collect();
        if ($isTeamEvent) {
            $eligibleTeamOrders = TeamPaymentOrder::with(['player', 'user'])
                ->where('event_id', $event->id)
                ->where(fn ($q) => $q->where('payfast_paid', true)->orWhere('wallet_debited', true))
                ->where(fn ($q) => $q
                    ->whereNull('refund_status')
                    ->orWhere('refund_status', '!=', 'completed')
                )
                ->get();
        }

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
            'grossPayments',
            'completedRefunds',
            'pendingRefunds',
            'totalPayfastFees',
            'totalCapeTennisFees',
            'netTournamentIncome',
            'totalPaidOut',
            'balance',
            'eligibleForRefund',
            'eligibleTeamOrders'
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

        $payout = EventPayout::create([
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

        activity('payout')
            ->performedOn($payout)
            ->causedBy(Auth::user())
            ->withProperties([
                'event_id'       => $event->id,
                'event_name'     => $event->name,
                'amount'         => $payout->amount,
                'recipient'      => $payout->recipient_name,
                'payment_method' => $payout->payment_method,
                'reference'      => $payout->reference,
                'paid_at'        => $payout->paid_at,
            ])
            ->log("Payout created: R{$payout->amount} for event '{$event->name}'");

        return back()->with('success', 'Payout recorded successfully.');
    }

    /* ------------------------------------------------------------------ */
    /*  DESTROY PAYOUT                                                     */
    /* ------------------------------------------------------------------ */

    public function destroyPayout(EventPayout $payout)
    {
        $event = $payout->event;

        $snapshot = [
            'payout_id'      => $payout->id,
            'event_id'       => $event->id,
            'event_name'     => $event->name,
            'amount'         => $payout->amount,
            'recipient'      => $payout->recipient_name,
            'payment_method' => $payout->payment_method,
            'reference'      => $payout->reference,
            'paid_at'        => $payout->paid_at,
        ];

        $payout->delete();

        activity('payout')
            ->causedBy(Auth::user())
            ->withProperties(array_merge($snapshot, ['action' => 'deleted']))
            ->log("Payout deleted: R{$snapshot['amount']} for event '{$snapshot['event_name']}'");

        return redirect()
            ->route('superadmin.finances.event', $event)
            ->with('success', 'Payout deleted.');
    }

    /* ------------------------------------------------------------------ */
    /*  FULL REFUND – single registration (individual event)               */
    /* ------------------------------------------------------------------ */

    public function storeFullRefund(
        Request $request,
        Event $event,
        CategoryEventRegistration $registration,
        \App\Domain\Refunds\Services\RefundExecutionService $refundService
    ) {
        $request->validate([
            'method'     => 'required|in:wallet,bank',
            'percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($registration->refund_status === CategoryEventRegistration::REFUND_COMPLETED) {
            return back()->withErrors('This registration has already been fully refunded.');
        }

        $payment = $registration->paymentInfo();
        if (empty($payment)) {
            return back()->withErrors('Payment information not found for this registration.');
        }

        $walletPaid = $payment['wallet_paid'] ?? 0;
        $gross      = round((float) $payment['gross'] + (float) $walletPaid, 2);

        if ($gross <= 0) {
            return back()->withErrors('No refundable amount found.');
        }

        $percentage  = (float) ($request->input('percentage') ?? 0);
        $fee         = round($gross * ($percentage / 100), 2);
        $net         = round($gross - $fee, 2);
        $method      = $request->input('method');
        $refundLabel = $percentage > 0
            ? "Partial refund ({$percentage}% deducted)"
            : 'Full refund';

        // Mark status/withdrawal fields before entering the service
        $registration->status       = 'withdrawn';
        $registration->withdrawn_at = $registration->withdrawn_at ?? now();
        $registration->save();

        $statusOverrides = [
            'refund_method' => $method,
            'refund_gross'  => $gross,
            'refund_fee'    => $fee,
            'refund_net'    => $net,
        ];

        $meta = [
            'registration_id' => $registration->id,
            'event_id'        => $event->id,
            'gross'           => $gross,
            'fee'             => $fee,
            'percentage'      => $percentage,
            'reference'       => $event->name,
            'initiated_by'    => 'super_admin',
        ];

        if ($method === 'wallet') {
            $user = $registration->user;

            if (!$user) {
                return back()->withErrors('User not found for this registration.');
            }

            $wallet = $user->wallet ?? $user->wallet()->create([]);

            try {
                $refundService->executeWalletRefund(
                    $registration,
                    $wallet,
                    $net,
                    'admin_full_refund',
                    $registration->id,
                    $meta,
                    $statusOverrides
                );

                activity('refund')
                    ->performedOn($registration)
                    ->causedBy(Auth::user())
                    ->withProperties(array_merge($meta, ['method' => 'wallet', 'net' => $net]))
                    ->log("Super-admin {$refundLabel} wallet refund R{$net}");

                return back()->with('success', "{$refundLabel} of R" . number_format($net, 2) . " credited to {$user->name}'s wallet.");

            } catch (\Throwable $e) {
                Log::error('ADMIN FULL REFUND FAILED (wallet/registration)', [
                    'registration_id' => $registration->id,
                    'error'           => $e->getMessage(),
                ]);
                return back()->withErrors('Wallet refund failed: ' . $e->getMessage());
            }
        }

        // ── Bank / PayFast path ───────────────────────────────────────────
        $registration->update(array_merge($statusOverrides, [
            'refund_status' => CategoryEventRegistration::REFUND_PENDING,
        ]));

        $pfPaymentId = $payment['pf_payment_id'] ?? null;

        if (!empty($pfPaymentId)) {
            try {
                $payfast = new \App\Services\Payfast();
                $result  = $payfast->refund($pfPaymentId, $net, "{$refundLabel} (admin)");

                if ($result['success']) {
                    $refundService->executeBankRefund($registration, array_merge($statusOverrides, [
                        'refund_method' => 'bank',
                    ]));

                    activity('refund')
                        ->performedOn($registration)
                        ->causedBy(Auth::user())
                        ->withProperties(array_merge($meta, [
                            'method'        => 'payfast',
                            'pf_payment_id' => $pfPaymentId,
                            'net'           => $net,
                        ]))
                        ->log("Super-admin {$refundLabel} PayFast refund R{$net}");

                    return back()->with('success', "{$refundLabel} of R" . number_format($net, 2) . " processed via PayFast.");
                }

                Log::warning('ADMIN FULL REFUND: PayFast failed — marked pending', [
                    'registration_id' => $registration->id,
                    'error'           => $result['error'] ?? 'unknown',
                ]);

            } catch (\Throwable $e) {
                Log::error('ADMIN FULL REFUND: PayFast exception — marked pending', [
                    'registration_id' => $registration->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        activity('refund')
            ->performedOn($registration)
            ->causedBy(Auth::user())
            ->withProperties(array_merge($meta, ['method' => 'bank', 'net' => $net]))
            ->log("Super-admin {$refundLabel} bank refund R{$net} (pending)");

        return back()->with('success', "Bank refund of R" . number_format($net, 2) . " marked as pending. Please process manually.");
    }

    /* ------------------------------------------------------------------ */
    /*  FULL REFUND – team payment order                                   */
    /* ------------------------------------------------------------------ */

    public function storeFullRefundTeam(
        Request $request,
        Event $event,
        TeamPaymentOrder $order,
        \App\Domain\Refunds\Services\RefundExecutionService $refundService
    ) {
        $request->validate([
            'method'     => 'required|in:wallet,bank',
            'percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($order->refund_status === 'completed') {
            return back()->withErrors('This order has already been fully refunded.');
        }

        $gross = round((float) $order->total_amount, 2);

        if ($gross <= 0) {
            return back()->withErrors('No refundable amount found.');
        }

        $percentage  = (float) ($request->input('percentage') ?? 0);
        $fee         = round($gross * ($percentage / 100), 2);
        $net         = round($gross - $fee, 2);
        $method      = $request->input('method');
        $refundLabel = $percentage > 0
            ? "Partial refund ({$percentage}% deducted)"
            : 'Full refund';

        $statusOverrides = [
            'refund_method' => $method,
            'refund_gross'  => $gross,
            'refund_fee'    => $fee,
            'refund_net'    => $net,
        ];

        $meta = [
            'order_id'     => $order->id,
            'event_id'     => $event->id,
            'gross'        => $gross,
            'fee'          => $fee,
            'percentage'   => $percentage,
            'reference'    => $event->name,
            'initiated_by' => 'super_admin',
        ];

        if ($method === 'wallet') {
            $user = $order->user;

            if (!$user) {
                return back()->withErrors('User not found for this order.');
            }

            $wallet = $user->wallet ?? $user->wallet()->create([]);

            try {
                $refundService->executeWalletRefund(
                    $order,
                    $wallet,
                    $net,
                    'admin_full_refund_team',
                    $order->id,
                    $meta,
                    $statusOverrides
                );

                activity('refund')
                    ->performedOn($order)
                    ->causedBy(Auth::user())
                    ->withProperties(array_merge($meta, ['method' => 'wallet', 'net' => $net]))
                    ->log("Super-admin {$refundLabel} wallet refund (team) R{$net}");

                return back()->with('success', "{$refundLabel} of R" . number_format($net, 2) . " credited to {$user->name}'s wallet.");

            } catch (\Throwable $e) {
                Log::error('ADMIN FULL REFUND FAILED (wallet/team)', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
                return back()->withErrors('Wallet refund failed: ' . $e->getMessage());
            }
        }

        // ── Bank / PayFast path ───────────────────────────────────────────
        $order->update(array_merge($statusOverrides, ['refund_status' => 'pending']));

        $pfPaymentId = $order->payfast_pf_payment_id ?? null;

        if (!empty($pfPaymentId)) {
            try {
                $payfast = new \App\Services\Payfast();
                $result  = $payfast->refund($pfPaymentId, $net, "{$refundLabel} team (admin)");

                if ($result['success']) {
                    $refundService->executeBankRefund($order, array_merge($statusOverrides, [
                        'refund_method' => 'bank',
                    ]));

                    activity('refund')
                        ->performedOn($order)
                        ->causedBy(Auth::user())
                        ->withProperties(array_merge($meta, [
                            'method'        => 'payfast',
                            'pf_payment_id' => $pfPaymentId,
                            'net'           => $net,
                        ]))
                        ->log("Super-admin {$refundLabel} PayFast refund (team) R{$net}");

                    return back()->with('success', "{$refundLabel} of R" . number_format($net, 2) . " processed via PayFast.");
                }

                Log::warning('ADMIN FULL REFUND (team): PayFast failed — marked pending', [
                    'order_id' => $order->id,
                    'error'    => $result['error'] ?? 'unknown',
                ]);

            } catch (\Throwable $e) {
                Log::error('ADMIN FULL REFUND (team): PayFast exception — marked pending', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        activity('refund')
            ->performedOn($order)
            ->causedBy(Auth::user())
            ->withProperties(array_merge($meta, ['method' => 'bank', 'net' => $net]))
            ->log("Super-admin {$refundLabel} bank refund (team) R{$net} (pending)");

        return back()->with('success', "Bank refund of R" . number_format($net, 2) . " marked as pending. Please process manually.");
    }
}
