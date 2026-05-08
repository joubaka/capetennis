<?php
/**
 * BACKFILL event 227 to exactly 96 transactions_pf rows.
 *
 * Inserts up to 4 missing rows (whichever are not already present):
 *   1. Order 7  — pf_id 282088155  — Milan Snyman        (real PayFast credit 2026-02-19)
 *   2. Order 5  — pf_id 281884735  — Karlienke Nel        (replacement for reversed 281867899, 2026-02-18)
 *   3. Order 2  — no pf_id         — Zoe Steenkamp        (dummy admin entry)
 *   4. Order 19 — no pf_id         — Adriaan Engelbrecht  (dummy admin entry)
 *
 * Skips any row already present. Safe to run multiple times.
 * Run: php backfill_227_to_96.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\SiteSetting;

const TARGET = 96;

$rows = [
    [
        'order_id' => 7,
        'pf_id'    => '282088155',
        'paid_at'  => '2026-02-19 10:50:27',
        'label'    => 'real credit',
    ],
    [
        'order_id' => 5,
        'pf_id'    => '281884735',   // replacement — original 281867899 was reversed
        'paid_at'  => '2026-02-18 08:55:35',
        'label'    => 'real credit (replacement)',
    ],
    [
        'order_id' => 2,
        'pf_id'    => null,
        'paid_at'  => null,          // will use now()
        'label'    => 'dummy',
    ],
    [
        'order_id' => 19,
        'pf_id'    => null,
        'paid_at'  => null,
        'label'    => 'dummy',
    ],
];

$currentCount = DB::table('transactions_pf')
    ->where('event_id', 227)
    ->where('transaction_type', 'Registration')
    ->where('is_test', false)
    ->count();

echo "Current count for event 227: {$currentCount}\n";
echo "Target: " . TARGET . "\n\n";

$inserted = 0;
$skipped  = 0;
$errors   = 0;

foreach ($rows as $item) {
    $orderId = $item['order_id'];
    $pfId    = $item['pf_id'];
    $paidAt  = $item['paid_at'] ?? now();
    $label   = $item['label'];

    // Duplicate guard
    if ($pfId !== null) {
        // Real payment: check by pf_payment_id
        if (DB::table('transactions_pf')->where('pf_payment_id', $pfId)->exists()) {
            echo "⏭️  pf_id={$pfId} already in transactions_pf — skipping ({$label})\n";
            $skipped++;
            continue;
        }
    } else {
        // Dummy: check by event + order player (no pf_id, no custom_int5)
        $order = DB::table('team_payment_orders')->where('id', $orderId)->first();
        if (!$order) {
            echo "❌ TeamPaymentOrder {$orderId} not found — skipping\n";
            $errors++;
            continue;
        }
        if (DB::table('transactions_pf')
            ->where('event_id', 227)
            ->where('player_id', $order->player_id)
            ->whereNull('pf_payment_id')
            ->exists()
        ) {
            echo "⏭️  Dummy for player_id={$order->player_id} (order {$orderId}) already exists — skipping\n";
            $skipped++;
            continue;
        }
    }

    $order  = $order ?? DB::table('team_payment_orders')->where('id', $orderId)->first();
    if (!$order) {
        echo "❌ TeamPaymentOrder {$orderId} not found — skipping\n";
        $errors++;
        continue;
    }

    $event      = DB::table('events')->where('id', $order->event_id)->first();
    $player     = DB::table('players')->where('id', $order->player_id)->first();
    $user       = DB::table('users')->where('id', $order->user_id)->first();
    $eventName  = $event  ? $event->name  : 'Team Event';
    $playerName = $player ? trim(($player->name ?? '') . ' ' . ($player->surname ?? '')) : '';
    $userName   = $user   ? $user->name   : '';

    $gross = 450.00;
    $fee   = $pfId !== null ? SiteSetting::calculatePayfastFee($gross) : 0;
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
        $tx->custom_int5       = null;
        $tx->custom_str5       = 'TeamOrder';
        $tx->team_id           = $order->team_id;
        $tx->is_test           = false;
        $tx->created_at        = $paidAt;
        $tx->updated_at        = $paidAt;
        $tx->save();

        echo "✅ Inserted tx id={$tx->id} | order={$orderId} | player={$playerName} | pf_id=".($pfId ?? 'NULL')." | {$label}\n";
        $inserted++;
    } catch (\Throwable $e) {
        echo "❌ Failed for order {$orderId}: {$e->getMessage()}\n";
        $errors++;
    }

    unset($order);
}

echo "\n=== DONE ===\n";
echo "Inserted : {$inserted}\n";
echo "Skipped  : {$skipped}\n";
echo "Errors   : {$errors}\n";

echo "\n=== VERIFY ===\n";
$count = DB::table('transactions_pf')
    ->where('event_id', 227)
    ->where('transaction_type', 'Registration')
    ->where('is_test', false)
    ->count();
echo "Total now: {$count} (target: " . TARGET . ")\n";
if ($count === TARGET) {
    echo "✅ Target reached.\n";
} else {
    echo "⚠️  Expected " . TARGET . ", got {$count} — investigate.\n";
}
