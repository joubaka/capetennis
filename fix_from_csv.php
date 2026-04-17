<?php
/**
 * Fix missing entries from PayFast CSV — applies changes then verifies.
 * Run: php fix_from_csv.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\RegistrationOrderItems;
use App\Models\CategoryEventRegistration;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

$csvPath = __DIR__ . '/payfast_export.csv';
$handle = fopen($csvPath, 'r');
$headers = fgetcsv($handle);
$col = array_flip($headers);

$fixed = [];
$skipped = [];

echo "=== APPLYING FIXES FROM CSV ===\n\n";

while (($row = fgetcsv($handle)) !== false) {
    if (($row[$col['Sign']] ?? '') !== 'Credit') continue;
    if (($row[$col['Type']] ?? '') !== 'Funds Received') continue;

    $orderId     = (int) ($row[$col['Custom_int5']] ?? 0);
    $pfPaymentId = $row[$col['PF Payment ID']] ?? '';
    $playerCsv   = $row[$col['Custom_str2']] ?? '?';
    $eventCsv    = $row[$col['Custom_str3']] ?? '?';
    $categoryCsv = $row[$col['Custom_str1']] ?? '?';
    $date        = $row[$col['Date']] ?? '?';
    $userId      = (int) ($row[$col['Custom_int4']] ?? 0);

    if (!$orderId) continue;

    $order = DB::table('registration_orders')->where('id', $orderId)->first();
    if (!$order) continue; // Can't fix orders that don't exist

    $items = RegistrationOrderItems::where('order_id', $orderId)->get();
    if ($items->isEmpty()) continue;

    foreach ($items as $item) {
        if (!$item->registration_id || !$item->player_id || !$item->category_event_id) continue;

        $reg = Registration::find($item->registration_id);
        if (!$reg) continue;

        $label = "{$playerCsv} -> {$categoryCsv} (Order #{$orderId})";

        // Fix 1: Mark order as paid if not already
        if ($order->pay_status != 1) {
            DB::table('registration_orders')->where('id', $orderId)->update([
                'pay_status' => 1,
                'payfast_paid' => true,
                'payfast_pf_payment_id' => $pfPaymentId,
            ]);
            $fixed[] = "[ORDER MARKED PAID] #{$orderId} — {$eventCsv}";
        }

        // Fix 2: Attach player if missing
        $playerAttached = DB::table('player_registrations')
            ->where('registration_id', $reg->id)
            ->where('player_id', $item->player_id)
            ->exists();

        if (!$playerAttached) {
            $reg->players()->syncWithoutDetaching([$item->player_id]);
            $fixed[] = "[PLAYER ATTACHED] {$label}";
        }

        // Fix 3: Create entry if missing
        $entry = CategoryEventRegistration::where('registration_id', $reg->id)
            ->where('category_event_id', $item->category_event_id)
            ->first();

        if (!$entry) {
            $reg->categoryEvents()->syncWithoutDetaching([
                $item->category_event_id => [
                    'payment_status_id' => 1,
                    'user_id' => $item->user_id ?: $userId,
                    'pf_transaction_id' => $pfPaymentId,
                ],
            ]);
            $fixed[] = "[ENTRY CREATED] {$label} — {$eventCsv}";
        }

        // Fix 4: Mark entry paid if exists but wrong status
        if ($entry && $entry->payment_status_id != 1) {
            $entry->payment_status_id = 1;
            $entry->pf_transaction_id = $entry->pf_transaction_id ?: $pfPaymentId;
            $entry->save();
            $fixed[] = "[ENTRY MARKED PAID] {$label} — {$eventCsv}";
        }
    }
}
fclose($handle);

// Print what was fixed
if (count($fixed) > 0) {
    echo "FIXES APPLIED:\n\n";
    foreach ($fixed as $i => $f) {
        echo "  " . ($i + 1) . ". {$f}\n";
    }
} else {
    echo "Nothing to fix — all CSV transactions already have correct entries.\n";
}

echo "\n=== NOW VERIFYING... ===\n\n";

// Re-run the check
$handle = fopen($csvPath, 'r');
fgetcsv($handle); // skip headers
$remaining = 0;
$checked = 0;

while (($row = fgetcsv($handle)) !== false) {
    if (($row[$col['Sign']] ?? '') !== 'Credit') continue;
    if (($row[$col['Type']] ?? '') !== 'Funds Received') continue;

    $orderId = (int) ($row[$col['Custom_int5']] ?? 0);
    if (!$orderId) continue;
    $checked++;

    $order = DB::table('registration_orders')->where('id', $orderId)->first();
    if (!$order) continue; // Can't verify orders that don't exist

    $items = RegistrationOrderItems::where('order_id', $orderId)->get();
    foreach ($items as $item) {
        if (!$item->registration_id || !$item->player_id || !$item->category_event_id) continue;

        $playerOk = DB::table('player_registrations')
            ->where('registration_id', $item->registration_id)
            ->where('player_id', $item->player_id)
            ->exists();

        $entry = CategoryEventRegistration::where('registration_id', $item->registration_id)
            ->where('category_event_id', $item->category_event_id)
            ->first();

        if (!$playerOk) {
            echo "  STILL BROKEN: Player #{$item->player_id} not attached to Reg #{$item->registration_id} (Order #{$orderId})\n";
            $remaining++;
        }
        if (!$entry) {
            echo "  STILL BROKEN: No entry for Reg #{$item->registration_id} + CatEvent #{$item->category_event_id} (Order #{$orderId})\n";
            $remaining++;
        } elseif ($entry->payment_status_id != 1) {
            echo "  STILL BROKEN: Entry not paid for Reg #{$item->registration_id} + CatEvent #{$item->category_event_id} (Order #{$orderId})\n";
            $remaining++;
        }
    }
}
fclose($handle);

echo "\n=== FINAL RESULT ===\n";
echo "Fixes applied:      " . count($fixed) . "\n";
echo "Verified:           {$checked} transactions\n";
echo "Remaining problems: {$remaining}\n";

if ($remaining === 0) {
    echo "\n✅ All fixable CSV transactions now have correct entries.\n";
} else {
    echo "\n⚠️ Some issues remain — see above.\n";
}
