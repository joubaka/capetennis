<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

// Check the 4 team_players with pay_status=1 but no tx — do they have any tx by player_id in transactions_pf?
$checkPlayers = [2255, 1944, 1959, 4590];
echo "=== transactions_pf records for these 4 players (any event) ===\n";
foreach ($checkPlayers as $pid) {
    $txs = DB::table('transactions_pf')->where('custom_int2', $pid)->orWhere('player_id', $pid)->get();
    $player = DB::table('players')->where('id', $pid)->first();
    $pname = $player ? trim($player->name.' '.$player->surname) : 'NOT FOUND';
    echo "\nPlayer {$pid} ({$pname}) — tx count: ".count($txs)."\n";
    foreach ($txs as $tx) {
        echo "  tx_id={$tx->id}, event_id={$tx->event_id}, pf_id={$tx->pf_payment_id}, type={$tx->transaction_type}, gross={$tx->amount_gross}, created={$tx->created_at}\n";
    }
}

// Check team_payment_orders for these players in event 227
echo "\n=== team_payment_orders for these players in event 227 ===\n";
foreach ($checkPlayers as $pid) {
    $o = DB::table('team_payment_orders')->where('player_id', $pid)->where('event_id', 227)->first();
    echo "player_id={$pid}: ".($o ? "order_id={$o->id}, pay_status={$o->pay_status}, pf_id={$o->payfast_pf_payment_id}" : "NO ORDER")."\n";
}

// Is order 3 a sandbox/test payment?
echo "\n=== Order 3 sandbox check ===\n";
$o3 = DB::table('team_payment_orders')->where('id', 3)->first();
echo "pf_payment_id={$o3->payfast_pf_payment_id}, user_id={$o3->user_id}\n";
echo "Is user 584 the test/super user: ".DB::table('users')->where('id',584)->value('name')."\n";
// PayFast sandbox IDs are typically short (< 8 digits)
echo "pf_id digit count: ".strlen($o3->payfast_pf_payment_id)."\n";
