<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== PENDING REFUND CERs WITHOUT WITHDRAWAL RECORD ===\n";
$cerIds = [17850, 17899, 17900];
$cers = DB::table('category_event_registrations')
    ->whereIn('id', $cerIds)
    ->get();
foreach ($cers as $cer) {
    echo sprintf(
        "CER.id=%-6s reg_id=%-6s refund_status=%-15s refund_method=%-12s refund_gross=%-8s withdrawn_at=%s\n",
        $cer->id, $cer->registration_id, $cer->refund_status, $cer->refund_method ?? 'NULL',
        $cer->refund_gross ?? 'NULL', $cer->withdrawn_at ?? 'NULL'
    );
    // Check withdrawal records via category_event_id + registration_id
    $withdrawals = DB::table('withdrawals')
        ->where('registration_id', $cer->registration_id)
        ->get(['id','category_event_id','registration_id','user_id','created_at']);
    if ($withdrawals->isEmpty()) {
        echo "  NO withdrawal record\n";
    } else {
        foreach ($withdrawals as $w) {
            echo "  withdrawal.id={$w->id} cat_event_id={$w->category_event_id} reg_id={$w->registration_id} user_id={$w->user_id} created_at={$w->created_at}\n";
        }
    }
}

echo "\n=== ORDER 9160 FULL DETAIL ===\n";
$order = DB::table('registration_orders')->find(9160);
echo "order.id={$order->id} user_id={$order->user_id} payfast_paid={$order->payfast_paid} pay_status={$order->pay_status}\n";
echo "  wallet_reserved={$order->wallet_reserved} wallet_debited={$order->wallet_debited} payfast_amount_due={$order->payfast_amount_due}\n";
echo "  payfast_pf_payment_id={$order->payfast_pf_payment_id}\n";

$wallet = DB::table('wallets')
    ->where('payable_type', 'App\\Models\\User')
    ->where('payable_id', $order->user_id)
    ->first();
if ($wallet) {
    echo "  wallet.id={$wallet->id}\n";
    $txns = DB::table('wallet_transactions')->where('wallet_id', $wallet->id)->orderBy('id')->get();
    foreach ($txns as $t) {
        echo "    wallet_txn.id={$t->id} type={$t->type} amount={$t->amount} source_type={$t->source_type} source_id={$t->source_id} created_at={$t->created_at}\n";
    }
} else {
    echo "  NO wallet\n";
}

// PayFast record for this order
$pfRows = DB::table('transactions_pf')->where('custom_int5', 9160)->get();
echo "  transactions_pf for order 9160:\n";
foreach ($pfRows as $p) {
    echo "    pf.id={$p->id} pf_payment_id={$p->pf_payment_id} amount_gross={$p->amount_gross}\n";
}

echo "\n=== EVENT PARITY CHECK — pick 3 events ===\n";
// Event with PayFast-only, wallet/hybrid, and refunds
// Find events that have transactions
$pfEvents = DB::table('transactions_pf')
    ->whereNotNull('event_id')
    ->groupBy('event_id')
    ->selectRaw('event_id, COUNT(*) as cnt, SUM(amount_gross) as total_gross')
    ->orderByDesc('cnt')
    ->limit(10)
    ->get();
echo "Top events by PayFast transaction count:\n";
foreach ($pfEvents as $e) {
    echo "  event_id={$e->event_id} pf_count={$e->cnt} total_gross={$e->total_gross}\n";
}

// Find events with refunds
$refundEvents = DB::table('category_event_registrations')
    ->where('refund_status', '!=', 'not_refunded')
    ->whereNotNull('refund_status')
    ->join('category_events', 'category_event_registrations.category_event_id', '=', 'category_events.id')
    ->groupBy('category_events.event_id')
    ->selectRaw('category_events.event_id, COUNT(*) as refund_cnt, SUM(category_event_registrations.refund_gross) as total_refund')
    ->orderByDesc('refund_cnt')
    ->limit(5)
    ->get();
echo "\nEvents with refunds:\n";
foreach ($refundEvents as $e) {
    echo "  event_id={$e->event_id} refund_cnt={$e->refund_cnt} total_refund={$e->total_refund}\n";
}

// Find events with wallet payments
$walletEvents = DB::table('registration_orders')
    ->where('wallet_debited', 1)
    ->where('wallet_reserved', '>', 0)
    ->join('registration_order_items', 'registration_orders.id', '=', 'registration_order_items.order_id')
    ->join('category_events', 'registration_order_items.category_event_id', '=', 'category_events.id')
    ->groupBy('category_events.event_id')
    ->selectRaw('category_events.event_id, COUNT(*) as wallet_cnt, SUM(registration_orders.wallet_reserved) as total_wallet')
    ->orderByDesc('wallet_cnt')
    ->limit(5)
    ->get();
echo "\nEvents with wallet payments:\n";
foreach ($walletEvents as $e) {
    echo "  event_id={$e->event_id} wallet_cnt={$e->wallet_cnt} total_wallet={$e->total_wallet}\n";
}
