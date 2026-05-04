<?php
/**
 * remote_restore_orphaned_orders.php
 *
 * Restores deleted RegistrationOrders 9220 & 9259 for event 235
 * (Cavaliers Junior Ceres Tournament 2026) on remote.
 *
 * TX 1176: R855 | pf 293890767 | user 3114 | order 9220 | 3 players:
 *   - Benjamin Brynard Van der Merwe  #3550 | cat_event 1971 (u/13 Boys-A)
 *   - Charl Johannes Van der Merwe    #4269 | cat_event 1971 (u/13 Boys-A)
 *   - Christina Susarah Van der Merwe #4273 | cat_event 1956 (u/10 Girls-A)
 *
 * TX 1218: R285 | pf 294643427 | user 725 | order 9259 | 1 player:
 *   - Katryn Zaayman #3417 | cat_event 1960 (u/12 Girls-A)
 *
 * Safe to run: will abort if orders already exist.
 * Upload to root of Laravel project, run: php remote_restore_orphaned_orders.php
 * Delete the file from the server after running.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$existing = DB::table('registration_orders')->whereIn('id', [9220, 9259])->count();
if ($existing > 0) {
    echo "ERROR: One or both orders (9220, 9259) already exist ($existing found). Aborting.\n";
    exit(1);
}

function createRegistration(int $playerId, string $now): int
{
    $regId = DB::table('registrations')->insertGetId([
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('player_registrations')->insert([
        'player_id'       => $playerId,
        'registration_id' => $regId,
        'created_at'      => $now,
        'updated_at'      => $now,
    ]);
    return $regId;
}

DB::transaction(function () {
    $now        = now()->toDateTimeString();
    $tx1Created = '2026-04-13 11:20:26';
    $tx2Created = '2026-04-18 22:28:52';

    DB::statement(
        "INSERT INTO registration_orders (id, user_id, wallet_reserved, wallet_debited, payfast_amount_due, payfast_paid, pay_status, payfast_pf_payment_id, created_at, updated_at) VALUES (9220, 3114, 0, 0, 855.00, 1, 1, '293890767', ?, ?)",
        [$tx1Created, $tx1Created]
    );
    echo "Created registration_order 9220\n";

    $order9220Items = [
        ['player_id' => 3550, 'category_event_id' => 1971, 'label' => 'Benjamin Brynard Van der Merwe (u/13 Boys-A)'],
        ['player_id' => 4269, 'category_event_id' => 1971, 'label' => 'Charl Johannes Van der Merwe (u/13 Boys-A)'],
        ['player_id' => 4273, 'category_event_id' => 1956, 'label' => 'Christina Susarah Van der Merwe (u/10 Girls-A)'],
    ];

    foreach ($order9220Items as $item) {
        $regId = createRegistration($item['player_id'], $tx1Created);
        DB::table('registration_order_items')->insert([
            'order_id'          => 9220,
            'category_event_id' => $item['category_event_id'],
            'player_id'         => $item['player_id'],
            'user_id'           => 3114,
            'registration_id'   => $regId,
            'item_price'        => 285.00,
            'parent'            => null,
            'created_at'        => $tx1Created,
            'updated_at'        => $tx1Created,
        ]);
        DB::table('category_event_registrations')->insert([
            'category_event_id' => $item['category_event_id'],
            'registration_id'   => $regId,
            'user_id'           => 3114,
            'payment_status_id' => 1,
            'pf_transaction_id' => '293890767',
            'status'            => 'active',
            'withdrawn_at'      => null,
            'refund_status'     => 'not_refunded',
            'created_at'        => $tx1Created,
            'updated_at'        => $now,
        ]);
        echo "  {$item['label']}: registration $regId + order item + CER created (active)\n";
    }

    DB::statement(
        "INSERT INTO registration_orders (id, user_id, wallet_reserved, wallet_debited, payfast_amount_due, payfast_paid, pay_status, payfast_pf_payment_id, created_at, updated_at) VALUES (9259, 725, 0, 0, 285.00, 1, 1, '294643427', ?, ?)",
        [$tx2Created, $tx2Created]
    );
    echo "Created registration_order 9259\n";

    $regId2 = createRegistration(3417, $tx2Created);
    DB::table('registration_order_items')->insert([
        'order_id'          => 9259,
        'category_event_id' => 1960,
        'player_id'         => 3417,
        'user_id'           => 725,
        'registration_id'   => $regId2,
        'item_price'        => 285.00,
        'parent'            => null,
        'created_at'        => $tx2Created,
        'updated_at'        => $tx2Created,
    ]);
    DB::table('category_event_registrations')->insert([
        'category_event_id' => 1960,
        'registration_id'   => $regId2,
        'user_id'           => 725,
        'payment_status_id' => 1,
        'pf_transaction_id' => '294643427',
        'status'            => 'active',
        'withdrawn_at'      => null,
        'refund_status'     => 'not_refunded',
        'created_at'        => $tx2Created,
        'updated_at'        => $now,
    ]);
    echo "  Katryn Zaayman (u/12 Girls-A): registration $regId2 + order item + CER created (active)\n";

    echo "\n=== Verification ===\n";
    echo "Order 9220 exists: "   . (DB::table('registration_orders')->find(9220) ? 'YES' : 'NO') . "\n";
    echo "Order 9259 exists: "   . (DB::table('registration_orders')->find(9259) ? 'YES' : 'NO') . "\n";
    echo "Items for 9220: "      . DB::table('registration_order_items')->where('order_id', 9220)->count() . " (expected 3)\n";
    echo "Items for 9259: "      . DB::table('registration_order_items')->where('order_id', 9259)->count() . " (expected 1)\n";
    echo "CERs pf 293890767: "   . DB::table('category_event_registrations')->where('pf_transaction_id', '293890767')->count() . " (expected 3)\n";
    echo "CERs pf 294643427: "   . DB::table('category_event_registrations')->where('pf_transaction_id', '294643427')->count() . " (expected 1)\n";
    echo "\nDone. Delete this file from the server after running.\n";
});