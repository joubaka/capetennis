<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$multiItemOrders = [9483, 9479, 9472];

foreach ($multiItemOrders as $orderId) {
    $order = DB::table('registration_orders')->where('id', $orderId)->first();
    $user  = DB::table('users')->where('id', $order->user_id)->first();

    echo "=== Order {$orderId} | user_id={$order->user_id} | {$user->name} ({$user->email}) ===\n";

    $items = DB::table('registration_order_items')->where('order_id', $orderId)->get();
    foreach ($items as $i => $item) {
        $player   = DB::table('players')->where('id', $item->player_id)->first();
        $ce       = DB::table('category_events')->where('id', $item->category_event_id)->first();
        $category = $ce ? DB::table('categories')->where('id', $ce->category_id)->first() : null;
        $itemUser = DB::table('users')->where('id', $item->user_id)->first();

        echo "  Item " . ($i+1) . ":\n";
        echo "    Player:    {$player->name} {$player->surname} (id={$player->id})\n";
        echo "    Category:  " . ($category->name ?? 'N/A') . " (ce_id={$item->category_event_id})\n";
        echo "    Price:     R{$item->item_price}\n";
        echo "    Added by:  {$itemUser->name} ({$itemUser->email}) [user_id={$item->user_id}]\n";
        echo "    registration_id: {$item->registration_id}\n";

        // Check CER status
        $cer = DB::table('category_event_registrations')
            ->where('registration_id', $item->registration_id)
            ->where('category_event_id', $item->category_event_id)
            ->first();
        echo "    CER payment_status_id: " . ($cer->payment_status_id ?? 'NO CER') . "\n";
    }
    echo "\n";
}
