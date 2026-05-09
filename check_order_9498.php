<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$orderId = 9498;
$pfId    = '299910931';

echo "=== Order {$orderId} ===\n";
$order = DB::table('registration_orders')->where('id', $orderId)->first();
print_r($order);

echo "\n=== Order items ===\n";
$items = DB::table('registration_order_items')->where('order_id', $orderId)->get();
foreach ($items as $item) {
    $player = DB::table('players')->where('id', $item->player_id)->first();
    $ce     = DB::table('category_events')->where('id', $item->category_event_id)->first();
    $cat    = $ce ? DB::table('categories')->where('id', $ce->category_id)->first() : null;
    echo "  player={$player->name} {$player->surname} (id={$player->id}) category={$cat->name} price={$item->item_price} reg_id={$item->registration_id}\n";
}

echo "\n=== transactions_pf for PF {$pfId} ===\n";
$tx = DB::table('transactions_pf')->where('pf_payment_id', $pfId)->first();
print_r($tx);

echo "\n=== CERs for these registrations ===\n";
foreach ($items as $item) {
    $cer = DB::table('category_event_registrations')
        ->where('registration_id', $item->registration_id)
        ->where('category_event_id', $item->category_event_id)
        ->first();
    echo "  reg_id={$item->registration_id} ce_id={$item->category_event_id} payment_status=" . ($cer->payment_status_id ?? 'NO CER') . " pf_transaction_id=" . ($cer->pf_transaction_id ?? 'NULL') . "\n";
}
