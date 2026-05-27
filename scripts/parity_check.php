<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Event;
use App\Models\CategoryEventRegistration;

$service = app(FinancialLedgerService::class);

// Find a recent event with both payfast AND refunds
$refundEventIds = DB::table('category_event_registrations')
    ->whereIn('refund_status', ['pending', 'completed', 'refunded'])
    ->join('category_events', 'category_event_registrations.category_event_id', '=', 'category_events.id')
    ->join('events', 'category_events.event_id', '=', 'events.id')
    ->where('events.id', '>=', 200) // recent events
    ->groupBy('category_events.event_id')
    ->selectRaw('category_events.event_id, COUNT(*) as cnt')
    ->orderByDesc('cnt')
    ->limit(3)
    ->pluck('event_id');

echo "Events with recent refunds: " . $refundEventIds->implode(', ') . "\n";

$selectedEvents = [230, 238, $refundEventIds->first() ?? 234];

foreach ($selectedEvents as $eventId) {
    $event = Event::find($eventId);
    if (!$event) { echo "Event $eventId not found\n"; continue; }

    echo "\n" . str_repeat('=', 70) . "\n";
    echo "EVENT {$eventId}: {$event->name}\n";
    echo str_repeat('=', 70) . "\n";

    // Cape Tennis fee (from site settings - use default 10%)
    $feePercent = 10.0;

    // --- FinancialLedgerService totals ---
    $paymentRows = $service->buildPaymentRows($event, $feePercent);
    $refundRows  = $service->buildRefundRows($event, $feePercent);
    $payoutRows  = $service->buildPayoutRows($event);
    $totals      = $service->buildTotals($paymentRows, $refundRows, $payoutRows);
    $fySummary   = $service->buildFySummaryRow($event);

    echo "\n--- FinancialLedgerService::buildTotals() ---\n";
    echo "  gross_payments:    R" . number_format($totals['gross_payments'],2) . "\n";
    echo "  completed_refunds: R" . number_format($totals['completed_refunds'],2) . "\n";
    echo "  pending_refunds:   R" . number_format($totals['pending_refunds'],2) . "\n";
    echo "  net_revenue:       R" . number_format($totals['net_revenue'],2) . "\n";
    echo "  total_paid_out:    R" . number_format($totals['total_paid_out'],2) . "\n";
    echo "  balance:           R" . number_format($totals['balance'],2) . "\n";
    echo "  pf_fees:           R" . number_format($totals['pf_fees'],2) . "\n";
    echo "  cape_fees:         R" . number_format($totals['cape_fees'],2) . "\n";

    echo "\n--- FinancialLedgerService::buildFySummaryRow() ---\n";
    echo "  gross_payments:    R" . number_format($fySummary['gross_payments'],2) . "\n";
    echo "  completed_refunds: R" . number_format($fySummary['completed_refunds'],2) . "\n";
    echo "  pending_refunds:   R" . number_format($fySummary['pending_refunds'],2) . "\n";
    echo "  total_income:      R" . number_format($fySummary['total_income'],2) . "\n";
    echo "  total_paid_out:    R" . number_format($fySummary['total_paid_out'],2) . "\n";
    echo "  balance:           R" . number_format($fySummary['balance'],2) . "\n";

    // --- Raw DB totals (what old dashboard inline logic would have computed) ---
    $rawPfGross = DB::table('transactions_pf')
        ->where('event_id', $eventId)
        ->whereNull('archived_at')
        ->sum('amount_gross');
    $rawPfFee = DB::table('transactions_pf')
        ->where('event_id', $eventId)
        ->whereNull('archived_at')
        ->sum('amount_fee');

    $rawWalletDebit = DB::table('registration_orders')
        ->join('registration_order_items', 'registration_orders.id', '=', 'registration_order_items.order_id')
        ->join('category_events', 'registration_order_items.category_event_id', '=', 'category_events.id')
        ->where('category_events.event_id', $eventId)
        ->where('registration_orders.wallet_debited', 1)
        ->where('registration_orders.payfast_paid', 0)
        ->sum('registration_orders.wallet_reserved');

    $rawCompletedRefunds = DB::table('category_event_registrations')
        ->join('category_events', 'category_event_registrations.category_event_id', '=', 'category_events.id')
        ->where('category_events.event_id', $eventId)
        ->whereIn('category_event_registrations.refund_status', ['completed', 'refunded'])
        ->sum('category_event_registrations.refund_gross');

    $rawPendingRefunds = DB::table('category_event_registrations')
        ->join('category_events', 'category_event_registrations.category_event_id', '=', 'category_events.id')
        ->where('category_events.event_id', $eventId)
        ->where('category_event_registrations.refund_status', 'pending')
        ->sum('category_event_registrations.refund_gross');

    $rawPayouts = DB::table('event_payouts')
        ->where('event_id', $eventId)
        ->sum('amount');

    echo "\n--- Raw DB (old-style inline controller math) ---\n";
    echo "  pf_gross:          R" . number_format($rawPfGross, 2) . "\n";
    echo "  pf_fee:            R" . number_format($rawPfFee, 2) . "\n";
    echo "  wallet_only_debit: R" . number_format($rawWalletDebit, 2) . "\n";
    echo "  completed_refunds: R" . number_format($rawCompletedRefunds, 2) . "\n";
    echo "  pending_refunds:   R" . number_format($rawPendingRefunds, 2) . "\n";
    echo "  payouts:           R" . number_format($rawPayouts, 2) . "\n";

    $grossMatch   = abs($totals['gross_payments']    - ($rawPfGross + $rawWalletDebit)) < 0.01;
    $refundMatch  = abs($totals['completed_refunds']  - $rawCompletedRefunds) < 0.01;
    $pendingMatch = abs($totals['pending_refunds']    - $rawPendingRefunds) < 0.01;
    $payoutMatch  = abs($totals['total_paid_out']     - $rawPayouts) < 0.01;

    echo "\n--- Parity ---\n";
    echo "  gross_payments: "     . ($grossMatch   ? "✅ MATCH" : "❌ MISMATCH (ledger={$totals['gross_payments']} raw=" . ($rawPfGross+$rawWalletDebit) . ")") . "\n";
    echo "  completed_refunds: " . ($refundMatch  ? "✅ MATCH" : "❌ MISMATCH (ledger={$totals['completed_refunds']} raw={$rawCompletedRefunds})") . "\n";
    echo "  pending_refunds: "   . ($pendingMatch ? "✅ MATCH" : "❌ MISMATCH (ledger={$totals['pending_refunds']} raw={$rawPendingRefunds})") . "\n";
    echo "  payouts: "           . ($payoutMatch  ? "✅ MATCH" : "❌ MISMATCH (ledger={$totals['total_paid_out']} raw={$rawPayouts})") . "\n";

    // Payment method breakdown
    echo "\n--- Payment method breakdown ---\n";
    $methods = $paymentRows->groupBy('method');
    foreach ($methods as $method => $rows) {
        echo "  $method: " . $rows->count() . " rows, gross=R" . number_format($rows->sum('gross'),2) . "\n";
    }

    // Refund status breakdown
    if ($refundRows->isNotEmpty()) {
        echo "\n--- Refund breakdown ---\n";
        foreach ($refundRows as $r) {
            $rArr = (array)$r;
            $id     = $rArr['cer_id'] ?? $rArr['id'] ?? '?';
            $method = $rArr['refund_method'] ?? 'N/A';
            echo "  CER#{$id} status={$r->refund_status} gross=R{$r->refund_gross} method={$method}\n";
        }
    }
}

