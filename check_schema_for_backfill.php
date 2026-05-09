<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Check registration_orders columns
echo "=== registration_orders columns ===\n";
$cols = DB::select('SHOW COLUMNS FROM registration_orders');
foreach ($cols as $c) echo "  {$c->Field} ({$c->Type})\n";

// Check category_event_registrations columns
echo "\n=== category_event_registrations columns ===\n";
$cols2 = DB::select('SHOW COLUMNS FROM category_event_registrations');
foreach ($cols2 as $c) echo "  {$c->Field} ({$c->Type})\n";

// Check transactions_pf columns
echo "\n=== transactions_pf columns ===\n";
$cols3 = DB::select('SHOW COLUMNS FROM transactions_pf');
foreach ($cols3 as $c) echo "  {$c->Field} ({$c->Type})\n";

// Sample a paid registration_order to understand the values that get set
echo "\n=== Sample PAID registration_order ===\n";
$sample = DB::table('registration_orders')->where('pay_status', 1)->orderByDesc('id')->first();
print_r($sample);

// Sample a transactions_pf row for a registration payment
echo "\n=== Sample transactions_pf Registration row ===\n";
$tx = DB::table('transactions_pf')
    ->where('transaction_type', 'Registration')
    ->where('is_test', 0)
    ->orderByDesc('id')
    ->first();
print_r($tx);

// Sample a registration_order_item to see its structure
echo "\n=== Sample registration_order_item ===\n";
$item = DB::table('registration_order_items')->orderByDesc('id')->first();
print_r($item);

// Check category_event_registrations for a known paid order
echo "\n=== category_event_registrations columns (payment_status) ===\n";
$cer = DB::table('category_event_registrations')->orderByDesc('id')->first();
print_r($cer);
