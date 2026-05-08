<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

// Event info
$event = DB::table('events')->where('id', 227)->select('id','name','entryFee','eventType')->first();
echo "=== Event 227 ===\n";
print_r($event);

// transactions_pf
$txCount = DB::table('transactions_pf')->where('event_id', 227)->where('transaction_type', 'Registration')->count();
echo "\ntransactions_pf for event 227: {$txCount}\n";

// team_payment_orders
$orders = DB::table('team_payment_orders')->where('event_id', 227)->get();
echo "team_payment_orders for event 227: ".count($orders)."\n";
echo "  - paid (pay_status=1): ".collect($orders)->where('pay_status',1)->count()."\n";
echo "  - unpaid: ".collect($orders)->where('pay_status',0)->count()."\n\n";

// Which paid orders have NO transactions_pf record
echo "=== Paid team_payment_orders with NO transactions_pf record ===\n";
echo "order_id,team_id,player_id,user_id,pf_payment_id,amount,updated_at\n";
$missing = [];
foreach ($orders as $o) {
    if (!$o->pay_status) continue;
    $exists = DB::table('transactions_pf')->where('pf_payment_id', $o->payfast_pf_payment_id)->exists();
    if (!$exists) {
        $player = DB::table('players')->where('id', $o->player_id)->first();
        $pname = $player ? trim($player->name.' '.$player->surname) : 'NOT FOUND';
        echo implode(',', [$o->id, $o->team_id, $o->player_id, $o->user_id, $o->payfast_pf_payment_id ?? 'NULL', $o->payfast_amount_due, $o->updated_at])."\n";
        $missing[] = $o;
    }
}
echo "Missing count: ".count($missing)."\n";

// team_players with pay_status=1 but no tx
echo "\n=== team_players (pay_status=1) with NO transactions_pf record ===\n";
$txPlayerIds = DB::table('transactions_pf')->where('event_id',227)->where('transaction_type','Registration')->pluck('custom_int2')->filter()->unique();
$teamIds = DB::table('team_payment_orders')->where('event_id',227)->distinct()->pluck('team_id');
$tps = DB::table('team_players')
    ->join('players','team_players.player_id','=','players.id')
    ->whereIn('team_players.team_id', $teamIds)
    ->where('team_players.pay_status', 1)
    ->select('team_players.id','team_players.team_id','team_players.player_id','players.name','players.surname','team_players.pay_status')
    ->get();
$noTx = 0;
foreach ($tps as $tp) {
    if (!$txPlayerIds->contains($tp->player_id)) {
        echo "tp_id={$tp->id}, team_id={$tp->team_id}, player_id={$tp->player_id}, name={$tp->name} {$tp->surname}\n";
        $noTx++;
    }
}
echo "No-tx count: {$noTx}\n";

echo "\n=== All paid orders for event 227 (detail) ===\n";
echo "order_id,team_id,player_id,player_name,pf_payment_id,amount,in_tx,updated_at\n";
foreach ($orders as $o) {
    if (!$o->pay_status) continue;
    $player = DB::table('players')->where('id', $o->player_id)->first();
    $pname = $player ? trim($player->name.' '.$player->surname) : 'NOT FOUND';
    $inTx = DB::table('transactions_pf')->where('pf_payment_id', $o->payfast_pf_payment_id)->exists();
    echo implode(',', [$o->id, $o->team_id, $o->player_id, $pname, $o->payfast_pf_payment_id ?? 'NULL', $o->payfast_amount_due, $inTx?'YES':'NO', $o->updated_at])."\n";
}
