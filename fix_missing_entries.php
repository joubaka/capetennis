<?php
/**
 * Fix missing CategoryEventRegistration + PlayerRegistration rows
 * for orders that were paid but never had entries created.
 *
 * Run: php artisan tinker fix_missing_entries.php
 * Or:  php fix_missing_entries.php (after requiring autoload)
 */

// Boot Laravel if running standalone
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Registration;
use App\Models\RegistrationOrderItems;
use App\Models\CategoryEventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== Fix Missing Entries ===\n\n";

// Get all order items that have registration_id, player_id, category_event_id
$items = RegistrationOrderItems::whereNotNull('registration_id')
    ->whereNotNull('player_id')
    ->whereNotNull('category_event_id')
    ->with('registration')
    ->get();

echo "Total order items found: " . $items->count() . "\n";

$fixedPlayers = 0;
$fixedEntries = 0;
$skipped = 0;
$errors = 0;

foreach ($items as $item) {
    $registration = Registration::find($item->registration_id);

    if (!$registration) {
        $skipped++;
        continue;
    }

    try {
        // 1. Ensure player is attached to registration
        $playerAttached = DB::table('player_registrations')
            ->where('registration_id', $registration->id)
            ->where('player_id', $item->player_id)
            ->exists();

        if (!$playerAttached) {
            $registration->players()->syncWithoutDetaching([$item->player_id]);
            $fixedPlayers++;
            echo "  [FIXED] Player {$item->player_id} attached to Registration {$registration->id}\n";
        }

        // 2. Ensure CategoryEventRegistration exists
        $entryExists = CategoryEventRegistration::where('registration_id', $registration->id)
            ->where('category_event_id', $item->category_event_id)
            ->exists();

        if (!$entryExists) {
            // Check if the order is paid
            $order = DB::table('registration_orders')->where('id', $item->order_id)->first();
            $isPaid = $order && $order->pay_status == 1;

            $registration->categoryEvents()->syncWithoutDetaching([
                $item->category_event_id => [
                    'payment_status_id' => $isPaid ? 1 : 0,
                    'user_id' => $item->user_id,
                ],
            ]);

            $fixedEntries++;
            $status = $isPaid ? 'PAID' : 'UNPAID';
            echo "  [FIXED] Entry created: Registration {$registration->id} -> CategoryEvent {$item->category_event_id} ({$status})\n";
        }

        // 3. If entry exists but payment_status_id is wrong, fix it
        if ($entryExists) {
            $order = DB::table('registration_orders')->where('id', $item->order_id)->first();
            if ($order && $order->pay_status == 1) {
                $entry = CategoryEventRegistration::where('registration_id', $registration->id)
                    ->where('category_event_id', $item->category_event_id)
                    ->first();

                if ($entry && $entry->payment_status_id != 1) {
                    $entry->payment_status_id = 1;
                    $entry->save();
                    $fixedEntries++;
                    echo "  [FIXED] Entry payment status updated: Registration {$registration->id} -> CategoryEvent {$item->category_event_id}\n";
                }
            }
        }

    } catch (\Throwable $e) {
        $errors++;
        echo "  [ERROR] Item {$item->id}: {$e->getMessage()}\n";
    }
}

echo "\n=== Summary ===\n";
echo "Total items checked:  " . $items->count() . "\n";
echo "Players attached:     {$fixedPlayers}\n";
echo "Entries fixed:        {$fixedEntries}\n";
echo "Skipped (no reg):     {$skipped}\n";
echo "Errors:               {$errors}\n";
echo "\nDone!\n";
