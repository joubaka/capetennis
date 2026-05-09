<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Find any player with "other" in name/surname
$players = DB::table('players')
    ->where('name', 'like', '%other%')
    ->orWhere('surname', 'like', '%other%')
    ->get();

echo "=== Players with 'other' in name/surname ===\n";
foreach ($players as $p) {
    echo "  id={$p->id} name={$p->name} surname={$p->surname}\n";
}

// Check category_event_registrations for event 222 linked to any such player
echo "\n=== CER rows for event 222 where player name contains 'other' ===\n";
$ce222 = DB::table('category_events')->where('event_id', 222)->pluck('id');

$regs = DB::table('category_event_registrations as cer')
    ->join('registrations as r', 'r.id', '=', 'cer.registration_id')
    ->join('player_registrations as pr', 'pr.registration_id', '=', 'r.id')
    ->join('players as p', 'p.id', '=', 'pr.player_id')
    ->whereIn('cer.category_event_id', $ce222)
    ->where(function($q) {
        $q->where('p.name', 'like', '%other%')
          ->orWhere('p.surname', 'like', '%other%');
    })
    ->select('cer.*', 'p.name as pname', 'p.surname as psurname', 'p.id as player_id')
    ->get();

foreach ($regs as $r) {
    echo "  cer_id={$r->id} reg_id={$r->registration_id} ce_id={$r->category_event_id} player={$r->pname} {$r->psurname} (id={$r->player_id}) payment_status={$r->payment_status_id}\n";
}

// Also check registration_order_items for event 222 orders
echo "\n=== Order items for event 222 where player name contains 'other' ===\n";
$items = DB::table('registration_order_items as roi')
    ->join('players as p', 'p.id', '=', 'roi.player_id')
    ->whereIn('roi.category_event_id', $ce222)
    ->where(function($q) {
        $q->where('p.name', 'like', '%other%')
          ->orWhere('p.surname', 'like', '%other%');
    })
    ->select('roi.*', 'p.name as pname', 'p.surname as psurname')
    ->get();

foreach ($items as $i) {
    $order = DB::table('registration_orders')->where('id', $i->order_id)->first();
    $user  = DB::table('users')->where('id', $order->user_id)->first();
    echo "  order={$i->order_id} player={$i->pname} {$i->psurname} (id={$i->player_id}) ce_id={$i->category_event_id} pay_status={$order->pay_status} user={$user->name} ({$user->email})\n";
}
