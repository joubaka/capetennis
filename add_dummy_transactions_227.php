<?php
/**
 * Add 2 dummy transactions_pf records for event 227 to bring total to 96.
 * Based on unpaid team_payment_orders 2 (Zoe Steenkamp) and 19 (Adriaan Engelbrecht).
 * These have no real PayFast pf_id — dummy placeholder IDs are used.
 *
 * Safe to run multiple times — checks for existing custom_int5 (order_id) before inserting.
 *
 * Run: php add_dummy_transactions_227.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\SiteSetting;

$dummyOrders = [2, 19];

$inserted = 0;
$skipped  = 0;
$errors   = 0;

foreach ($dummyOrders as $orderId) {
    // Guard: skip if a dummy tx for this order's player already exists (custom_int5 is null, match on player_id instead)
    $order = DB::table('team_payment_orders')->where('id', $orderId)->first();
    if (!$order) {
        echo "❌ Order {$orderId} not found — skipping\n";
        $errors++;
        continue;
    }
    if (DB::table('transactions_pf')->where('event_id', 227)->where('player_id', $order->player_id)->whereNull('pf_payment_id')->exists()) {
        echo "⏭️  Dummy for player_id={$order->player_id} already exists — skipping\n";
        $skipped++;
        continue;
    }

    $order = $order; // already fetched above
    $event  = DB::table('events')->where('id', $order->event_id)->first();
    $player = DB::table('players')->where('id', $order->player_id)->first();
    $user   = DB::table('users')->where('id', $order->user_id)->first();

    $eventName  = $event  ? $event->name  : 'Team Event';
    $playerName = $player ? trim(($player->name ?? '') . ' ' . ($player->surname ?? '')) : '';
    $userName   = $user   ? $user->name   : '';

    $gross = 450.00;
    $fee   = SiteSetting::calculatePayfastFee($gross);
    $net   = round($gross - $fee, 2);

    try {
        $tx = new Transaction();
        $tx->transaction_type  = 'Registration';
        $tx->amount_gross      = $gross;
        $tx->amount_fee        = $fee;
        $tx->amount_net        = $net;
        $tx->event_id          = $order->event_id;
        $tx->item_name         = $eventName;
        $tx->pf_payment_id     = null;
        $tx->player_id         = $order->player_id;
        $tx->custom_int2       = $order->player_id;
        $tx->custom_str2       = $playerName;
        $tx->custom_int3       = $order->event_id;
        $tx->custom_str3       = $eventName;
        $tx->custom_int4       = $order->user_id;
        $tx->custom_str4       = $userName;
        $tx->custom_int5       = null;
        $tx->custom_str5       = 'TeamOrder';
        $tx->team_id           = $order->team_id;
        $tx->is_test           = false;
        $tx->created_at        = now();
        $tx->updated_at        = now();
        $tx->save();

        echo "✅ Inserted dummy tx id={$tx->id} for order {$orderId} | player={$playerName} | gross=R{$gross}\n";
        $inserted++;
    } catch (\Throwable $e) {
        echo "❌ Failed for order {$orderId}: {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\n=== DONE ===\n";
echo "Inserted : {$inserted}\n";
echo "Skipped  : {$skipped}\n";
echo "Errors   : {$errors}\n";

echo "\n=== VERIFY: transactions_pf count for event 227 ===\n";
$count = DB::table('transactions_pf')
    ->where('event_id', 227)
    ->where('transaction_type', 'Registration')
    ->where('is_test', false)
    ->count();
echo "Total now: {$count}\n";
