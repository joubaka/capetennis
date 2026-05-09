<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$orderId = 9498;

echo "=== Order {$orderId} items ===\n";
$items = DB::table('registration_order_items')->where('order_id', $orderId)->get();
foreach ($items as $i => $item) {
    $player   = DB::table('players')->where('id', $item->player_id)->first();
    $ce       = DB::table('category_events')->where('id', $item->category_event_id)->first();
    $category = $ce ? DB::table('categories')->where('id', $ce->category_id)->first() : null;
    echo "  Item ".($i+1).": {$player->name} {$player->surname} (id={$player->id}) | {$category->name} (ce_id={$item->category_event_id}) | R{$item->item_price}\n";
}

echo "\n=== transactions_pf for order {$orderId} ===\n";
$tx = DB::table('transactions_pf')->where('custom_int5', $orderId)->first();
print_r($tx);
