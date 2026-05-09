<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$orderId = 9498;
$pfId    = '299910931';

echo "=== Order {$orderId} state ===\n";
$order = DB::table('registration_orders')->where('id', $orderId)->first();
echo "  pay_status={$order->pay_status} payfast_paid={$order->payfast_paid} pf_id={$order->payfast_pf_payment_id} amount_due={$order->payfast_amount_due}\n";

echo "\n=== Items & CERs ===\n";
$items = DB::table('registration_order_items')->where('order_id', $orderId)->get();
foreach ($items as $item) {
    $player   = DB::table('players')->where('id', $item->player_id)->first();
    $ce       = DB::table('category_events')->where('id', $item->category_event_id)->first();
    $category = $ce ? DB::table('categories')->where('id', $ce->category_id)->first() : null;
    echo "  Player: {$player->name} {$player->surname} (id={$player->id}) | {$category->name} (ce_id={$item->category_event_id})\n";

    $cer = DB::table('category_event_registrations')
        ->where('registration_id', $item->registration_id)
        ->where('category_event_id', $item->category_event_id)
        ->first();
    if ($cer) {
        echo "    CER: payment_status_id={$cer->payment_status_id} pf_transaction_id={$cer->pf_transaction_id} status={$cer->status}\n";
    } else {
        echo "    CER: NOT FOUND\n";
    }
}

echo "\n=== transactions_pf for PF {$pfId} ===\n";
$tx = DB::table('transactions_pf')->where('pf_payment_id', $pfId)->first();
if ($tx) {
    echo "  FOUND: id={$tx->id} gross={$tx->amount_gross} event={$tx->event_id} order={$tx->custom_int5}\n";
} else {
    echo "  NOT FOUND in transactions_pf\n";
}

echo "\n=== transactions_pf for order {$orderId} ===\n";
$tx2 = DB::table('transactions_pf')->where('custom_int5', $orderId)->first();
if ($tx2) {
    echo "  FOUND: id={$tx2->id} pf_id={$tx2->pf_payment_id} gross={$tx2->amount_gross}\n";
} else {
    echo "  NOT FOUND by order id\n";
}

// Check if players appear in event 222 CERs at all
echo "\n=== CERs in event 222 for player 4315 (Joa) and 5016 (Carli) ===\n";
$ce222 = DB::table('category_events')->where('event_id', 222)->pluck('id');
foreach ([4315, 5016] as $pid) {
    $player = DB::table('players')->where('id', $pid)->first();
    $regs = DB::table('player_registrations')->where('player_id', $pid)->pluck('registration_id');
    $cers = DB::table('category_event_registrations')
        ->whereIn('registration_id', $regs)
        ->whereIn('category_event_id', $ce222)
        ->get();
    echo "  {$player->name} {$player->surname} (id={$pid}): " . $cers->count() . " CER(s)\n";
    foreach ($cers as $cer) {
        $cat = DB::table('category_events')->where('id', $cer->category_event_id)->first();
        $catName = $cat ? DB::table('categories')->where('id', $cat->category_id)->value('name') : 'N/A';
        echo "    ce_id={$cer->category_event_id} ({$catName}) payment_status={$cer->payment_status_id} pf_tx={$cer->pf_transaction_id}\n";
    }
}
