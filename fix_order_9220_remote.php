<?php
/**
 * fix_order_9220_remote.php
 *
 * Fixes order 9220 on remote: corrects the 3 order items to the right players
 * and creates missing CERs for Charl and Christina.
 *
 * Expected state after running:
 *   Item 1: Benjamin Brynard Van der Merwe #3550 | u/13 Boys-A (1971) | CER exists
 *   Item 2: Charl Johannes Van der Merwe   #4269 | u/13 Boys-A (1971) | CER created
 *   Item 3: Christina Susarah Van der Merwe #4273 | u/10 Girls-A (1956) | CER created
 *
 * Delete this file from the server after running.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Show current state
echo "=== Current order 9220 items ===\n";
$items = DB::table('registration_order_items')->where('order_id', 9220)->orderBy('id')->get();
foreach ($items as $it) {
    echo "  Item #{$it->id}: player={$it->player_id} cat_event={$it->category_event_id} reg={$it->registration_id}\n";
}

echo "\n=== Current CERs for pf 293890767 ===\n";
$cers = DB::table('category_event_registrations')->where('pf_transaction_id', '293890767')->get();
foreach ($cers as $c) {
    echo "  CER #{$c->id}: cat_event={$c->category_event_id} reg={$c->registration_id} status={$c->status}\n";
}

if ($items->count() !== 3) {
    echo "\nERROR: Expected 3 items for order 9220, found {$items->count()}. Aborting.\n";
    exit(1);
}

$itemArr = $items->values();
$item1 = $itemArr[0]; // keep as Benjamin
$item2 = $itemArr[1]; // fix to Charl
$item3 = $itemArr[2]; // fix to Christina

$now = now()->toDateTimeString();
$tx1Created = '2026-04-13 11:20:26';

DB::transaction(function () use ($item1, $item2, $item3, $now, $tx1Created, $cers) {

    // -- Item 2: Charl #4269, u/13 Boys-A (1971) --
    $reg2 = DB::table('registrations')->insertGetId(['created_at' => $tx1Created, 'updated_at' => $tx1Created]);
    DB::table('player_registrations')->insert(['player_id' => 4269, 'registration_id' => $reg2, 'created_at' => $tx1Created, 'updated_at' => $tx1Created]);
    DB::table('registration_order_items')->where('id', $item2->id)->update([
        'player_id'         => 4269,
        'category_event_id' => 1971,
        'registration_id'   => $reg2,
        'updated_at'        => $now,
    ]);
    DB::table('category_event_registrations')->insert([
        'category_event_id' => 1971,
        'registration_id'   => $reg2,
        'user_id'           => 3114,
        'payment_status_id' => 1,
        'pf_transaction_id' => '293890767',
        'status'            => 'active',
        'withdrawn_at'      => null,
        'refund_status'     => 'not_refunded',
        'created_at'        => $tx1Created,
        'updated_at'        => $now,
    ]);
    echo "Charl #4269 (u/13 Boys-A): registration $reg2 + item #{$item2->id} + CER created\n";

    // -- Item 3: Christina #4273, u/10 Girls-A (1956) --
    $reg3 = DB::table('registrations')->insertGetId(['created_at' => $tx1Created, 'updated_at' => $tx1Created]);
    DB::table('player_registrations')->insert(['player_id' => 4273, 'registration_id' => $reg3, 'created_at' => $tx1Created, 'updated_at' => $tx1Created]);
    DB::table('registration_order_items')->where('id', $item3->id)->update([
        'player_id'         => 4273,
        'category_event_id' => 1956,
        'registration_id'   => $reg3,
        'updated_at'        => $now,
    ]);
    DB::table('category_event_registrations')->insert([
        'category_event_id' => 1956,
        'registration_id'   => $reg3,
        'user_id'           => 3114,
        'payment_status_id' => 1,
        'pf_transaction_id' => '293890767',
        'status'            => 'active',
        'withdrawn_at'      => null,
        'refund_status'     => 'not_refunded',
        'created_at'        => $tx1Created,
        'updated_at'        => $now,
    ]);
    echo "Christina #4273 (u/10 Girls-A): registration $reg3 + item #{$item3->id} + CER created\n";

    // Clean up the now-orphaned registrations that item2 and item3 previously pointed to
    $oldRegs = [$item2->registration_id, $item3->registration_id];
    DB::table('player_registrations')->whereIn('registration_id', $oldRegs)->delete();
    DB::table('registrations')->whereIn('id', $oldRegs)->delete();
    echo "Cleaned up orphaned registrations: " . implode(', ', $oldRegs) . "\n";
});

// Verify
echo "\n=== Verification ===\n";
$items2 = DB::table('registration_order_items as roi')
    ->join('players as p', 'p.id', '=', 'roi.player_id')
    ->leftJoin('category_events as ce', 'ce.id', '=', 'roi.category_event_id')
    ->leftJoin('categories as cat', 'cat.id', '=', 'ce.category_id')
    ->where('roi.order_id', 9220)
    ->get(['roi.id', 'p.name', 'p.surname', 'cat.name as category']);
foreach ($items2 as $i) {
    echo "  Item #{$i->id}: {$i->name} {$i->surname} | {$i->category}\n";
}
$cers2 = DB::table('category_event_registrations as cer')
    ->join('category_events as ce', 'ce.id', '=', 'cer.category_event_id')
    ->join('categories as cat', 'cat.id', '=', 'ce.category_id')
    ->leftJoin('player_registrations as pr', 'pr.registration_id', '=', 'cer.registration_id')
    ->leftJoin('players as p', 'p.id', '=', 'pr.player_id')
    ->where('cer.pf_transaction_id', '293890767')
    ->get(['cer.id', 'p.name', 'p.surname', 'cat.name as category', 'cer.status']);
foreach ($cers2 as $c) {
    echo "  CER #{$c->id}: {$c->name} {$c->surname} | {$c->category} | {$c->status}\n";
}
echo "\nDone. Delete this file from the server now.\n";