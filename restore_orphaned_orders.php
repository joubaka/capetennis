<?php
/**
 * restore_orphaned_orders.php
 *
 * Restores the two deleted RegistrationOrders (9220 and 9259) for event 235
 * (Cavaliers Junior Ceres Tournament 2026) and marks all associated
 * CategoryEventRegistrations as withdrawn for record purposes.
 *
 * TX 1176: R855 | pf_payment_id 293890767 | user 3114 | player 3550 | cat_event 1971 (u/13 Boys-A) | 3 entries
 * TX 1218: R285 | pf_payment_id 294643427 | user 725  | player 3417 | cat_event 1960 (u/12 Girls-A) | 1 entry
 *
 * Run once: php restore_orphaned_orders.php
 * ALREADY EXECUTED — do not run again.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Safety guard — refuse to re-run if the data already exists
if (DB::table('registration_orders')->whereIn('id', [9220, 9259])->count() === 2) {
    echo "Orders 9220 and 9259 already exist. Nothing to do.\n";
    exit(0);
}

// ── Helper: create a registration + player_registration pivot ─────────────────
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
    $now = now()->toDateTimeString();

    // ─────────────────────────────────────────────────────────────────────────
    // ORDER 9220  |  TX 1176  |  R855  |  user 3114  |  3 entries
    // category_event 1971 (u/13 Boys-A, R285 each)
    // The transaction only records one player (3550) and one category_event (1971).
    // We recreate the order with 3 order items (all same player/cat_event since
    // that's the only data available), restore the order, and create 3 CERs.
    // ─────────────────────────────────────────────────────────────────────────

    $tx1Created = '2026-04-13 11:20:26'; // matches transaction created_at

    // Recreate the deleted order with the original ID
    DB::statement("INSERT INTO registration_orders
        (id, user_id, wallet_reserved, wallet_debited, payfast_amount_due,
         payfast_paid, pay_status, payfast_pf_payment_id, created_at, updated_at)
        VALUES (9220, 3114, 0, 0, 855.00, 1, 1, '293890767', ?, ?)",
        [$tx1Created, $tx1Created]
    );
    echo "Created registration_order 9220\n";

    // Create 3 order items (all for the same player/category since that's all we know)
    for ($i = 0; $i < 3; $i++) {
        $regId = createRegistration(3550, $tx1Created);
        DB::table('registration_order_items')->insert([
            'order_id'         => 9220,
            'category_event_id'=> 1971,
            'player_id'        => 3550,
            'user_id'          => 3114,
            'registration_id'  => $regId,
            'item_price'       => 285.00,
            'parent'           => null,
            'created_at'       => $tx1Created,
            'updated_at'       => $tx1Created,
        ]);

        // Create the CategoryEventRegistration marked as withdrawn
        DB::table('category_event_registrations')->insert([
            'category_event_id'  => 1971,
            'registration_id'    => $regId,
            'user_id'            => 3114,
            'payment_status_id'  => 1,
            'pf_transaction_id'  => '293890767',
            'status'             => 'withdrawn',
            'withdrawn_at'       => $tx1Created,
            'refund_status'      => 'not_refunded',
            'created_at'         => $tx1Created,
            'updated_at'         => $now,
        ]);
        echo "  Created registration $regId + CER for order 9220 (entry " . ($i + 1) . " of 3)\n";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ORDER 9259  |  TX 1218  |  R285  |  user 725  |  1 entry
    // category_event 1960 (u/12 Girls-A, R285)
    // ─────────────────────────────────────────────────────────────────────────

    $tx2Created = '2026-04-18 22:28:52';

    DB::statement("INSERT INTO registration_orders
        (id, user_id, wallet_reserved, wallet_debited, payfast_amount_due,
         payfast_paid, pay_status, payfast_pf_payment_id, created_at, updated_at)
        VALUES (9259, 725, 0, 0, 285.00, 1, 1, '294643427', ?, ?)",
        [$tx2Created, $tx2Created]
    );
    echo "Created registration_order 9259\n";

    $regId2 = createRegistration(3417, $tx2Created);
    DB::table('registration_order_items')->insert([
        'order_id'         => 9259,
        'category_event_id'=> 1960,
        'player_id'        => 3417,
        'user_id'          => 725,
        'registration_id'  => $regId2,
        'item_price'       => 285.00,
        'parent'           => null,
        'created_at'       => $tx2Created,
        'updated_at'       => $tx2Created,
    ]);

    DB::table('category_event_registrations')->insert([
        'category_event_id'  => 1960,
        'registration_id'    => $regId2,
        'user_id'            => 725,
        'payment_status_id'  => 1,
        'pf_transaction_id'  => '294643427',
        'status'             => 'withdrawn',
        'withdrawn_at'       => $tx2Created,
        'refund_status'      => 'not_refunded',
        'created_at'         => $tx2Created,
        'updated_at'         => $now,
    ]);
    echo "  Created registration $regId2 + CER for order 9259 (1 entry)\n";

    echo "\nDone. Verify:\n";
    $o1 = DB::table('registration_orders')->find(9220);
    $o2 = DB::table('registration_orders')->find(9259);
    echo "Order 9220 exists: " . ($o1 ? 'YES' : 'NO') . "\n";
    echo "Order 9259 exists: " . ($o2 ? 'YES' : 'NO') . "\n";
    $items1 = DB::table('registration_order_items')->where('order_id', 9220)->count();
    $items2 = DB::table('registration_order_items')->where('order_id', 9259)->count();
    echo "Items for order 9220: $items1 (expected 3)\n";
    echo "Items for order 9259: $items2 (expected 1)\n";
    $cers1 = DB::table('category_event_registrations')->where('pf_transaction_id', '293890767')->count();
    $cers2 = DB::table('category_event_registrations')->where('pf_transaction_id', '294643427')->count();
    echo "CERs for tx 293890767: $cers1 (expected 3)\n";
    echo "CERs for tx 294643427: $cers2 (expected 1)\n";
});
