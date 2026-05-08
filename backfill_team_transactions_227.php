<?php
/**
 * BACKFILL: Write missing transactions_pf records for 2 team payments
 * for event 227 that are present in PayFast but not in transactions_pf.
 *
 * Missing credits (from PayFast CSV reconciliation):
 *   1. Order 7  — pf_id 282088155 — Milan Snyman        — 2026-02-19 10:50:27
 *   2. Order 5  — pf_id 281884735 — Karlienke Nel        — 2026-02-18 08:55:35
 *                 NOTE: Order 5 original pf_id 281867899 was REVERSED — use replacement 281884735
 *
 * Excluded from backfill:
 *   - pf_id 281867899  (Funds Received Reversal — Debit — already excluded)
 *   - pf_id 3012559    (Sandbox/test payment — user 584 Super User)
 *
 * No cap for event 227.
 * Safe to run multiple times — checks for existing pf_payment_id before inserting.
 *
 * Run: php backfill_team_transactions_227.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\SiteSetting;

// The two missing live credits: [order_id, pf_payment_id_to_use, payment_timestamp]
$missing = [
    [
        'order_id'    => 7,
        'pf_id'       => '282088155',
        'paid_at'     => '2026-02-19 10:50:27',
    ],
    [
        'order_id'    => 5,
        'pf_id'       => '281884735',   // replacement payment — original 281867899 was reversed
        'paid_at'     => '2026-02-18 08:55:35',
    ],
];

$inserted = 0;
$skipped  = 0;
$errors   = 0;

foreach ($missing as $item) {
    $orderId = $item['order_id'];
    $pfId    = $item['pf_id'];
    $paidAt  = $item['paid_at'];

    // Guard: skip if already in transactions_pf
    if (DB::table('transactions_pf')->where('pf_payment_id', $pfId)->exists()) {
        echo "⏭️  pf_id={$pfId} already in transactions_pf — skipping\n";
        $skipped++;
        continue;
    }

    $order  = DB::table('team_payment_orders')->where('id', $orderId)->first();
    if (!$order) {
        echo "❌ Order {$orderId} not found — skipping\n";
        $errors++;
        continue;
    }

    $event  = DB::table('events')->where('id', $order->event_id)->first();
    $player = DB::table('players')->where('id', $order->player_id)->first();
    $user   = DB::table('users')->where('id', $order->user_id)->first();

    $eventName  = $event  ? $event->name  : 'Team Event';
    $playerName = $player ? trim(($player->name ?? '') . ' ' . ($player->surname ?? '')) : '';
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
        $tx->pf_payment_id     = $pfId;
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
        $tx->created_at        = $paidAt;
        $tx->updated_at        = $paidAt;
        $tx->save();

        echo "✅ Inserted tx id={$tx->id} for order {$orderId} | player={$playerName} | pf_id={$pfId} | gross=R{$gross} | paid_at={$paidAt}\n";
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
echo "(PayFast CSV has 94 live credits: 95 credits minus 1 sandbox/test)\n";
