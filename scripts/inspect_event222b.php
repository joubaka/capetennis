<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Event;
use App\Models\SiteSetting;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

$event = Event::find(222);
echo "cape_tennis_fee (event attr): R" . $event->cape_tennis_fee . "\n";
echo "SiteSetting fee_per_entry:    R" . SiteSetting::get('cape_tennis_fee_per_entry', 10) . "\n\n";

$txs = Transaction::where('event_id', 222)->get();
$pfTxs    = $txs->whereNotNull('pf_payment_id');
$adminTxs = $txs->whereNull('pf_payment_id');

echo "Total transactions:               " . $txs->count() . "\n";
echo "PayFast transactions:             " . $pfTxs->count() . "\n";
echo "Admin/null transactions:          " . $adminTxs->count() . "\n\n";

echo "Sum amount_gross (ALL):           R" . number_format($txs->sum('amount_gross'), 2) . "\n";
echo "Sum amount_gross (PayFast only):  R" . number_format($pfTxs->sum('amount_gross'), 2) . "\n\n";

// Count entries (items) per method
$pfEntries = 0;
$adminEntries = 0;
foreach ($pfTxs as $tx) {
    $tx->load('order.items');
    $cnt = max(1, optional($tx->order)->items?->count() ?? 1);
    $pfEntries += $cnt;
}
foreach ($adminTxs as $tx) {
    $tx->load('order.items');
    $cnt = max(1, optional($tx->order)->items?->count() ?? 1);
    $adminEntries += $cnt;
}
echo "PayFast entry count (items):      $pfEntries\n";
echo "Admin entry count (items):        $adminEntries\n";
echo "Total entries:                    " . ($pfEntries + $adminEntries) . "\n\n";

// What the dashboard label shows
$fee = (float) $event->cape_tennis_fee;
$totalEntries = $pfEntries + $adminEntries;
echo "Dashboard label would show:       '{$totalEntries} entries'\n";
echo "Cape fee (R{$fee} x {$totalEntries}): R" . number_format($fee * $totalEntries, 2) . "\n";
echo "Cape fee (R{$fee} x {$pfEntries} payfast only): R" . number_format($fee * $pfEntries, 2) . "\n\n";

// What screen shows
echo "Screen shows: Gross=R31,065  Cape=R1,920  (ledger gross=R30,808.50  cape=R1,280)\n";
echo "R1,920 / R{$fee} = " . round(1920 / $fee, 2) . " entries\n";
echo "R1,280 / R{$fee} = " . round(1280 / $fee, 2) . " entries\n";
echo "R31,065 - R30,808.50 = R256.50 (wallet hybrid)\n";
