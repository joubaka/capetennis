<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Null out custom_int5 on the two dummy rows so they don't accidentally
// link to unrelated RegistrationOrders from other events
$affected = DB::table('transactions_pf')
    ->whereIn('id', [1379, 1380])
    ->update(['custom_int5' => null, 'custom_str5' => 'TeamOrder']);

echo "Updated $affected dummy rows (custom_int5 set to NULL)\n";

// Verify
$rows = DB::table('transactions_pf')->whereIn('id', [1379, 1380])->get(['id','custom_int5','custom_str5','player_id']);
foreach ($rows as $r) {
    echo "  id={$r->id} custom_int5=".($r->custom_int5 ?? 'NULL')." player_id={$r->player_id}\n";
}

$count = DB::table('transactions_pf')
    ->where('event_id', 227)
    ->where('transaction_type', 'Registration')
    ->where('is_test', false)
    ->count();
echo "Total event 227 count: $count\n";
