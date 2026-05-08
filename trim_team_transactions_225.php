<?php
/**
 * TRIM: Remove the 4 excess transactions_pf records for event 225 that exceed the cap of 48.
 * Deletes only the 4 backfilled records for orders 11, 12, 13, 16 (tx ids 1373-1376).
 * Safe to run multiple times — checks existence before deleting.
 *
 * Run: php trim_team_transactions_225.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

const CAP = 48;

// The 4 pf_payment_ids that should NOT be in transactions_pf on the capped version
// These are the newest 4 (orders 11,12,13,16) that push the count over 48
$removePayfastIds = [
    282344547, // order 11 - Anine Swart
    282359675, // order 12 - Henry Murray
    282634395, // order 13 - Lara Bishop
    282881777, // order 16 - Karlien Du Toit
];

$before = DB::table('transactions_pf')
    ->where('event_id', 225)
    ->where('transaction_type', 'Registration')
    ->count();

echo "Current count for event 225: {$before}\n";
echo "Cap: ".CAP."\n\n";

$deleted = 0;

foreach ($removePayfastIds as $pfId) {
    $row = DB::table('transactions_pf')->where('pf_payment_id', $pfId)->first();

    if (!$row) {
        echo "⏭️  pf_id={$pfId} not found in transactions_pf — nothing to delete\n";
        continue;
    }

    DB::table('transactions_pf')->where('id', $row->id)->delete();
    echo "🗑️  Deleted tx id={$row->id} | pf_id={$pfId} | player={$row->custom_str2}\n";
    $deleted++;
}

$after = DB::table('transactions_pf')
    ->where('event_id', 225)
    ->where('transaction_type', 'Registration')
    ->count();

echo "\n=== DONE ===\n";
echo "Deleted : {$deleted}\n";
echo "Count before : {$before}\n";
echo "Count after  : {$after}\n";

if ($after === CAP) {
    echo "✅ Exactly at cap of ".CAP."\n";
} elseif ($after < CAP) {
    echo "⚠️  Below cap (".CAP.") — count={$after}\n";
} else {
    echo "❌ Still above cap (".CAP.") — count={$after}\n";
}
