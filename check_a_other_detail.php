<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$playerId = 2404;

echo "=== Player 2404 ===\n";
$p = DB::table('players')->where('id', $playerId)->first();
print_r($p);

echo "\n=== CERs for player 2404 in event 222 ===\n";
$cer222ceIds = DB::table('category_events')->where('event_id', 222)->pluck('id');

$cers = DB::table('category_event_registrations as cer')
    ->join('registrations as r', 'r.id', '=', 'cer.registration_id')
    ->join('player_registrations as pr', 'pr.registration_id', '=', 'r.id')
    ->whereIn('cer.category_event_id', $cer222ceIds)
    ->where('pr.player_id', $playerId)
    ->select('cer.*')
    ->get();

foreach ($cers as $cer) {
    echo "\nCER id={$cer->id} reg_id={$cer->registration_id} ce_id={$cer->category_event_id} payment_status={$cer->payment_status_id} pf_transaction_id={$cer->pf_transaction_id} user_id={$cer->user_id} status={$cer->status}\n";

    $user = DB::table('users')->where('id', $cer->user_id)->first();
    echo "  Registered by user: {$user->name} ({$user->email})\n";

    // Check transactions_pf for this pf_transaction_id
    if ($cer->pf_transaction_id) {
        $tx = DB::table('transactions_pf')->where('pf_payment_id', $cer->pf_transaction_id)->first();
        if ($tx) {
            echo "  transactions_pf: id={$tx->id} gross={$tx->amount_gross} pf={$tx->pf_payment_id} order={$tx->custom_int5}\n";
        } else {
            echo "  transactions_pf: NONE for pf_transaction_id={$cer->pf_transaction_id}\n";
        }
    } else {
        echo "  pf_transaction_id is NULL/empty\n";
        // Check by registration_id in transactions_pf
        $tx2 = DB::table('transactions_pf')->where('custom_int5', $cer->registration_id)->first();
        if ($tx2) echo "  transactions_pf by custom_int5: id={$tx2->id}\n";
    }

    // Check category_event
    $ce = DB::table('category_events')->where('id', $cer->category_event_id)->first();
    $cat = DB::table('categories')->where('id', $ce->category_id)->first();
    echo "  Category: {$cat->name}\n";
}

// Check all registrations for player 2404 globally
echo "\n=== All registrations for player 2404 ===\n";
$allRegs = DB::table('player_registrations')->where('player_id', $playerId)->get();
foreach ($allRegs as $pr) {
    $cer = DB::table('category_event_registrations')->where('registration_id', $pr->registration_id)->first();
    $ce  = $cer ? DB::table('category_events')->where('id', $cer->category_event_id)->first() : null;
    $evt = $ce  ? DB::table('events')->where('id', $ce->event_id)->first() : null;
    echo "  reg_id={$pr->registration_id} event=" . ($evt->name ?? 'N/A') . " ce_id=" . ($cer->category_event_id ?? 'N/A') . " payment_status=" . ($cer->payment_status_id ?? 'N/A') . "\n";
}

// Check if player 2404 appears in any order items
echo "\n=== Order items for player 2404 ===\n";
$items = DB::table('registration_order_items')->where('player_id', $playerId)->get();
foreach ($items as $i) {
    $order = DB::table('registration_orders')->where('id', $i->order_id)->first();
    echo "  order={$i->order_id} ce_id={$i->category_event_id} pay_status={$order->pay_status} pf_id={$order->payfast_pf_payment_id}\n";
}
if ($items->isEmpty()) echo "  NONE\n";

// Who owns player 2404?
echo "\n=== User linked to player 2404 ===\n";
$up = DB::table('user_players')->where('player_id', $playerId)->get();
foreach ($up as $u) {
    $usr = DB::table('users')->where('id', $u->user_id)->first();
    echo "  user_id={$u->user_id} name={$usr->name} email={$usr->email}\n";
}
