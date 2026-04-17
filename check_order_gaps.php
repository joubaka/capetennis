<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$maxOrder = DB::table('registration_orders')->max('id');
$countOrders = DB::table('registration_orders')->count();
$recent = DB::table('registration_orders')
    ->orderByDesc('id')
    ->limit(10)
    ->get(['id', 'user_id', 'pay_status', 'payfast_paid', 'created_at']);

echo "Max order ID: {$maxOrder}\n";
echo "Total orders: {$countOrders}\n\n";

echo "Last 10 orders:\n";
foreach ($recent as $o) {
    echo "  #{$o->id} | user:{$o->user_id} | pay_status:{$o->pay_status} | pf_paid:" . ($o->payfast_paid ? 'Y' : 'N') . " | {$o->created_at}\n";
}

// Check if the missing IDs exist as gaps
$missingIds = [9217,9218,9220,9221,9222,9224,9225,9226,9228,9229,9231,9232,9234,9235,9236,9238,9239,9241,9243,9245,9246,9248,9249,9250,9251,9252,9253,9254,9255,9256,9257,9258,9259,9260,9261];
$existing = DB::table('registration_orders')->whereIn('id', $missingIds)->pluck('id')->toArray();
$teamExisting = DB::table('team_payment_orders')->whereIn('id', $missingIds)->pluck('id')->toArray();

echo "\nOf the 35 missing IDs:\n";
echo "  Found in registration_orders: " . count($existing) . " — " . implode(',', $existing) . "\n";
echo "  Found in team_payment_orders: " . count($teamExisting) . " — " . implode(',', $teamExisting) . "\n";

// Check if there are orders AROUND the missing range
$around = DB::table('registration_orders')
    ->whereBetween('id', [9200, 9270])
    ->orderBy('id')
    ->get(['id', 'user_id', 'pay_status', 'created_at']);

echo "\nOrders in range 9200-9270:\n";
foreach ($around as $o) {
    echo "  #{$o->id} | user:{$o->user_id} | pay_status:{$o->pay_status} | {$o->created_at}\n";
}
