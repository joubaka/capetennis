<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Constants\RefundType;
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
     *  - refund_rows         : Collection of stdClass (type='refund'|'withdrawal', refund_status on each)
     *  - payout_rows         : Collection of stdClass (type='payout')
     *  - totals              : array (see buildTotals)
     *
     * refund_rows contains ALL withdrawn registrations:
     *   - type='refund'      → refund_status in (completed, pending)  — affects accounting
     *   - type='withdrawal'  → refund_status = not_refunded           — operational visibility only
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
            : $paymentRows->sum(fn($r) => $r->entryCount ?? 1);

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
    // Refund / Withdrawal rows
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build all withdrawn registration rows for the ledger.
     *
     * Row types returned:
     *   type='refund'     → refund_status completed|pending  — deducted from net revenue
     *   type='withdrawal' → refund_status not_refunded|null  — operational visibility, NOT deducted
     *
     * The $grossRefund <= 0 guard is intentionally removed for withdrawal rows so that
     * admin-entered (R 0.00) withdrawals remain visible in the operational ledger.
     */
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
                'not_refunded',
            ])
            ->get()
            ->map(function ($reg) use ($feePerEntry, $event) {
                $payment = $reg->paymentInfo();

                $refundStatus = $reg->refund_status ?? 'not_refunded';
                $isNoRefund   = $refundStatus === 'not_refunded';

                $grossRefund      = round((float) ($reg->refund_gross ?? 0), 2);
                $totalPaid        = round((float) ($payment['gross'] ?? 0) + (float) ($payment['wallet_paid'] ?? 0), 2);

                // For no-refund withdrawals: show the full entry fee if they paid anything,
                // or R 0 if admin-entered (no payment linked). Never show split amounts.
                $entryFee         = (float) ($event->entry_fee ?? $event->entryFee ?? 0);
                $originalPaymentGross = $isNoRefund
                    ? ($totalPaid > 0 ? $entryFee : 0.0)
                    : $totalPaid;

                $displayGross = $isNoRefund ? $originalPaymentGross : $grossRefund;

                $payfastFee   = abs((float) ($payment['fee'] ?? 0));
                $refundMethod = strtolower($reg->refund_method ?? '');

                // Row type: 'refund' affects accounting; 'withdrawal' is operational only
                $rowType = $isNoRefund ? 'withdrawal' : 'refund';

                $subtype = match($refundStatus) {
                    'completed' => $refundMethod ? "refund_{$refundMethod}" : 'refund_completed',
                    'pending'   => $refundMethod ? "refund_{$refundMethod}_pending" : 'refund_pending',
                    default     => 'withdrawal_no_refund',
                };

                $statusLabel = match($refundStatus) {
                    'completed'    => RefundType::label($refundMethod) . ' (Completed)',
                    'pending'      => RefundType::label($refundMethod ?: 'bank') . ' (Pending)',
                    'not_refunded' => 'No Refund',
                    default        => 'Withdrawn',
                };

                return (object) [
                    // ── Canonical normalized fields ───────────────────────────
                    'type'              => $rowType,
                    'subtype'           => $subtype,
                    'amount_gross'      => $displayGross,
                    'amount_fee'        => $isNoRefund ? 0 : $payfastFee,
                    'amount_net'        => $isNoRefund ? 0 : round(-$grossRefund + $payfastFee + $feePerEntry, 2),
                    'payment_method'    => $refundMethod ?: null,
                    'refund_status'     => $refundStatus,
                    'withdrawal_status' => $reg->status,
                    'status_label'      => $statusLabel,
                    'status_colour'     => $isNoRefund ? 'secondary' : RefundType::colour($refundStatus),
                    'source_tx_id'      => $payment['transaction_id'] ?? null,
                    'source_order_id'   => null,
                    'source_pf_id'      => $payment['pf_payment_id'] ?? null,
                    'cer_id'            => $reg->id,
                    'user_name'         => $reg->display_name,
                    'event_id'          => optional($reg->categoryEvent)->event_id,
                    // ── Legacy fields (Blade/view compatibility) ──────────────
                    'created_at'    => $reg->refunded_at ?? $reg->withdrawn_at ?? $reg->updated_at,
                    'player'        => $reg->display_name,
                    'category'      => optional($reg->categoryEvent->category)->name,
                    'method'        => $isNoRefund ? 'No Refund' : ucfirst($reg->refund_method ?? ''),
                    'pf_payment_id' => $payment['pf_payment_id'] ?? null,
                    'tx_id'         => $payment['transaction_id'] ?? null,
                    'paid_at'       => $payment['paid_at'] ?? null,
                    'refund_gross'  => $isNoRefund ? 0 : $grossRefund,
                    'refund_fee'    => $isNoRefund ? 0 : $payfastFee,
                    'refund_net'    => $isNoRefund ? 0 : round(-$grossRefund + $payfastFee + $feePerEntry, 2),
                    // gross/fee/net for display arithmetic — withdrawal rows are informational (0 impact)
                    'gross'         => $isNoRefund ? 0 : -$grossRefund,
                    'fee'           => $isNoRefund ? 0 : +$payfastFee,
                    'capeFee'       => $isNoRefund ? 0 : +$feePerEntry,
                    'net'           => $isNoRefund ? 0 : round(-$grossRefund + $payfastFee + $feePerEntry, 2),
                    // For display: original amount paid (shown on withdrawal rows)
                    'original_gross' => $originalPaymentGross,
                ];
            })
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
                // ── Canonical normalized fields ───────────────────────────
                'type'              => 'payout',
                'subtype'           => 'event_payout',
                'amount_gross'      => -(float) $p->amount,
                'amount_fee'        => 0,
                'amount_net'        => -(float) $p->amount,
                'payment_method'    => $p->payment_method,
                'refund_status'     => null,
                'withdrawal_status' => null,
                'status_label'      => 'Payout',
                'status_colour'     => 'dark',
                'source_tx_id'      => null,
                'source_order_id'   => null,
                'source_pf_id'      => null,
                'user_name'         => $p->recipient_name
                    ?? optional(optional($p->convenor)->user)->name
                    ?? '—',
                'event_id'          => $p->event_id,
                // ── Legacy fields ─────────────────────────────────────────
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

        // Only 'refund' type rows affect accounting — 'withdrawal' (not_refunded) are informational
        $accountingRefunds = $refundRows->where('type', 'refund');
        $noRefundRows      = $refundRows->where('type', 'withdrawal');

        $completedRefunds = $accountingRefunds->where('refund_status', CategoryEventRegistration::REFUND_COMPLETED);
        $pendingRefunds   = $accountingRefunds->where('refund_status', CategoryEventRegistration::REFUND_PENDING);

        // Only completed refunds reduce realized net
        $completedRefundGross     = round($completedRefunds->sum('refund_gross'), 2);
        $completedRefundNetImpact = round($completedRefunds->sum('net'), 2);

        $pendingRefundGross = round($pendingRefunds->sum('refund_gross'), 2);
        $noRefundCount      = $noRefundRows->count();

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
            'no_refund_count'        => $noRefundCount,
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

        // Admin entries (no PayFast, no wallet) are privately collected — no CT fee charged
        $capeFeeTx = ($method === 'Admin Entry')
            ? 0
            : -1 * round($feePerEntry * $entryCount, 2);
        $netTx     = round($grossTx + $pfFeeTx + $capeFeeTx, 2);

        $playerName = ($tx->pf_payment_id === null)
            ? trim(optional($tx->player)->name . ' ' . optional($tx->player)->surname)
            : optional($tx->user)->name;

        return (object) [
            // ── Canonical normalized fields ───────────────────────────────
            'type'             => 'payment',
            'subtype'          => strtolower(str_replace(' ', '_', $method)),
            'amount_gross'     => $grossTx,
            'amount_fee'       => $pfFeeTx,
            'amount_net'       => $netTx,
            'payment_method'   => $method,
            'refund_status'    => null,
            'withdrawal_status'=> null,
            'status_label'     => $method,
            'status_colour'    => 'primary',
            'source_tx_id'     => $tx->id,
            'source_order_id'  => optional($tx->order)->id,
            'source_pf_id'     => $tx->pf_payment_id,
            'user_name'        => $playerName ?: optional($tx->user)->name,
            'event_id'         => $tx->event_id,
            // ── Legacy fields (keep for Blade/view compatibility) ─────────
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
                    // ── Canonical normalized fields ───────────────────────
                    'type'              => 'payment',
                    'subtype'           => 'wallet_payment',
                    'amount_gross'      => $gross,
                    'amount_fee'        => $capeFeeTx,
                    'amount_net'        => round($gross + $capeFeeTx, 2),
                    'payment_method'    => 'Wallet',
                    'refund_status'     => null,
                    'withdrawal_status' => null,
                    'status_label'      => 'Wallet',
                    'status_colour'     => 'info',
                    'source_tx_id'      => null,
                    'source_order_id'   => $order?->id,
                    'source_pf_id'      => null,
                    'user_name'         => $user?->name ?? '—',
                    'event_id'          => null,
                    // ── Legacy fields ─────────────────────────────────────
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
