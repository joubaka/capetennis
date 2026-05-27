<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CategoryEventRegistration;

// Inspect event 235 pending refund CERs
$cers = CategoryEventRegistration::with(['players','categoryEvent.category','payfastTransaction'])
    ->whereHas('categoryEvent', fn($q) => $q->where('event_id', 235))
    ->where('status', 'withdrawn')
    ->whereIn('refund_status', ['pending','completed'])
    ->get();

echo "Event 235 — withdrawn CERs with pending/completed refund_status:\n";
foreach ($cers as $cer) {
    $payment = $cer->paymentInfo();
    echo sprintf(
        "  CER#%d refund_status=%-12s refund_gross=%-8s refund_method=%-8s payment_method=%-10s payfast_id=%-12s wallet_txn_id=%s\n",
        $cer->id,
        $cer->refund_status,
        $cer->refund_gross ?? 'NULL',
        $cer->refund_method ?? 'NULL',
        $cer->payment_method ?? 'NULL',
        $cer->payfast_id ?? 'NULL',
        $cer->wallet_transaction_id ?? 'NULL'
    );
    echo "    paymentInfo: " . json_encode($payment) . "\n";
}

echo "\n\nAll pending CERs for event 235 (regardless of status):\n";
$raw = DB::table('category_event_registrations')
    ->join('category_events', 'category_event_registrations.category_event_id', '=', 'category_events.id')
    ->where('category_events.event_id', 235)
    ->where('category_event_registrations.refund_status', 'pending')
    ->get(['category_event_registrations.id','category_event_registrations.status',
           'category_event_registrations.refund_status','category_event_registrations.refund_gross',
           'category_event_registrations.refund_method','category_event_registrations.payment_method',
           'category_event_registrations.payfast_id']);
foreach ($raw as $r) {
    echo sprintf("  CER#%-6d status=%-12s refund_status=%-12s refund_gross=%-8s refund_method=%-8s payment_method=%-10s payfast_id=%s\n",
        $r->id, $r->status, $r->refund_status, $r->refund_gross ?? 'NULL', $r->refund_method ?? 'NULL',
        $r->payment_method ?? 'NULL', $r->payfast_id ?? 'NULL');
}

echo "\n\nRaw pending_refunds total (raw query): R" .
    DB::table('category_event_registrations')
        ->join('category_events','category_event_registrations.category_event_id','=','category_events.id')
        ->where('category_events.event_id', 235)
        ->where('category_event_registrations.refund_status','pending')
        ->sum('category_event_registrations.refund_gross') . "\n";