echo "\n\n=== REFUND SCENARIO VALIDATION ===\n";
// Find one of each refund type
$refundTypes = [
    'pending bank' => ['refund_status' => 'pending', 'refund_method' => 'bank'],
    'completed bank' => ['refund_status' => 'completed', 'refund_method' => 'bank'],
    'completed wallet' => ['refund_status' => 'completed', 'refund_method' => 'wallet'],
    'refunded' => ['refund_status' => 'refunded', 'refund_method' => null],
];
foreach ($refundTypes as $label => $filter) {
    $q = DB::table('category_event_registrations')
        ->where('refund_status', $filter['refund_status'])
        ->whereNotNull('refund_gross');
    if ($filter['refund_method']) $q->where('refund_method', $filter['refund_method']);
    $cer = $q->orderByDesc('id')->first();
    if ($cer) {
        echo "\n$label — CER#{$cer->id}\n";
        echo "  refund_status={$cer->refund_status} refund_method={$cer->refund_method}\n";
        echo "  refund_gross={$cer->refund_gross} refund_fee={$cer->refund_fee} refund_net={$cer->refund_net}\n";
        echo "  withdrawn_at={$cer->withdrawn_at} refunded_at={$cer->refunded_at}\n";
        // Wallet credit if applicable
        if ($cer->wallet_transaction_id) {
            $wt = DB::table('wallet_transactions')->find($cer->wallet_transaction_id);
            echo "  wallet_txn.id={$wt->id} type={$wt->type} amount={$wt->amount}\n";
        } else {
            echo "  wallet_transaction_id=NULL\n";
        }
    } else {
        echo "\n$label — NONE FOUND\n";
    }
}
