<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check the hybrid order
$order = App\Models\RegistrationOrder::find(9074);
echo "Order #9074:\n";
echo "  wallet_reserved={$order->wallet_reserved}\n";
echo "  payfast_amount_due={$order->payfast_amount_due}\n";

$items = $order->items;
foreach ($items as $item) {
    echo "  Item: reg_id={$item->registration_id}, cat_event_id={$item->category_event_id}, item_price={$item->item_price}\n";

    // Check pivot record
    $pivot = App\Models\CategoryEventRegistration::where('registration_id', $item->registration_id)
        ->where('category_event_id', $item->category_event_id)
        ->first();
    if ($pivot) {
        echo "    Pivot #{$pivot->id}: pf_transaction_id={$pivot->pf_transaction_id}, status={$pivot->status}\n";
        $paymentInfo = $pivot->paymentInfo();
        echo "    paymentInfo: " . json_encode($paymentInfo) . "\n";
    }
}
