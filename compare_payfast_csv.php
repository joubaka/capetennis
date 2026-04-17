<?php
/**
 * Compare PayFast CSV transactions against DB entries.
 * Shows which payments are missing entries or have wrong payment status.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\RegistrationOrderItems;
use App\Models\CategoryEventRegistration;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

$csvPath = 'C:\Users\pajou\Downloads\2026-04-17-Transaction_History_11307280.csv';

if (!file_exists($csvPath)) {
    echo "CSV not found: {$csvPath}\n";
    exit(1);
}

$handle = fopen($csvPath, 'r');
$headers = fgetcsv($handle);

// Map headers
$col = array_flip($headers);

$total = 0;
$ok = 0;
$problems = [];

echo "=== PayFast CSV vs DB Comparison ===\n\n";

while (($row = fgetcsv($handle)) !== false) {
    // Skip non-credit entries (refunds, etc.)
    if (($row[$col['Sign']] ?? '') !== 'Credit') continue;
    if (($row[$col['Type']] ?? '') !== 'Funds Received') continue;

    $orderId     = (int) ($row[$col['Custom_int5']] ?? 0);
    $pfPaymentId = $row[$col['PF Payment ID']] ?? '';
    $playerName  = $row[$col['Custom_str2']] ?? '?';
    $eventName   = $row[$col['Custom_str3']] ?? '?';
    $categoryStr = $row[$col['Custom_str1']] ?? '?';
    $gross       = $row[$col['Gross']] ?? '0';
    $date        = $row[$col['Date']] ?? '?';

    if (!$orderId) continue;
    $total++;

    // Check order exists and is paid
    $order = DB::table('registration_orders')->where('id', $orderId)->first();

    if (!$order) {
        $problems[] = [
            'type' => 'ORDER NOT FOUND',
            'date' => $date,
            'event' => $eventName,
            'category' => $categoryStr,
            'player' => $playerName,
            'order_id' => $orderId,
            'pf_id' => $pfPaymentId,
            'gross' => $gross,
            'detail' => "Order #{$orderId} does not exist in DB",
        ];
        continue;
    }

    if ($order->pay_status != 1) {
        $problems[] = [
            'type' => 'ORDER NOT MARKED PAID',
            'date' => $date,
            'event' => $eventName,
            'category' => $categoryStr,
            'player' => $playerName,
            'order_id' => $orderId,
            'pf_id' => $pfPaymentId,
            'gross' => $gross,
            'detail' => "Order #{$orderId} has pay_status={$order->pay_status} (expected 1)",
        ];
    }

    // Check order items → entries
    $items = RegistrationOrderItems::where('order_id', $orderId)->get();

    if ($items->isEmpty()) {
        $problems[] = [
            'type' => 'NO ORDER ITEMS',
            'date' => $date,
            'event' => $eventName,
            'category' => $categoryStr,
            'player' => $playerName,
            'order_id' => $orderId,
            'pf_id' => $pfPaymentId,
            'gross' => $gross,
            'detail' => "Order #{$orderId} has no items in registration_order_items",
        ];
        continue;
    }

    $itemOk = true;
    foreach ($items as $item) {
        if (!$item->registration_id) {
            $problems[] = [
                'type' => 'ITEM MISSING REGISTRATION',
                'date' => $date,
                'event' => $eventName,
                'category' => $categoryStr,
                'player' => $playerName,
                'order_id' => $orderId,
                'pf_id' => $pfPaymentId,
                'gross' => $gross,
                'detail' => "Item #{$item->id} has no registration_id",
            ];
            $itemOk = false;
            continue;
        }

        // Check player attached
        $playerAttached = DB::table('player_registrations')
            ->where('registration_id', $item->registration_id)
            ->where('player_id', $item->player_id)
            ->exists();

        if (!$playerAttached) {
            $problems[] = [
                'type' => 'PLAYER NOT ATTACHED',
                'date' => $date,
                'event' => $eventName,
                'category' => $categoryStr,
                'player' => $playerName,
                'order_id' => $orderId,
                'pf_id' => $pfPaymentId,
                'gross' => $gross,
                'detail' => "Player #{$item->player_id} not in player_registrations for Registration #{$item->registration_id}",
            ];
            $itemOk = false;
        }

        // Check CategoryEventRegistration exists and is paid
        $entry = CategoryEventRegistration::where('registration_id', $item->registration_id)
            ->where('category_event_id', $item->category_event_id)
            ->first();

        if (!$entry) {
            $problems[] = [
                'type' => 'ENTRY MISSING',
                'date' => $date,
                'event' => $eventName,
                'category' => $categoryStr,
                'player' => $playerName,
                'order_id' => $orderId,
                'pf_id' => $pfPaymentId,
                'gross' => $gross,
                'detail' => "No category_event_registration for Reg#{$item->registration_id} + CatEvent#{$item->category_event_id}",
            ];
            $itemOk = false;
        } elseif ($entry->payment_status_id != 1) {
            $problems[] = [
                'type' => 'ENTRY NOT MARKED PAID',
                'date' => $date,
                'event' => $eventName,
                'category' => $categoryStr,
                'player' => $playerName,
                'order_id' => $orderId,
                'pf_id' => $pfPaymentId,
                'gross' => $gross,
                'detail' => "Entry exists but payment_status_id={$entry->payment_status_id} (expected 1)",
            ];
            $itemOk = false;
        }
    }

    if ($itemOk) $ok++;
}

fclose($handle);

// Print problems
if (count($problems) > 0) {
    echo "--- PROBLEMS FOUND ---\n\n";
    foreach ($problems as $i => $p) {
        echo ($i + 1) . ". [{$p['type']}] {$p['date']}\n";
        echo "   Event:    {$p['event']}\n";
        echo "   Category: {$p['category']}\n";
        echo "   Player:   {$p['player']}\n";
        echo "   Order:    #{$p['order_id']}  |  PF: {$p['pf_id']}  |  R{$p['gross']}\n";
        echo "   Issue:    {$p['detail']}\n\n";
    }

    // Group by event
    $byEvent = [];
    foreach ($problems as $p) {
        $byEvent[$p['event']][] = $p;
    }

    echo "--- SUMMARY BY EVENT ---\n\n";
    foreach ($byEvent as $event => $probs) {
        echo "{$event}: " . count($probs) . " issue(s)\n";
        $types = array_count_values(array_column($probs, 'type'));
        foreach ($types as $type => $count) {
            echo "  {$type}: {$count}\n";
        }
        echo "\n";
    }
} else {
    echo "All transactions match DB entries correctly.\n";
}

echo "=== TOTALS ===\n";
echo "CSV transactions checked: {$total}\n";
echo "Fully OK:                 {$ok}\n";
echo "With problems:            " . count($problems) . "\n";
