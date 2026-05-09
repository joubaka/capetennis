<?php
/**
 * Backfill orders 9498 and 9499 from PayFast CSV
 * Run: php backfill_9498_9499.php [--live]
 */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\RegistrationOrder;
use App\Models\CategoryEventRegistration;
use App\Models\Transaction;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;

$isDryRun = !in_array('--live', $argv ?? []);
echo $isDryRun ? "=== DRY RUN (pass --live to apply) ===\n\n" : "=== LIVE RUN ===\n\n";

$rows = [
    [
        'order_id'      => 9499,
        'pf_payment_id' => '299913471',
        'gross'         => 285.00,
        'fee'           => 12.79,
        'net'           => 272.21,
        'event_id'      => 222,
        'paid_at'       => '2026-05-09 17:24:07',
        'custom_int1'   => 2090,
        'custom_int2'   => 3473,
        'custom_int3'   => 222,
        'custom_int4'   => 3058,
        'custom_str1'   => 'u/12 Boys',
        'custom_str2'   => 'Ralph Köster',
        'custom_str3'   => 'Primary Schools Witzenberg/Breede Vallei/Langeberg Primary Schools Trials - Leg 2 2026',
        'custom_str4'   => 'Janine Köster',
        'item_name'     => 'Primary Schools Witzenberg/Breede Vallei/Langeberg Primary Schools Trials - Leg 2 2026',
    ],
    [
        'order_id'      => 9498,
        'pf_payment_id' => '299910931',
        'gross'         => 570.00,
        'fee'           => 23.28,
        'net'           => 546.72,
        'event_id'      => 222,
        'paid_at'       => '2026-05-09 17:00:37',
        'custom_int1'   => 2088,
        'custom_int2'   => 4315,
        'custom_int3'   => 222,
        'custom_int4'   => 3695,
        'custom_str1'   => 'u/11 Boys',
        'custom_str2'   => 'Joa Louw',
        'custom_str3'   => 'Primary Schools Witzenberg/Breede Vallei/Langeberg Primary Schools Trials - Leg 2 2026',
        'custom_str4'   => 'Sephan',
        'item_name'     => 'Primary Schools Witzenberg/Breede Vallei/Langeberg Primary Schools Trials - Leg 2 2026',
    ],
];

foreach ($rows as $row) {
    $orderId      = $row['order_id'];
    $pfPaymentId  = $row['pf_payment_id'];

    echo "--- Order {$orderId} / PF {$pfPaymentId} ---\n";

    // Check if already in transactions_pf
    $exists = Transaction::where('pf_payment_id', $pfPaymentId)->first();
    if ($exists) {
        echo "  ✅ Already in transactions_pf (id={$exists->id}) — skipping\n\n";
        continue;
    }

    $order = RegistrationOrder::with('items.category_event')->find($orderId);
    if (!$order) {
        echo "  ❌ Order not found — skipping\n\n";
        continue;
    }

    echo "  Order pay_status={$order->pay_status}, payfast_paid={$order->payfast_paid}\n";
    echo "  Items: " . $order->items->count() . "\n";

    foreach ($order->items as $item) {
        $cer = CategoryEventRegistration::where('registration_id', $item->registration_id)
            ->where('category_event_id', $item->category_event_id)
            ->first();
        echo "  CER registration_id={$item->registration_id} category_event_id={$item->category_event_id} payment_status=" . ($cer->payment_status_id ?? 'null') . "\n";
    }

    // Use fee directly from CSV (authoritative)
    $grossAmount = $row['gross'];
    $feeAmount   = $row['fee'];
    $netAmount   = $row['net'];

    echo "  Gross={$grossAmount} Fee={$feeAmount} Net={$netAmount}\n";

    if ($isDryRun) {
        echo "  [DRY RUN] Would mark order paid + upsert CERs + insert transactions_pf\n\n";
        continue;
    }

    try {
        DB::transaction(function () use ($order, $row, $pfPaymentId, $grossAmount, $feeAmount, $netAmount) {
            // Mark order paid
            $order->pay_status          = 1;
            $order->payfast_paid        = true;
            $order->payfast_pf_payment_id = $pfPaymentId;
            $order->payfast_amount_due  = $row['gross'];
            $order->updated_at          = $row['paid_at'];
            $order->save();

            // Mark each CER paid
            foreach ($order->items as $item) {
                CategoryEventRegistration::where('registration_id', $item->registration_id)
                    ->where('category_event_id', $item->category_event_id)
                    ->update(['payment_status_id' => 1]);
            }

            // Insert transactions_pf row — only columns that exist in the table
            $t = new Transaction();
            $t->pf_payment_id    = $pfPaymentId;
            $t->amount_gross     = $grossAmount;
            $t->amount_fee       = $feeAmount;
            $t->amount_net       = $netAmount;
            $t->item_name        = $row['item_name'];
            $t->custom_int1      = $row['custom_int1'];
            $t->custom_int2      = $row['custom_int2'];
            $t->custom_int3      = $row['custom_int3'];
            $t->custom_int4      = $row['custom_int4'];
            $t->custom_int5      = $order->id;
            $t->custom_str1      = $row['custom_str1'];
            $t->custom_str2      = $row['custom_str2'];
            $t->custom_str3      = $row['custom_str3'];
            $t->custom_str4      = $row['custom_str4'];
            $t->transaction_type = 'Registration';
            $t->is_test          = false;
            $t->event_id         = $row['event_id'];
            $t->created_at       = $row['paid_at'];
            $t->updated_at       = $row['paid_at'];
            $t->save();

            echo "  ✅ Done — transactions_pf id={$t->id}\n";
        });
    } catch (\Throwable $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "Complete.\n";
