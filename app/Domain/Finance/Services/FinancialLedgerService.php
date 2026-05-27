<?php

namespace App\Domain\Finance\Services;

use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventPayout;
use App\Models\RegistrationOrder;
use App\Models\SiteSetting;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;

/**
 * FinancialLedgerService
 *
 * Single authoritative ledger builder for an event's financial state.
 *
 * Used by:
 *  - SuperAdminFinanceController (dashboard FY summary + per-event view)
 *  - EventTransactionController (PDF / export)
 *  - Future CSV / Excel exports
 *
 * Design decisions:
 *  - Gross Payments (gross_payments) = sum of all incoming money, NEVER reduced by refunds.
 *  - Completed Refunds reduce realized net revenue.
 *  - Pending Refunds are reported as a separate liability.
 *  - Wallet-only, hybrid, and PayFast-only orders are all included.
 *  - No double-counting: each registration/order is counted once.
 */
class FinancialLedgerService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the complete ledger for a single event.
     *
     * Returns an associative array:
     *  - payment_rows        : Collection of stdClass (type='payment')
     *  - refund_rows         : Collection of stdClass (type='refund', refund_status on each)
     *  - payout_rows         : Collection of stdClass (type='payout')
     *  - totals              : array (see buildTotals)
     */
    public function buildForEvent(Event $event): array
    {
        $feePerEntry = (float) $event->cape_tennis_fee;

        $paymentRows = $this->buildPaymentRows($event, $feePerEntry);
        $refundRows  = $this->buildRefundRows($event, $feePerEntry);
        $payoutRows  = $this->buildPayoutRows($event);

        $totals = $this->buildTotals($paymentRows, $refundRows, $payoutRows);

        return compact('paymentRows', 'refundRows', 'payoutRows', 'totals');
    }

    /**
     * Build a compact summary for the FY dashboard (no per-row detail needed).
     */
    public function buildFySummaryRow(Event $event): array
    {
        $feePerEntry     = (float) $event->cape_tennis_fee;
        $paymentRows     = $this->buildPaymentRows($event, $feePerEntry);
        $refundRows      = $this->buildRefundRows($event, $feePerEntry);
        $payoutRows      = $this->buildPayoutRows($event);
        $totals          = $this->buildTotals($paymentRows, $refundRows, $payoutRows);

        $isTeamEvent  = $event->isTeam();
        $totalEntries = $isTeamEvent
            ? $paymentRows->count()
            : $paymentRows->flatMap(fn($r) => optional($r->order)->items ?? collect())->count();

        return [
            'event'            => $event,
            'gross_payments'   => $totals['gross_payments'],
            'completed_refunds' => $totals['completed_refunds'],
            'pending_refunds'  => $totals['pending_refunds'],
            'total_gross'      => $totals['gross_payments'],   // raw inflow (label compatibility)
            'total_income'     => $totals['net_revenue'],
            'total_entries'    => $totalEntries,
            'total_paid_out'   => $totals['total_paid_out'],
            'balance'          => $totals['balance'],
            'has_transactions' => $paymentRows->isNotEmpty(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Payment rows
    // ─────────────────────────────────────────────────────────────────────────

    public function buildPaymentRows(Event $event, float $feePerEntry): Collection
    {
        // ── PayFast + Hybrid transactions ─────────────────────────────────
        $rawTransactions = Transaction::with([
            'user',
            'player',
            'order.items.player',
            'order.items.category_event.category',
        ])
            ->where('event_id', $event->id)
            ->where('transaction_type', 'Registration')
            ->where('amount_gross', '>=', 0)
            ->where('is_test', false)
            ->whereNull('archived_at')
            ->orderByDesc('created_at')
            ->get();

        $pfRows = $rawTransactions->map(fn($tx) => $this->mapPayfastRow($tx, $feePerEntry));

        // ── Wallet-only orders (no PayFast tx at all) ─────────────────────
        $walletOnlyRows = $this->buildWalletOnlyRows($event, $feePerEntry);

        return collect()->merge($pfRows)->merge($walletOnlyRows);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Refund rows
    // ─────────────────────────────────────────────────────────────────────────

    public function buildRefundRows(Event $event, float $feePerEntry): Collection
    {
        return CategoryEventRegistration::with([
            'players',
            'categoryEvent.category',
            'payfastTransaction',
        ])
            ->whereHas('categoryEvent', fn($q) => $q->where('event_id', $event->id))
            ->where('status', 'withdrawn')
            ->whereIn('refund_status', [
                CategoryEventRegistration::REFUND_COMPLETED,
                CategoryEventRegistration::REFUND_PENDING,
            ])
            ->get()
            ->map(function ($reg) use ($feePerEntry) {
                $payment = $reg->paymentInfo();

                // Use the CER's own refund_gross — the authoritative amount owed back.
                // paymentInfo()['gross'] is the per-entry payment split of the original
                // order and is NOT the refund amount when one PayFast transaction covered
                // multiple registrations.
                $grossRefund = round((float) ($reg->refund_gross ?? $payment['gross'] ?? 0), 2);
                if ($grossRefund <= 0) {
                    return null;
                }

                $payfastFee = abs((float) ($payment['fee'] ?? 0));

                return (object) [
                    'type'          => 'refund',
                    'refund_status' => $reg->refund_status,
                    'created_at'    => $reg->refunded_at ?? $reg->updated_at,
                    'player'        => $reg->display_name,
                    'category'      => optional($reg->categoryEvent->category)->name,
                    'method'        => ucfirst($reg->refund_method ?? ''),
                    'pf_payment_id' => $payment['pf_payment_id'] ?? null,
                    'tx_id'         => $payment['transaction_id'] ?? null,
                    'paid_at'       => $payment['paid_at'] ?? null,
                    // Accounting: refund rows are positive deduction values
                    'refund_gross'  => $grossRefund,
                    'refund_fee'    => $payfastFee,
                    'refund_net'    => round(-$grossRefund + $payfastFee + $feePerEntry, 2),
                    // Legacy display fields (negative = outflow)
                    'gross'         => -$grossRefund,
                    'fee'           => +$payfastFee,
                    'capeFee'       => +$feePerEntry,
                    'net'           => round(-$grossRefund + $payfastFee + $feePerEntry, 2),
                ];
            })
            ->filter()
            ->values();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Payout rows
    // ─────────────────────────────────────────────────────────────────────────

    public function buildPayoutRows(Event $event): Collection
    {
        return EventPayout::with(['convenor.user', 'paidByUser'])
            ->where('event_id', $event->id)
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn($p) => (object) [
                'type'        => 'payout',
                'created_at'  => $p->paid_at ?? $p->created_at,
                'player'      => $p->recipient_name
                    ?? optional(optional($p->convenor)->user)->name
                    ?? '—',
                'method'      => $p->payment_method,
                'gross'       => -(float) $p->amount,
                'fee'         => 0,
                'capeFee'     => 0,
                'net'         => -(float) $p->amount,
                'description' => $p->description,
                'reference'   => $p->reference,
                'model'       => $p,
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Totals
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build totals from pre-computed payment/refund/payout collections.
     *
     * Gross Payments  = total money received (PayFast + wallet, all methods).
     * Completed Refunds = only refunds whose refund_status = 'completed'.
     * Pending Refunds   = only refunds whose refund_status = 'pending' (liability).
     * Net Revenue       = Gross Payments − Completed Refund gross − all fees.
     * Balance           = Net Revenue − Total Paid Out.
     */
    public function buildTotals(Collection $paymentRows, Collection $refundRows, Collection $payoutRows): array
    {
        $grossPayments    = round($paymentRows->sum('gross'), 2);
        $pfFees           = round($paymentRows->sum('fee'), 2);
        $capeFees         = round($paymentRows->sum('capeFee'), 2);

        $completedRefunds = $refundRows->where('refund_status', CategoryEventRegistration::REFUND_COMPLETED);
        $pendingRefunds   = $refundRows->where('refund_status', CategoryEventRegistration::REFUND_PENDING);

        // Only completed refunds reduce realized net
        $completedRefundGross  = round($completedRefunds->sum('refund_gross'), 2);
        $completedRefundFeeAdj = round($completedRefunds->sum('refund_fee'), 2);   // fees recovered
        $completedCapeFeeAdj   = round($completedRefunds->count() * 0, 2);         // cape fee recovered (per-refund)

        // Sum the 'net' field of completed refund rows (already negative net impact)
        $completedRefundNetImpact = round($completedRefunds->sum('net'), 2);

        $pendingRefundGross       = round($pendingRefunds->sum('refund_gross'), 2);

        // Net revenue = payments net + completed refund net impact
        $paymentsNet = round($grossPayments + $pfFees + $capeFees, 2);
        $netRevenue  = round($paymentsNet + $completedRefundNetImpact, 2);

        $totalPaidOut = round($payoutRows->sum(fn($r) => abs($r->net ?? 0)), 2);
        $balance      = round($netRevenue - $totalPaidOut, 2);

        return [
            'gross_payments'         => $grossPayments,
            'pf_fees'                => $pfFees,
            'cape_fees'              => $capeFees,
            'payments_net'           => $paymentsNet,
            'completed_refunds'      => $completedRefundGross,
            'completed_refund_adj'   => $completedRefundNetImpact,
            'pending_refunds'        => $pendingRefundGross,
            'net_revenue'            => $netRevenue,
            'total_paid_out'         => $totalPaidOut,
            'balance'                => $balance,
            // Legacy aliases for view compatibility
            'total_gross'            => $grossPayments,
            'total_income'           => $netRevenue,
            'totalGross'             => $grossPayments,
            'totalPayfastFees'       => $pfFees,
            'totalCapeTennisFees'    => $capeFees,
            'netTournamentIncome'    => $netRevenue,
            'totalPaidOut'           => $totalPaidOut,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────────

    private function mapPayfastRow(Transaction $tx, float $feePerEntry): object
    {
        $items        = collect(optional($tx->order)->items ?? []);
        $entryCount   = max(1, $items->count());
        $payfastGross = round((float) $tx->amount_gross, 2);
        $walletUsed   = round((float) optional($tx->order)->wallet_reserved, 2);
        $grossTx      = $payfastGross + $walletUsed;

        if ($tx->pf_payment_id === null && $walletUsed == 0) {
            $pfFeeTx = 0;
            $method  = 'Admin Entry';
        } elseif ($walletUsed > 0 && $payfastGross > 0) {
            $pfFeeTx = -1 * SiteSetting::calculatePayfastFee($payfastGross);
            $method  = 'PayFast + Wallet';
        } else {
            $pfFeeTx = -1 * SiteSetting::calculatePayfastFee($payfastGross);
            $method  = 'PayFast';
        }

        $capeFeeTx = -1 * round($feePerEntry * $entryCount, 2);
        $netTx     = round($grossTx + $pfFeeTx + $capeFeeTx, 2);

        $playerName = ($tx->pf_payment_id === null)
            ? trim(optional($tx->player)->name . ' ' . optional($tx->player)->surname)
            : optional($tx->user)->name;

        return (object) [
            'type'          => 'payment',
            'created_at'    => $tx->created_at,
            'player'        => $playerName ?: optional($tx->user)->name,
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
    }

    private function buildWalletOnlyRows(Event $event, float $feePerEntry): Collection
    {
        $walletOnlyOrderIds = RegistrationOrder::whereHas('items', function ($q) use ($event) {
                $q->whereHas('category_event', fn($q2) => $q2->where('event_id', $event->id));
            })
            ->where('wallet_reserved', '>', 0)
            ->where(function ($q) {
                $q->whereNull('payfast_amount_due')->orWhere('payfast_amount_due', 0);
            })
            ->pluck('id');

        return WalletTransaction::with(['wallet.payable'])
            ->whereIn('source_id', $walletOnlyOrderIds)
            ->where('source_type', 'event_registration_wallet_payment')
            ->where('type', 'debit')
            ->get()
            ->map(function ($wt) use ($feePerEntry) {
                $order      = RegistrationOrder::with('items')->find($wt->source_id);
                $entryCount = max(1, $order?->items?->count() ?? 1);
                $gross      = round((float) $wt->amount, 2);
                $capeFeeTx  = -1 * round($feePerEntry * $entryCount, 2);
                $user       = $wt->wallet?->payable;

                return (object) [
                    'type'          => 'payment',
                    'created_at'    => $wt->created_at,
                    'player'        => $user?->name ?? '—',
                    'method'        => 'Wallet',
                    'gross'         => $gross,
                    'fee'           => 0,
                    'capeFee'       => $capeFeeTx,
                    'net'           => round($gross + $capeFeeTx, 2),
                    'pf_payment_id' => null,
                    'tx_id'         => null,
                    'paid_at'       => $wt->created_at,
                    'order'         => $order,
                    'entryCount'    => $entryCount,
                    'payfastGross'  => 0,
                    'walletUsed'    => $gross,
                ];
            });
    }
}
