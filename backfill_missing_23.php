<?php
/**
 * Backfill script for 23 missing transactions_pf rows.
 *
 * Root cause: At the time these PayFast ITNs fired, the
 * $registration->players()->syncWithoutDetaching() call was NOT wrapped
 * in a try/catch. The missing `player_registrations` table on the remote
 * server caused an exception that aborted the entire DB transaction —
 * leaving pay_status=0, payfast_pf_payment_id=NULL, and no transactions_pf row.
 *
 * This script:
 *   1. Marks each registration_order as paid
 *   2. Updates category_event_registrations (payment_status_id=1, pf_transaction_id)
 *   3. Inserts a transactions_pf row matching the pattern of real rows
 *
 * Usage:
 *   DRY RUN (default): php backfill_missing_23.php
 *   LIVE:              php backfill_missing_23.php --live
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\CategoryEventRegistration;

$dryRun = !in_array('--live', $argv ?? []);
if ($dryRun) {
    echo "=== DRY RUN — pass --live to apply changes ===\n\n";
} else {
    echo "=== LIVE MODE — changes will be written ===\n\n";
}

// The 23 missing payments from the CSV (pf_payment_id => [event, order_id, gross, paid_at])
// paid_at taken from CSV Date column (UTC+2 but stored as-is)
$missing = [
    // event 222
    ['pf'=>'299881295','event'=>222,'order'=>9497,'gross'=>285.00,'paid_at'=>'2026-05-09 12:57:18'],
    ['pf'=>'299736399','event'=>222,'order'=>9494,'gross'=>285.00,'paid_at'=>'2026-05-09 09:54:37'],
    ['pf'=>'299725251','event'=>222,'order'=>9493,'gross'=>285.00,'paid_at'=>'2026-05-09 09:30:24'],
    ['pf'=>'299714155','event'=>222,'order'=>9490,'gross'=>285.00,'paid_at'=>'2026-05-09 08:54:29'],
    ['pf'=>'299672049','event'=>222,'order'=>9489,'gross'=>285.00,'paid_at'=>'2026-05-08 23:05:37'],
    ['pf'=>'299670781','event'=>222,'order'=>9488,'gross'=>285.00,'paid_at'=>'2026-05-08 22:59:24'],
    ['pf'=>'299621133','event'=>222,'order'=>9484,'gross'=>285.00,'paid_at'=>'2026-05-08 17:35:46'],
    ['pf'=>'299591917','event'=>222,'order'=>9483,'gross'=>570.00,'paid_at'=>'2026-05-08 14:25:11'],
    ['pf'=>'299584855','event'=>222,'order'=>9481,'gross'=>285.00,'paid_at'=>'2026-05-08 13:37:22'],
    ['pf'=>'299571877','event'=>222,'order'=>9479,'gross'=>570.00,'paid_at'=>'2026-05-08 11:59:34'],
    ['pf'=>'299558993','event'=>222,'order'=>9478,'gross'=>285.00,'paid_at'=>'2026-05-08 10:38:47'],
    ['pf'=>'299547723','event'=>222,'order'=>9477,'gross'=>285.00,'paid_at'=>'2026-05-08 09:27:12'],
    ['pf'=>'299525919','event'=>222,'order'=>9475,'gross'=>285.00,'paid_at'=>'2026-05-08 07:18:53'],
    ['pf'=>'299483441','event'=>222,'order'=>9473,'gross'=>285.00,'paid_at'=>'2026-05-07 21:28:19'],
    ['pf'=>'299477759','event'=>222,'order'=>9472,'gross'=>570.00,'paid_at'=>'2026-05-07 20:51:44'],
    ['pf'=>'299474261','event'=>222,'order'=>9471,'gross'=>285.00,'paid_at'=>'2026-05-07 20:28:36'],
    ['pf'=>'299414901','event'=>222,'order'=>9468,'gross'=>285.00,'paid_at'=>'2026-05-07 14:43:27'],
    ['pf'=>'299413621','event'=>222,'order'=>9467,'gross'=>285.00,'paid_at'=>'2026-05-07 14:37:09'],
    // event 232
    ['pf'=>'299838551','event'=>232,'order'=>9496,'gross'=>285.00,'paid_at'=>'2026-05-09 11:21:44'],
    ['pf'=>'299791033','event'=>232,'order'=>9495,'gross'=>285.00,'paid_at'=>'2026-05-09 10:42:18'],
    ['pf'=>'299579839','event'=>232,'order'=>9480,'gross'=>285.00,'paid_at'=>'2026-05-08 12:49:53'],
    // event 239
    ['pf'=>'299720067','event'=>239,'order'=>9492,'gross'=>285.00,'paid_at'=>'2026-05-09 09:22:11'],
    ['pf'=>'299638591','event'=>239,'order'=>9486,'gross'=>285.00,'paid_at'=>'2026-05-08 18:53:42'],
];

function calcFee(float $gross): array {
    $fee = \App\Models\SiteSetting::calculatePayfastFee($gross);
    $net = round($gross - $fee, 2);
    return ['fee' => $fee, 'net' => $net];
}

$errors   = 0;
$success  = 0;

foreach ($missing as $row) {
    $orderId  = $row['order'];
    $pfId     = $row['pf'];
    $eventId  = $row['event'];
    $gross    = $row['gross'];
    $paidAt   = $row['paid_at'];

    echo "--- Order {$orderId} | PF {$pfId} | Event {$eventId} | R{$gross} ---\n";

    // Guard: already in transactions_pf?
    $alreadyTx = DB::table('transactions_pf')->where('pf_payment_id', $pfId)->exists();
    if ($alreadyTx) {
        echo "  SKIP: already in transactions_pf\n\n";
        continue;
    }

    // Load order
    $order = DB::table('registration_orders')->where('id', $orderId)->first();
    if (!$order) {
        echo "  ERROR: order not found\n\n";
        $errors++;
        continue;
    }

    if ($order->pay_status == 1) {
        echo "  NOTE: order already pay_status=1 (pf_id={$order->payfast_pf_payment_id})\n";
    }

    // Load items
    $items = DB::table('registration_order_items')->where('order_id', $orderId)->get();
    if ($items->isEmpty()) {
        echo "  ERROR: no order items found\n\n";
        $errors++;
        continue;
    }

    // Use first item for transaction metadata
    $firstItem = $items->first();
    $categoryEventId = $firstItem->category_event_id;
    $playerId        = $firstItem->player_id;
    $userId          = $order->user_id;

    // Load event name
    $ceRow = DB::table('category_events')->where('id', $categoryEventId)->first();
    $event = $ceRow ? DB::table('events')->where('id', $ceRow->event_id)->first() : null;
    $category = $ceRow ? DB::table('categories')->where('id', $ceRow->category_id)->first() : null;
    $player = DB::table('players')->where('id', $playerId)->first();
    $eventName = $event->name ?? "Event {$eventId}";
    $categoryName = $category->name ?? '';
    $playerName = $player ? trim($player->name.' '.$player->surname) : '';
    $cape_tennis_fee = 15.00;

    ['fee' => $fee, 'net' => $net] = calcFee($gross);

    echo "  Player: {$playerName} | Category: {$categoryName}\n";
    echo "  Event: {$eventName}\n";
    echo "  gross={$gross} fee={$fee} net={$net}\n";
    echo "  Items: " . $items->count() . "\n";

    if ($dryRun) {
        echo "  [DRY RUN] Would update order + CERs + insert transactions_pf\n\n";
        $success++;
        continue;
    }

    // --- LIVE ---
    try {
        DB::transaction(function () use (
            $orderId, $pfId, $eventId, $gross, $fee, $net, $paidAt,
            $order, $items, $firstItem, $categoryEventId, $playerId, $userId,
            $eventName, $categoryName, $playerName, $cape_tennis_fee,
            $ceRow, $event, $category, $player
        ) {
            // 1. Mark order paid
            DB::table('registration_orders')->where('id', $orderId)->update([
                'pay_status'           => 1,
                'payfast_paid'         => 1,
                'payfast_pf_payment_id'=> $pfId,
                'updated_at'           => now(),
            ]);

            // 2. Update category_event_registrations for each item
            foreach ($items as $item) {
                $cerExists = DB::table('category_event_registrations')
                    ->where('registration_id', $item->registration_id)
                    ->where('category_event_id', $item->category_event_id)
                    ->exists();

                if ($cerExists) {
                    DB::table('category_event_registrations')
                        ->where('registration_id', $item->registration_id)
                        ->where('category_event_id', $item->category_event_id)
                        ->update([
                            'payment_status_id' => 1,
                            'pf_transaction_id' => $pfId,
                            'updated_at'        => now(),
                        ]);
                } else {
                    DB::table('category_event_registrations')->insert([
                        'registration_id'   => $item->registration_id,
                        'category_event_id' => $item->category_event_id,
                        'user_id'           => $item->user_id,
                        'payment_status_id' => 1,
                        'pf_transaction_id' => $pfId,
                        'status'            => 'active',
                        'refund_status'     => 'not_refunded',
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }
            }

            // 3. Insert transactions_pf row
            DB::table('transactions_pf')->insert([
                'transaction_type'  => 'Registration',
                'amount_gross'      => $gross,
                'amount_fee'        => $fee,
                'amount_net'        => $net,
                'event_id'          => $eventId,
                'player_id'         => $playerId,
                'category_event_id' => $categoryEventId,
                'custom_int5'       => $orderId,
                'custom_int4'       => $order->user_id,
                'custom_int3'       => $eventId,
                'custom_int2'       => $playerId,
                'custom_int1'       => $categoryEventId,
                'custom_str2'       => $playerName,
                'custom_str3'       => $eventName,
                'custom_str1'       => $categoryName,
                'pf_payment_id'     => $pfId,
                'item_name'         => $eventName,
                'cape_tennis_fee'   => $cape_tennis_fee,
                'is_test'           => 0,
                'created_at'        => $paidAt,
                'updated_at'        => $paidAt,
            ]);
        });

        echo "  ✅ Done\n\n";
        $success++;
    } catch (\Throwable $e) {
        echo "  ❌ ERROR: " . $e->getMessage() . "\n\n";
        $errors++;
    }
}

echo "=== Summary ===\n";
echo "Processed: " . count($missing) . "\n";
echo "Success:   {$success}\n";
echo "Errors:    {$errors}\n";
if ($dryRun) {
    echo "\nRun with --live to apply changes.\n";
}
