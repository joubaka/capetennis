<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Check the actual amounts on these orders vs what PayFast charged
$cases = [
    ['order'=>9497,'pf_gross'=>285.00],
    ['order'=>9494,'pf_gross'=>285.00],
    ['order'=>9483,'pf_gross'=>570.00],
    ['order'=>9479,'pf_gross'=>570.00],
    ['order'=>9472,'pf_gross'=>570.00],
    ['order'=>9492,'pf_gross'=>285.00],
    ['order'=>9486,'pf_gross'=>285.00],
];

echo "order_id | payfast_amount_due (DB) | pf_gross (CSV) | items_sum | mismatch?\n";
foreach ($cases as $c) {
    $order = DB::table('registration_orders')->where('id', $c['order'])->first();
    $items = DB::table('registration_order_items')->where('order_id', $c['order'])->sum('item_price');
    $expected = (float) $order->payfast_amount_due;
    if ($expected <= 0) $expected = (float) $items - (float) $order->wallet_reserved;
    $mismatch = round($c['pf_gross'], 2) !== round($expected, 2) ? '❌ MISMATCH' : '✅ OK';
    echo "{$c['order']} | payfast_amount_due={$order->payfast_amount_due} | pf_gross={$c['pf_gross']} | items_sum={$items} | {$mismatch}\n";
}

// Also check the laravel log for HYBRID ITN errors
echo "\n=== Checking Laravel log for HYBRID ITN errors ===\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $relevant = array_filter($lines, fn($l) => str_contains($l, 'HYBRID ITN') || str_contains($l, 'Amount mismatch'));
    $relevant = array_slice(array_values($relevant), -30);
    foreach ($relevant as $line) {
        echo $line;
    }
} else {
    echo "Log file not found at {$logFile}\n";
}
