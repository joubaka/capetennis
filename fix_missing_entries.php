<?php
/**
 * DRY RUN — shows what would be fixed without making changes.
 * Run: php fix_missing_entries.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Registration;
use App\Models\RegistrationOrderItems;
use App\Models\CategoryEventRegistration;
use App\Models\Player;
use App\Models\CategoryEvent;
use App\Models\EventType;
use Illuminate\Support\Facades\DB;

echo "=== DRY RUN — No changes will be made (Individual events only) ===\n\n";

// Get individual event type IDs
$individualTypeIds = EventType::where('type', EventType::INDIVIDUAL)->pluck('id');

$items = RegistrationOrderItems::whereNotNull('registration_id')
    ->whereNotNull('player_id')
    ->whereNotNull('category_event_id')
    ->where('created_at', '>=', now()->subDays(30))
    ->whereHas('category_event.event', function ($q) use ($individualTypeIds) {
        $q->whereIn('eventType', $individualTypeIds);
    })
    ->whereIn('order_id', DB::table('registration_orders')->where('pay_status', 1)->pluck('id'))
    ->get();

echo "Total order items found: " . $items->count() . "\n\n";

$missingPlayers = 0;
$missingEntries = 0;
$wrongPayment = 0;
$skipped = 0;

foreach ($items as $item) {
    $registration = Registration::find($item->registration_id);
    if (!$registration) {
        $skipped++;
        continue;
    }

    $order = DB::table('registration_orders')->where('id', $item->order_id)->first();
    $isPaid = $order && $order->pay_status == 1;

    $player = Player::find($item->player_id);
    $catEvent = CategoryEvent::with('event', 'category')->find($item->category_event_id);

    $playerName = $player ? trim($player->name . ' ' . $player->surname) : "Player#{$item->player_id}";
    $eventName = $catEvent && $catEvent->event ? $catEvent->event->name : "Event?";
    $categoryName = $catEvent && $catEvent->category ? $catEvent->category->name : "Cat?";
    $orderStatus = $isPaid ? 'PAID' : 'UNPAID';

    // 1. Check missing player attachment
    $playerAttached = DB::table('player_registrations')
        ->where('registration_id', $registration->id)
        ->where('player_id', $item->player_id)
        ->exists();

    if (!$playerAttached) {
        $missingPlayers++;
        echo "[MISSING PLAYER] {$playerName} not attached to Registration#{$registration->id}\n";
        echo "  Event: {$eventName} | Category: {$categoryName} | Order: {$orderStatus}\n\n";
    }

    // 2. Check missing CategoryEventRegistration
    $entry = CategoryEventRegistration::where('registration_id', $registration->id)
        ->where('category_event_id', $item->category_event_id)
        ->first();

    if (!$entry) {
        $missingEntries++;
        echo "[MISSING ENTRY] {$playerName} -> {$categoryName}\n";
        echo "  Event: {$eventName} | Order#{$item->order_id} ({$orderStatus})\n";
        echo "  Would create with payment_status_id = " . ($isPaid ? '1 (Paid)' : '0 (Unpaid)') . "\n\n";
    }

    // 3. Check wrong payment status
    if ($entry && $isPaid && $entry->payment_status_id != 1) {
        $wrongPayment++;
        echo "[WRONG PAYMENT STATUS] {$playerName} -> {$categoryName}\n";
        echo "  Event: {$eventName} | Order is PAID but entry shows payment_status_id={$entry->payment_status_id}\n";
        echo "  Would update to payment_status_id = 1\n\n";
    }
}

echo "=== DRY RUN SUMMARY ===\n";
echo "Total items checked:        " . $items->count() . "\n";
echo "Missing player attachments: {$missingPlayers}\n";
echo "Missing entries:            {$missingEntries}\n";
echo "Wrong payment status:       {$wrongPayment}\n";
echo "Skipped (no registration):  {$skipped}\n";
echo "\nTo apply fixes, run: php fix_missing_entries.php --apply\n";
