<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Find a transactions_pf row for a known paid order (e.g., 9459)
echo "=== transactions_pf for order 9459 (paid, event 222) ===\n";
$tx = DB::table('transactions_pf')->where('custom_int5', 9459)->first();
print_r($tx);

// Check a few more paid orders to get the pattern
echo "\n=== transactions_pf for event 222 Registration rows (non-team) ===\n";
$rows = DB::table('transactions_pf')
    ->where('event_id', 222)
    ->where('transaction_type', 'Registration')
    ->whereNotNull('custom_int5')
    ->orderByDesc('id')
    ->limit(3)
    ->get();
foreach ($rows as $r) print_r($r);

// Also check what PayFast fee rate is used (amount_fee calculation)
echo "\n=== Check cape_tennis_fee pattern ===\n";
$rows2 = DB::table('transactions_pf')
    ->where('transaction_type', 'Registration')
    ->whereNotNull('pf_payment_id')
    ->whereNotNull('amount_fee')
    ->orderByDesc('id')
    ->limit(5)
    ->get();
foreach ($rows2 as $r) {
    echo "gross={$r->amount_gross} fee={$r->amount_fee} cape_tennis_fee={$r->cape_tennis_fee} pf_payment_id={$r->pf_payment_id}\n";
}
