<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Missing pf_ids and their registration order ids (custom_int5 from CSV)
$missing = [
    // event 222
    ['pf'=>'299881295','event'=>222,'order'=>9497,'gross'=>285.00,'paid_at'=>'2026-05-09 12:58:45'],
    ['pf'=>'299736399','event'=>222,'order'=>9494,'gross'=>285.00,'paid_at'=>'2026-05-08 15:31:07'],
    ['pf'=>'299725251','event'=>222,'order'=>9493,'gross'=>285.00,'paid_at'=>'2026-05-08 14:42:38'],
    ['pf'=>'299714155','event'=>222,'order'=>9490,'gross'=>285.00,'paid_at'=>'2026-05-08 13:44:08'],
    ['pf'=>'299672049','event'=>222,'order'=>9489,'gross'=>285.00,'paid_at'=>'2026-05-08 10:46:17'],
    ['pf'=>'299670781','event'=>222,'order'=>9488,'gross'=>285.00,'paid_at'=>'2026-05-08 10:41:14'],
    ['pf'=>'299621133','event'=>222,'order'=>9484,'gross'=>285.00,'paid_at'=>'2026-05-08 07:30:14'],
    ['pf'=>'299591917','event'=>222,'order'=>9483,'gross'=>570.00,'paid_at'=>'2026-05-07 23:42:12'],
    ['pf'=>'299584855','event'=>222,'order'=>9481,'gross'=>285.00,'paid_at'=>'2026-05-07 22:03:13'],
    ['pf'=>'299571877','event'=>222,'order'=>9479,'gross'=>570.00,'paid_at'=>'2026-05-07 20:20:20'],
    ['pf'=>'299558993','event'=>222,'order'=>9478,'gross'=>285.00,'paid_at'=>'2026-05-07 19:02:25'],
    ['pf'=>'299547723','event'=>222,'order'=>9477,'gross'=>285.00,'paid_at'=>'2026-05-07 17:58:54'],
    ['pf'=>'299525919','event'=>222,'order'=>9475,'gross'=>285.00,'paid_at'=>'2026-05-07 16:04:51'],
    ['pf'=>'299483441','event'=>222,'order'=>9473,'gross'=>285.00,'paid_at'=>'2026-05-07 12:52:53'],
    ['pf'=>'299477759','event'=>222,'order'=>9472,'gross'=>570.00,'paid_at'=>'2026-05-07 12:29:52'],
    ['pf'=>'299474261','event'=>222,'order'=>9471,'gross'=>285.00,'paid_at'=>'2026-05-07 12:13:16'],
    ['pf'=>'299414901','event'=>222,'order'=>9468,'gross'=>285.00,'paid_at'=>'2026-05-07 08:33:04'],
    ['pf'=>'299413621','event'=>222,'order'=>9467,'gross'=>285.00,'paid_at'=>'2026-05-07 08:22:21'],
    // event 232
    ['pf'=>'299838551','event'=>232,'order'=>9496,'gross'=>285.00,'paid_at'=>'2026-05-09 08:07:45'],
    ['pf'=>'299791033','event'=>232,'order'=>9495,'gross'=>285.00,'paid_at'=>'2026-05-08 20:53:13'],
    ['pf'=>'299579839','event'=>232,'order'=>9480,'gross'=>285.00,'paid_at'=>'2026-05-07 21:17:22'],
    // event 239
    ['pf'=>'299720067','event'=>239,'order'=>9492,'gross'=>285.00,'paid_at'=>'2026-05-08 14:12:52'],
    ['pf'=>'299638591','event'=>239,'order'=>9486,'gross'=>285.00,'paid_at'=>'2026-05-08 08:48:15'],
];

echo "=== Checking registration_orders (custom_int5 = order_id) ===\n\n";
foreach ($missing as $m) {
    $order = DB::table('registration_orders')->where('id', $m['order'])->first();
    if (!$order) {
        echo "❌ Order {$m['order']} NOT FOUND in registration_orders | pf={$m['pf']}\n";
        continue;
    }

    // Check pay_status and pf_payment_id on the order
    echo "Order {$m['order']}: pay_status={$order->pay_status} pf_id_on_order=".($order->payfast_pf_payment_id ?? 'NULL')." wallet_reserved={$order->wallet_reserved} payfast_amount_due={$order->payfast_amount_due}\n";

    // Check if registration_order_items exist
    $items = DB::table('registration_order_items')->where('order_id', $m['order'])->get();
    if ($items->isEmpty()) {
        // try alternative column name
        $items = DB::table('registration_order_items')->where('registration_order_id', $m['order'])->get();
    }
    echo "  Items: " . $items->count() . "\n";

    // Check transactions_pf for this order
    $tx = DB::table('transactions_pf')->where('pf_payment_id', $m['pf'])->first();
    echo "  In transactions_pf: " . ($tx ? "YES id={$tx->id}" : "NO") . "\n";
}

echo "\n=== Checking registration_order_items column name ===\n";
$cols = DB::select("SHOW COLUMNS FROM registration_order_items");
foreach ($cols as $col) {
    echo "  {$col->Field}\n";
}

echo "\n=== Sample registration_order row ===\n";
$sample = DB::table('registration_orders')->orderBy('id','desc')->first();
print_r($sample);
