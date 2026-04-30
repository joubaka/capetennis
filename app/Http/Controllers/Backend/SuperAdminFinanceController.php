<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventPayout;
use App\Models\SiteSetting;
use App\Models\TeamPaymentOrder;
use App\Models\Transaction;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // ── Financial Year helpers ────────────────────────────────────────────
        // FY = calendar year of start_date (e.g. "2025")
        $availableFYs = $allEvents
            ->filter(fn ($e) => $e->start_date)
            ->map(fn ($e) => (string) $e->start_date->year)
            ->unique()
            ->sort()
            ->values();

        $currentFY = request('fy');
        if (! $availableFYs->contains($currentFY)) {
            $currentFY = $availableFYs->last() ?? (string) now()->year;
        }

        // Filter to selected FY
        $eventsForFY = $allEvents->filter(
            fn ($e) => $e->start_date && (string) $e->start_date->year === $currentFY
        );

        // ── Load transactions & refunds for the filtered event set ────────────
        $eventIds = $eventsForFY->pluck('id');

        $allTransactions = Transaction::with(['order.items'])
            ->where('transaction_type', 'Registration')
            ->where('amount_gross', '>', 0)
            ->where('is_test', false)
            ->whereIn('event_id', $eventIds)
            ->get()
            ->groupBy('event_id');

        $allRefunds = CategoryEventRegistration::with([
                'categoryEvent',
                'payfastTransaction.order.items',
            ])
            ->where('status', 'withdrawn')
            ->where('refund_status', 'completed')
            ->whereHas('payfastTransaction', fn ($q) => $q->where('is_test', false))
            ->whereHas('categoryEvent', fn ($q) => $q->whereIn('event_id', $eventIds))
            ->get()
            ->groupBy(fn ($r) => $r->categoryEvent->event_id);

        $allPayouts = EventPayout::whereIn('event_id', $eventIds)->get()->groupBy('event_id');

        $financeByEvent = $eventsForFY->map(function ($event) use ($allTransactions, $allRefunds, $allPayouts) {
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
                'event'            => $event,
                'total_gross'      => $totalGross,
                'total_income'     => $netIncome,
                'total_entries'    => $totalEntries,
                'total_paid_out'   => $totalPaidOut,
                'balance'          => round($netIncome - $totalPaidOut, 2),
                'has_transactions' => $txForEvent->isNotEmpty(),
            ];
        });

        $financeSummary = [
            'total_gross'    => $financeByEvent->sum('total_gross'),
            'total_income'   => $financeByEvent->sum('total_income'),
            'total_entries'  => $financeByEvent->sum('total_entries'),
            'total_paid_out' => $financeByEvent->sum('total_paid_out'),
            'balance'        => $financeByEvent->sum('balance'),
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

    /* ------------------------------------------------------------------ */
    /*  FULL REFUND – single registration (individual event)               */
    /* ------------------------------------------------------------------ */

    public function storeFullRefund(Request $request, Event $event, CategoryEventRegistration $registration)
    {
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

        $percentage = (float) ($request->input('percentage') ?? 0);
        $fee        = round($gross * ($percentage / 100), 2);
        $net        = round($gross - $fee, 2);
        $method     = $request->input('method');

        $baseUpdate = [
            'status'        => 'withdrawn',
            'withdrawn_at'  => $registration->withdrawn_at ?? now(),
            'refund_method' => $method,
            'refund_gross'  => $gross,
            'refund_fee'    => $fee,
            'refund_net'    => $net,
        ];

        $refundLabel = $percentage > 0
            ? "Partial refund ({$percentage}% deducted)"
            : 'Full refund';

        if ($method === 'wallet') {
            $user = $registration->user;

            if (!$user) {
                return back()->withErrors('User not found for this registration.');
            }

            $wallet = $user->wallet ?? $user->wallet()->create([]);

            try {
                DB::transaction(function () use ($registration, $wallet, $gross, $fee, $net, $percentage, $event, $baseUpdate) {
                    app(WalletService::class)->credit(
                        $wallet,
                        $net,
                        'admin_full_refund',
                        $registration->id,
                        [
                            'registration_id' => $registration->id,
                            'event_id'        => $event->id,
                            'gross'           => $gross,
                            'fee'             => $fee,
                            'percentage'      => $percentage,
                            'method'          => 'wallet',
                            'reference'       => $event->name,
                            'initiated_by'    => 'super_admin',
                        ]
                    );

                    $registration->update(array_merge($baseUpdate, [
                        'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
                        'refunded_at'   => now(),
                    ]));
                });

                activity('refund')
                    ->performedOn($registration)
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'registration_id' => $registration->id,
                        'method'          => 'wallet',
                        'gross'           => $gross,
                        'fee'             => $fee,
                        'percentage'      => $percentage,
                        'net'             => $net,
                        'event'           => $event->name,
                        'initiated_by'    => 'super_admin',
                    ])
                    ->log("Super-admin {$refundLabel} wallet refund R{$net}");

                return back()->with('success', "{$refundLabel} of R" . number_format($net, 2) . " credited to {$user->name}'s wallet.");

            } catch (DuplicateTransactionException $e) {
                $registration->update(array_merge($baseUpdate, [
                    'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
                    'refunded_at'   => now(),
                ]));
                return back()->with('success', 'Wallet refund already processed (state synced).');
            } catch (\Throwable $e) {
                Log::error('ADMIN FULL REFUND FAILED (wallet/registration)', [
                    'registration_id' => $registration->id,
                    'error'           => $e->getMessage(),
                ]);
                return back()->withErrors('Wallet refund failed: ' . $e->getMessage());
            }
        }

        // ── Bank / PayFast path ───────────────────────────────────────────
        $registration->update(array_merge($baseUpdate, [
            'refund_status' => CategoryEventRegistration::REFUND_PENDING,
        ]));

        $pfPaymentId = $payment['pf_payment_id'] ?? null;

        if (!empty($pfPaymentId)) {
            try {
                $payfast = new \App\Services\Payfast();
                $result  = $payfast->refund($pfPaymentId, $net, "{$refundLabel} (admin)");

                if ($result['success']) {
                    $registration->update([
                        'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
                        'refunded_at'   => now(),
                    ]);

                    activity('refund')
                        ->performedOn($registration)
                        ->causedBy(Auth::user())
                        ->withProperties([
                            'registration_id' => $registration->id,
                            'method'          => 'payfast',
                            'pf_payment_id'   => $pfPaymentId,
                            'gross'           => $gross,
                            'fee'             => $fee,
                            'percentage'      => $percentage,
                            'net'             => $net,
                            'event'           => $event->name,
                            'initiated_by'    => 'super_admin',
                        ])
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
            ->withProperties([
                'registration_id' => $registration->id,
                'method'          => 'bank',
                'gross'           => $gross,
                'fee'             => $fee,
                'percentage'      => $percentage,
                'net'             => $net,
                'event'           => $event->name,
                'initiated_by'    => 'super_admin',
            ])
            ->log("Super-admin {$refundLabel} bank refund R{$net} (pending)");

        return back()->with('success', "Bank refund of R" . number_format($net, 2) . " marked as pending. Please process manually.");
    }

    /* ------------------------------------------------------------------ */
    /*  FULL REFUND – team payment order                                   */
    /* ------------------------------------------------------------------ */

    public function storeFullRefundTeam(Request $request, Event $event, TeamPaymentOrder $order)
    {
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

        $percentage = (float) ($request->input('percentage') ?? 0);
        $fee        = round($gross * ($percentage / 100), 2);
        $net        = round($gross - $fee, 2);
        $method     = $request->input('method');

        $refundLabel = $percentage > 0
            ? "Partial refund ({$percentage}% deducted)"
            : 'Full refund';

        $baseUpdate = [
            'refund_method' => $method,
            'refund_gross'  => $gross,
            'refund_fee'    => $fee,
            'refund_net'    => $net,
        ];

        if ($method === 'wallet') {
            $user = $order->user;

            if (!$user) {
                return back()->withErrors('User not found for this order.');
            }

            $wallet = $user->wallet ?? $user->wallet()->create([]);

            try {
                DB::transaction(function () use ($order, $wallet, $gross, $fee, $net, $percentage, $event, $baseUpdate) {
                    app(WalletService::class)->credit(
                        $wallet,
                        $net,
                        'admin_full_refund_team',
                        $order->id,
                        [
                            'order_id'     => $order->id,
                            'event_id'     => $event->id,
                            'gross'        => $gross,
                            'fee'          => $fee,
                            'percentage'   => $percentage,
                            'method'       => 'wallet',
                            'reference'    => $event->name,
                            'initiated_by' => 'super_admin',
                        ]
                    );

                    $order->update(array_merge($baseUpdate, [
                        'refund_status' => 'completed',
                        'refunded_at'   => now(),
                    ]));
                });

                activity('refund')
                    ->performedOn($order)
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'order_id'     => $order->id,
                        'method'       => 'wallet',
                        'gross'        => $gross,
                        'fee'          => $fee,
                        'percentage'   => $percentage,
                        'net'          => $net,
                        'event'        => $event->name,
                        'initiated_by' => 'super_admin',
                    ])
                    ->log("Super-admin {$refundLabel} wallet refund (team) R{$net}");

                return back()->with('success', "{$refundLabel} of R" . number_format($net, 2) . " credited to {$user->name}'s wallet.");

            } catch (DuplicateTransactionException $e) {
                $order->update(array_merge($baseUpdate, [
                    'refund_status' => 'completed',
                    'refunded_at'   => now(),
                ]));
                return back()->with('success', 'Wallet refund already processed (state synced).');
            } catch (\Throwable $e) {
                Log::error('ADMIN FULL REFUND FAILED (wallet/team)', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
                return back()->withErrors('Wallet refund failed: ' . $e->getMessage());
            }
        }

        // ── Bank / PayFast path ───────────────────────────────────────────
        $order->update(array_merge($baseUpdate, [
            'refund_status' => 'pending',
        ]));

        $pfPaymentId = $order->payfast_pf_payment_id ?? null;

        if (!empty($pfPaymentId)) {
            try {
                $payfast = new \App\Services\Payfast();
                $result  = $payfast->refund($pfPaymentId, $net, "{$refundLabel} team (admin)");

                if ($result['success']) {
                    $order->update([
                        'refund_status' => 'completed',
                        'refunded_at'   => now(),
                    ]);

                    activity('refund')
                        ->performedOn($order)
                        ->causedBy(Auth::user())
                        ->withProperties([
                            'order_id'      => $order->id,
                            'method'        => 'payfast',
                            'pf_payment_id' => $pfPaymentId,
                            'gross'         => $gross,
                            'fee'           => $fee,
                            'percentage'    => $percentage,
                            'net'           => $net,
                            'event'         => $event->name,
                            'initiated_by'  => 'super_admin',
                        ])
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
            ->withProperties([
                'order_id'     => $order->id,
                'method'       => 'bank',
                'gross'        => $gross,
                'fee'          => $fee,
                'percentage'   => $percentage,
                'net'          => $net,
                'event'        => $event->name,
                'initiated_by' => 'super_admin',
            ])
            ->log("Super-admin {$refundLabel} bank refund (team) R{$net} (pending)");

        return back()->with('success', "Bank refund of R" . number_format($net, 2) . " marked as pending. Please process manually.");
    }
}
