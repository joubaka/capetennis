<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$csvPath = 'C:\Users\pajou\Downloads\2026-04-17-Transaction_History_11307280.csv';
$handle = fopen($csvPath, 'r');
$headers = fgetcsv($handle);
$col = array_flip($headers);

$missing = [];

while (($row = fgetcsv($handle)) !== false) {
    if (($row[$col['Sign']] ?? '') !== 'Credit') continue;
    if (($row[$col['Type']] ?? '') !== 'Funds Received') continue;

    $orderId = (int) ($row[$col['Custom_int5']] ?? 0);
    if (!$orderId) continue;

    $inRegOrders = DB::table('registration_orders')->where('id', $orderId)->exists();
    if ($inRegOrders) continue;

    $inTeamOrders = DB::table('team_payment_orders')->where('id', $orderId)->exists();

    $missing[] = [
        'order_id' => $orderId,
        'date' => $row[$col['Date']] ?? '',
        'event' => $row[$col['Custom_str3']] ?? '',
        'category' => $row[$col['Custom_str1']] ?? '',
        'player' => $row[$col['Custom_str2']] ?? '',
        'gross' => $row[$col['Gross']] ?? '',
        'in_team_orders' => $inTeamOrders,
    ];
}
fclose($handle);

echo "=== ORDERS NOT IN registration_orders ===\n\n";
foreach ($missing as $m) {
    $where = $m['in_team_orders'] ? 'FOUND in team_payment_orders' : 'NOT FOUND ANYWHERE';
    echo "Order #{$m['order_id']} | {$m['date']} | {$m['event']} | {$m['category']} | {$m['player']} | R{$m['gross']} | {$where}\n";
}
echo "\nTotal: " . count($missing) . "\n";
$teamCount = count(array_filter($missing, fn($m) => $m['in_team_orders']));
$nowhere = count($missing) - $teamCount;
echo "In team_payment_orders: {$teamCount}\n";
echo "Not found anywhere:    {$nowhere}\n";
