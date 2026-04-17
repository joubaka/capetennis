<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$maxTeam = DB::table('team_payment_orders')->max('id');
$countTeam = DB::table('team_payment_orders')->count();
$recentTeam = DB::table('team_payment_orders')
    ->orderByDesc('id')
    ->limit(10)
    ->get(['id', 'user_id', 'pay_status', 'created_at']);

echo "team_payment_orders:\n";
echo "  Max ID: {$maxTeam}\n";
echo "  Total:  {$countTeam}\n\n";

foreach ($recentTeam as $o) {
    echo "  #{$o->id} | user:{$o->user_id} | pay_status:{$o->pay_status} | {$o->created_at}\n";
}

// Check the missing IDs range in team_payment_orders
$around = DB::table('team_payment_orders')
    ->whereBetween('id', [9200, 9270])
    ->orderBy('id')
    ->get(['id', 'user_id', 'pay_status', 'created_at']);

echo "\nteam_payment_orders in range 9200-9270:\n";
foreach ($around as $o) {
    echo "  #{$o->id} | user:{$o->user_id} | pay_status:{$o->pay_status} | {$o->created_at}\n";
}

// Also check what notify route does for team — does it check both tables?
echo "\nDone.\n";
