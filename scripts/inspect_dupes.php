<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$ids = [526, 527, 530, 531, 536, 537, 588, 589];

echo "\n=== DUPLICATE transactions_pf ROWS ===\n";
$rows = DB::table('transactions_pf')->whereIn('id', $ids)->orderBy('pf_payment_id')->orderBy('id')->get();
foreach ($rows as $r) {
    echo sprintf(
        "id=%-4s pf_payment_id=%-12s event_id=%-4s custom_int5=%-6s amount_gross=%-8s amount_fee=%-6s created_at=%s\n",
        $r->id, $r->pf_payment_id, $r->event_id ?? 'NULL', $r->custom_int5 ?? 'NULL',
        $r->amount_gross, $r->amount_fee ?? 'NULL', $r->created_at
    );
}

// For each duplicate group, look up the linked order and CER
echo "\n=== LINKED ORDERS & REGISTRATION DATA ===\n";
$pf_ids = ['280022243','280064537','280073041','280225013'];
foreach ($pf_ids as $pfid) {
    echo "\n--- pf_payment_id: $pfid ---\n";
    $trows = DB::table('transactions_pf')->where('pf_payment_id', $pfid)->orderBy('id')->get();
    foreach ($trows as $t) {
        echo "  transactions_pf.id={$t->id} custom_int5={$t->custom_int5} event_id={$t->event_id} amount_gross={$t->amount_gross} created_at={$t->created_at}\n";
        if ($t->custom_int5) {
            $order = DB::table('registration_orders')->find($t->custom_int5);
            if ($order) {
                echo "    order.id={$order->id} pay_status={$order->pay_status} payfast_paid={$order->payfast_paid} wallet_reserved={$order->wallet_reserved} wallet_debited={$order->wallet_debited} user_id={$order->user_id}\n";
                // CERs linked via registration_order_items -> registration_id
                $regIds = DB::table('registration_order_items')->where('order_id', $order->id)->pluck('registration_id');
                if ($regIds->isNotEmpty()) {
                    $cers = DB::table('category_event_registrations')->whereIn('registration_id', $regIds)->get(['id','refund_status','registration_id']);
                    foreach ($cers as $cer) {
                        echo "      CER.id={$cer->id} refund_status={$cer->refund_status} reg_id={$cer->registration_id}\n";
                    }
                }
            } else {
                echo "    order.id={$t->custom_int5} NOT FOUND\n";
            }
        }
    }
}

echo "\n=== WALLET RESERVATION CORRUPT ORDER ===\n";
$orders = DB::table('registration_orders')
    ->where('pay_status', 1)
    ->where('wallet_debited', 0)
    ->where('wallet_reserved', '>', 0)
    ->get();
foreach ($orders as $o) {
    echo "order.id={$o->id} user_id={$o->user_id} wallet_reserved={$o->wallet_reserved} wallet_debited={$o->wallet_debited} payfast_paid={$o->payfast_paid} pay_status={$o->pay_status} created_at={$o->created_at}\n";
    $wallet = DB::table('wallets')
        ->where('payable_type', 'App\\Models\\User')
        ->where('payable_id', $o->user_id)
        ->first();
    if ($wallet) {
        $balance = DB::table('wallet_transactions')->where('wallet_id', $wallet->id)
            ->selectRaw("SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) as balance")
            ->value('balance');
        echo "  wallet.id={$wallet->id} balance={$balance}\n";
    } else {
        echo "  NO WALLET for user_id={$o->user_id}\n";
    }
    // CERs for this order via registration_order_items
    $regIds = DB::table('registration_order_items')->where('order_id', $o->id)->pluck('registration_id');
    $cers = $regIds->isNotEmpty()
        ? DB::table('category_event_registrations')->whereIn('registration_id', $regIds)->get(['id','refund_status','registration_id'])
        : collect();
    foreach ($cers as $cer) {
        echo "  CER.id={$cer->id} refund_status={$cer->refund_status} reg_id={$cer->registration_id}\n";
    }
}
