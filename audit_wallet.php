<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Check wallet-only orders linked to event 222
$walletOrders = DB::table('registration_orders')
    ->where('wallet_reserved', '>', 0)
    ->where(function($q) {
        $q->whereNull('payfast_amount_due')->orWhere('payfast_amount_due', 0);
    })
    ->get();

echo "Wallet-only orders (all events): " . $walletOrders->count() . "\n\n";

foreach ($walletOrders as $o) {
    $items = DB::table('registration_order_items')->where('order_id', $o->id)->get();
    $catEventIds = $items->pluck('category_event_id');
    $eventIds = DB::table('category_events')->whereIn('id', $catEventIds)->pluck('event_id')->unique();

    if ($eventIds->contains(222)) {
        echo "Order {$o->id} | wallet=R{$o->wallet_reserved} | payfast=R{$o->payfast_amount_due} | items={$items->count()}\n";
        foreach ($items as $i) {
            $cer = DB::table('category_event_registrations')
                ->where('registration_id', $i->registration_id)
                ->where('category_event_id', $i->category_event_id)
                ->first();
            echo "  CER " . ($cer->id ?? 'N/A')
                . " | status=" . ($cer->status ?? '?')
                . " | payment_method=" . ($cer->payment_method ?? 'NULL')
                . " | refund_status=" . ($cer->refund_status ?? 'NULL')
                . " | pf_tx=" . ($cer->pf_transaction_id ?? 'NULL')
                . " | wallet_tx=" . ($cer->wallet_transaction_id ?? 'NULL')
                . "\n";
        }
    }
}

// Also check hybrid orders (wallet + payfast)
echo "\n--- HYBRID ORDERS (wallet_reserved > 0 AND payfast_amount_due > 0) ---\n";
$hybridOrders = DB::table('registration_orders')
    ->where('wallet_reserved', '>', 0)
    ->where('payfast_amount_due', '>', 0)
    ->get();

foreach ($hybridOrders as $o) {
    $items = DB::table('registration_order_items')->where('order_id', $o->id)->get();
    $catEventIds = $items->pluck('category_event_id');
    $eventIds = DB::table('category_events')->whereIn('id', $catEventIds)->pluck('event_id')->unique();

    if ($eventIds->contains(222)) {
        echo "Order {$o->id} | wallet=R{$o->wallet_reserved} | payfast=R{$o->payfast_amount_due} | items={$items->count()}\n";
        foreach ($items as $i) {
            $cer = DB::table('category_event_registrations')
                ->where('registration_id', $i->registration_id)
                ->where('category_event_id', $i->category_event_id)
                ->first();
            echo "  CER " . ($cer->id ?? 'N/A')
                . " | status=" . ($cer->status ?? '?')
                . " | payment_method=" . ($cer->payment_method ?? 'NULL')
                . " | refund_status=" . ($cer->refund_status ?? 'NULL')
                . " | pf_tx=" . ($cer->pf_transaction_id ?? 'NULL')
                . "\n";
        }
    }
}

// Check WalletTransactions for event 222
echo "\n--- WALLET TRANSACTIONS for event 222 ---\n";
$walletOnlyOrderIds = DB::table('registration_orders')
    ->whereHas !== null ? DB::table('registration_orders')
    ->where('wallet_reserved', '>', 0)
    ->where(function($q) { $q->whereNull('payfast_amount_due')->orWhere('payfast_amount_due', 0); })
    ->pluck('id') : collect();

$wts = DB::table('wallet_transactions')
    ->whereIn('source_id', $walletOnlyOrderIds)
    ->where('source_type', 'event_registration_wallet_payment')
    ->where('type', 'debit')
    ->get();

echo "Wallet debit transactions: " . $wts->count() . "\n";
foreach ($wts as $wt) {
    $order = DB::table('registration_orders')->find($wt->source_id);
    $items = DB::table('registration_order_items')->where('order_id', $wt->source_id)->get();
    $catEventIds = $items->pluck('category_event_id');
    $eventIds = DB::table('category_events')->whereIn('id', $catEventIds)->pluck('event_id')->unique();
    if ($eventIds->contains(222)) {
        echo "  WalletTx {$wt->id} | amount=R{$wt->amount} | order={$wt->source_id}\n";
        foreach ($items as $i) {
            $cer = DB::table('category_event_registrations')
                ->where('registration_id', $i->registration_id)
                ->where('category_event_id', $i->category_event_id)
                ->first();
            echo "    CER " . ($cer->id ?? 'N/A') . " | status=" . ($cer->status ?? '?') . " | payment_method=" . ($cer->payment_method ?? 'NULL') . "\n";
        }
    }
}
