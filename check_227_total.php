<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Raw count same as backfill script
$raw = DB::table('transactions_pf')
    ->where('event_id', 227)
    ->where('transaction_type', 'Registration')
    ->where('is_test', false)
    ->count();
echo "Raw count (is_test=false, Registration): $raw\n";

// Count including all rows
$all = DB::table('transactions_pf')->where('event_id', 227)->count();
echo "All rows for event 227: $all\n";

// Count with amount_gross >= 0 (as EventTransactionController does)
$positive = DB::table('transactions_pf')
    ->where('event_id', 227)
    ->where('transaction_type', 'Registration')
    ->where('amount_gross', '>=', 0)
    ->where('is_test', false)
    ->count();
echo "With amount_gross >= 0: $positive\n";

// Show the 2 dummy rows
$dummies = DB::table('transactions_pf')->whereIn('custom_int5', [2, 19])->where('event_id', 227)->get();
foreach ($dummies as $d) {
    echo "Dummy tx id={$d->id} order={$d->custom_int5} pf_id=".($d->pf_payment_id ?? 'NULL')." gross={$d->amount_gross} is_test={$d->is_test}\n";
}
