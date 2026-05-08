<?php
/**
 * BACKFILL: Write missing transactions_pf records for the 7 team payments
 * that went through notify_team (which never wrote to transactions_pf).
 *
 * Safe to run multiple times — checks for existing pf_payment_id before inserting.
 * Capped at 48 total transactions for event 225 (inserts only what is needed to reach 48).
 *
 * Run: php backfill_team_transactions_225.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\SiteSetting;

// Hard cap: never let event 225 exceed this many Registration transactions
const CAP = 48;

// The 7 paid team_payment_orders for event 225 that have no transactions_pf record,
// ordered oldest-first (by payfast_pf_payment_id asc = chronological)
$orderIds = [1, 4, 10, 11, 12, 13, 16];

// Check how many already exist
$currentCount = DB::table('transactions_pf')
    ->where('event_id', 225)
    ->where('transaction_type', 'Registration')
    ->count();

$slotsLeft = CAP - $currentCount;

echo "Current transactions_pf for event 225: {$currentCount}\n";
echo "Cap: ".CAP."\n";
echo "Slots available: {$slotsLeft}\n\n";

if ($slotsLeft <= 0) {
    echo "⛔ Already at or above cap of ".CAP." — nothing inserted.\n";
    exit(0);
}

$inserted = 0;
$skipped  = 0;
$errors   = 0;

foreach ($orderIds as $orderId) {

    // Stop as soon as we would exceed the cap
    if ($inserted >= $slotsLeft) {
        echo "⛔ Cap of ".CAP." reached — stopping (remaining orders skipped).\n";
        break;
    }

    $order = DB::table('team_payment_orders')->where('id', $orderId)->first();

    if (!$order) {
        echo "❌ Order {$orderId} not found — skipping\n";
        $errors++;
        continue;
    }

    if (!$order->payfast_pf_payment_id) {
        echo "⚠️  Order {$orderId} has no pf_payment_id — skipping\n";
        $skipped++;
        continue;
    }

    // Check if already exists
    $exists = DB::table('transactions_pf')
        ->where('pf_payment_id', $order->payfast_pf_payment_id)
        ->exists();

    if ($exists) {
        echo "⏭️  Order {$orderId} (pf_id={$order->payfast_pf_payment_id}) already in transactions_pf — skipping\n";
        $skipped++;
        continue;
    }

    $event  = DB::table('events')->where('id', $order->event_id)->first();
    $player = DB::table('players')->where('id', $order->player_id)->first();
    $user   = DB::table('users')->where('id', $order->user_id)->first();

    $eventName  = $event  ? $event->name  : 'Team Event';
    $playerName = $player ? trim($player->name . ' ' . $player->surname) : '';
    $userName   = $user   ? $user->name   : '';

    $gross = (float) $order->payfast_amount_due;
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
        $tx->pf_payment_id     = $order->payfast_pf_payment_id;
        $tx->player_id         = $order->player_id;
        $tx->custom_int2       = $order->player_id;
        $tx->custom_str2       = $playerName;
        $tx->custom_int3       = $order->event_id;
        $tx->custom_str3       = $eventName;
        $tx->custom_int4       = $order->user_id;
        $tx->custom_str4       = $userName;
        $tx->custom_int5       = $order->id;
        $tx->custom_str5       = 'TeamOrder';
        $tx->team_id           = $order->team_id;
        $tx->is_test           = false;
        $tx->created_at        = $order->updated_at; // use the time PayFast confirmed
        $tx->updated_at        = $order->updated_at;
        $tx->save();

        echo "✅ Inserted tx id={$tx->id} for order {$orderId} | player={$playerName} | pf_id={$order->payfast_pf_payment_id} | gross=R{$gross}\n";
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

echo "\n=== VERIFY: transactions_pf count for event 225 ===\n";
$count = DB::table('transactions_pf')->where('event_id', 225)->where('transaction_type', 'Registration')->count();
echo "Total now: {$count} (cap is ".CAP.")\n";
if ($count > CAP) {
    echo "⚠️  WARNING: count exceeds cap — investigate before running again.\n";
} else {
    echo "✅ Within cap.\n";
}
