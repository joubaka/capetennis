<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== USER LOOKUP ===\n";
$user = App\Models\User::where('email', 'maridebeer19@gmail.com')->first();
if ($user) {
    echo "User ID: {$user->id} | {$user->name} | {$user->email}\n";
    $w = $user->wallet;
    if ($w) {
        echo "Wallet ID: {$w->id} | Balance: R" . number_format($w->balance, 2) . "\n";
        foreach ($w->transactions()->latest()->get() as $t) {
            $ref = $t->meta['reference'] ?? '-';
            echo "  {$t->created_at} | {$t->type} | R" . number_format($t->amount, 2) . " | {$t->source_type} | {$ref}\n";
        }
        if ($w->transactions()->count() === 0) echo "  No transactions\n";
    } else { echo "  No wallet\n"; }
} else { echo "User not found\n"; }

echo "\n=== PLAYER LOOKUP ===\n";
$players = App\Models\Player::where('surname', 'like', '%beer%')->get();
foreach ($players as $p) {
    echo "Player ID: {$p->id} | {$p->name} {$p->surname} | {$p->email}\n";
    foreach ($p->users as $lu) { echo "  Linked User: {$lu->id} | {$lu->email}\n"; }
}
if ($players->isEmpty()) echo "  None found\n";

echo "\n=== BANK REFUNDS ===\n";
$br = App\Models\CategoryEventRegistration::where('refund_account_name','like','%beer%')->get();
foreach ($br as $r) { echo "  CER:{$r->id}|{$r->status}|{$r->refund_status}|R".number_format($r->refund_net??0,2)."\n"; }
$bt = App\Models\TeamPaymentOrder::where('refund_account_name','like','%beer%')->get();
foreach ($bt as $r) { echo "  TPO:{$r->id}|{$r->refund_status}|R".number_format($r->refund_net??0,2)."\n"; }
if ($br->isEmpty() && $bt->isEmpty()) echo "  None\n";

echo "\n=== WITHDRAWN REGS ===\n";
if ($user) {
    $wd = App\Models\CategoryEventRegistration::where('user_id',$user->id)->where('status','withdrawn')->get();
    foreach ($wd as $r) { echo "  CER:{$r->id}|{$r->refund_method}|{$r->refund_status}|R".number_format($r->refund_net??0,2)."\n"; }
    if ($wd->isEmpty()) echo "  None\n";
}

if (!$players->isEmpty()) {
    $pIds = $players->pluck('id')->toArray();
    $tpos = App\Models\TeamPaymentOrder::whereIn('player_id', $pIds)->get();
    echo "\n=== TEAM ORDERS ===\n";
    foreach ($tpos as $r) { echo "  TPO:{$r->id}|team:{$r->team_id}|event:{$r->event_id}|pay:{$r->pay_status}|R".number_format($r->total_amount??0,2)."|refund:{$r->refund_method}|{$r->refund_status}|wallet:{$r->wallet_debited}\n"; }
    if ($tpos->isEmpty()) echo "  None\n";
}

echo "\nDone.\n";