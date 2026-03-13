<?php
require __DIR__.'/vendor/autoload.php';
$a=require_once __DIR__.'/bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== USER LOOKUP (Vollgraaf) ===\n";
$users = App\Models\User::where('name','like','%vollgraaf%')->orWhere('email','like','%vollgraaf%')->orWhere('userSurname','like','%vollgraaf%')->get();
if ($users->isEmpty()) { echo "No user found by name/email\n"; }
foreach ($users as $u) {
    echo "User ID: {$u->id} | {$u->name} | {$u->userName} {$u->userSurname} | {$u->email}\n";
    $w = $u->wallet;
    if ($w) {
        echo "  Wallet ID: {$w->id} | Balance: R".number_format($w->balance,2)."\n";
        foreach ($w->transactions()->latest()->get() as $t) {
            $ref = $t->meta['reference'] ?? '-';
            echo "  {$t->created_at} | {$t->type} | R".number_format($t->amount,2)." | {$t->source_type} | {$ref}\n";
        }
        if ($w->transactions()->count()===0) echo "  No transactions\n";
    } else { echo "  No wallet\n"; }
}

echo "\n=== PLAYER LOOKUP (Vollgraaf) ===\n";
$players = App\Models\Player::where('surname','like','%vollgraaf%')->orWhere('name','like','%Mia%')->where('surname','like','%voll%')->get();
foreach ($players as $p) {
    echo "Player ID: {$p->id} | {$p->name} {$p->surname} | {$p->email}\n";
    foreach ($p->users as $lu) { echo "  Linked User: {$lu->id} | {$lu->name} | {$lu->email}\n"; }
    if ($p->users->isEmpty()) echo "  No linked users\n";
}
if ($players->isEmpty()) echo "  None found\n";

echo "\n=== WITHDRAWN REGS (Vollgraaf users) ===\n";
foreach ($users as $u) {
    $wd = App\Models\CategoryEventRegistration::where('user_id',$u->id)->where('status','withdrawn')->get();
    foreach ($wd as $r) { echo "  CER:{$r->id} | event_reg:{$r->category_event_id} | method:{$r->refund_method} | status:{$r->refund_status} | net:R".number_format($r->refund_net??0,2)." | wallet_deb:{$r->wallet_debited}\n"; }
}

echo "\n=== ALL REGS for Vollgraaf users ===\n";
foreach ($users as $u) {
    $regs = App\Models\CategoryEventRegistration::where('user_id',$u->id)->get();
    echo "User {$u->id} has ".$regs->count()." registrations\n";
    foreach ($regs as $r) { echo "  CER:{$r->id} | status:{$r->status} | refund_method:{$r->refund_method} | refund_status:{$r->refund_status} | wallet_deb:{$r->wallet_debited}\n"; }
}

echo "\n=== TEAM ORDERS for Vollgraaf players ===\n";
if (!$players->isEmpty()) {
    $pIds = $players->pluck('id')->toArray();
    $tpos = App\Models\TeamPaymentOrder::whereIn('player_id',$pIds)->get();
    foreach ($tpos as $r) { echo "  TPO:{$r->id} | team:{$r->team_id} | event:{$r->event_id} | pay:{$r->pay_status} | R".number_format($r->total_amount??0,2)." | refund:{$r->refund_method}|{$r->refund_status} | wallet_deb:{$r->wallet_debited}\n"; }
    if ($tpos->isEmpty()) echo "  None\n";
}

echo "\n=== BANK REFUNDS (Vollgraaf) ===\n";
$br = App\Models\CategoryEventRegistration::where('refund_account_name','like','%vollgraaf%')->get();
foreach ($br as $r) { echo "  CER:{$r->id} | {$r->refund_status} | R".number_format($r->refund_net??0,2)."\n"; }
$bt = App\Models\TeamPaymentOrder::where('refund_account_name','like','%vollgraaf%')->get();
foreach ($bt as $r) { echo "  TPO:{$r->id} | {$r->refund_status} | R".number_format($r->refund_net??0,2)."\n"; }
if ($br->isEmpty() && $bt->isEmpty()) echo "  None\n";

echo "\nDone.\n";