<?php
/**
 * LOCAL ITN test for order 9651 — Jean Joubert, u/13 Girls, event 239, R285.
 *
 * This script fires the actual notify() route through the real controller logic,
 * with a properly computed PayFast signature so the full code path is exercised.
 *
 * Usage:  php test_itn_9651.php
 *
 * Prerequisites:
 *   1. Order 9651 must have pay_status=0, payfast_paid=0  (reset done before running).
 *   2. CER 18155 must have payment_status_id=0            (reset done before running).
 */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

// ============================================================
// ORDER / PLAYER INFO
// ============================================================
$orderId        = 9651;
$pfPaymentId    = 'LOCAL_TEST_' . date('YmdHis');
$amountGross    = '285.00';
$amountFee      = '-11.65';
$amountNet      = '273.35';
$playerId       = 245;
$ceId           = 2069;
$eventId        = 239;
$registrationId = 19938;
$userId         = DB::table('registration_orders')->where('id', $orderId)->value('user_id');

echo "=== ITN LOCAL TEST: order {$orderId}, player {$playerId} (Jean Joubert), ce {$ceId} ===\n";
echo "user_id resolved to: {$userId}\n\n";

// ============================================================
// STEP 1: Reset order + CER to unpaid (idempotent)
// ============================================================
echo "--- STEP 1: Reset order and CER to unpaid ---\n";

DB::table('registration_orders')->where('id', $orderId)->update([
    'pay_status'              => 0,
    'payfast_paid'            => 0,
    'payfast_pf_payment_id'   => null,
    'wallet_reserved'         => 0,
    'wallet_debited'          => 0,
    'payfast_amount_due'      => 285.00,
    'updated_at'              => now(),
]);

DB::table('category_event_registrations')
    ->where('registration_id', $registrationId)
    ->where('category_event_id', $ceId)
    ->update([
        'payment_status_id' => 0,
        'pf_transaction_id' => null,
        'updated_at'        => now(),
    ]);

echo "  Order 9651 reset to unpaid.\n";
echo "  CER 18155 reset to payment_status_id=0.\n\n";

// ============================================================
// STEP 2: Build the ITN payload (matches what PayFast would send)
// ============================================================
echo "--- STEP 2: Build ITN payload ---\n";

// Determine which merchant credentials to use (sandbox vs live)
$isSandbox      = config('services.payfast.sandbox', true);
$merchantId     = $isSandbox
    ? config('services.payfast.sandbox_merchant_id', '10008657')
    : config('services.payfast.merchant_id');
$passphrase     = $isSandbox
    ? config('services.payfast.passphrase_sandbox')
    : (config('services.payfast.passphrase_live')
       ?: config('services.payfast.passphrase'));

echo "  sandbox={$isSandbox}, merchant_id={$merchantId}, passphrase=[" . (empty($passphrase) ? 'EMPTY' : 'set') . "]\n";

// Build payload WITHOUT signature first
$payload = [
    'merchant_id'      => $merchantId,
    'merchant_key'     => $isSandbox
        ? config('services.payfast.sandbox_merchant_key', '')
        : config('services.payfast.merchant_key'),
    'payment_status'   => 'COMPLETE',
    'pf_payment_id'    => $pfPaymentId,
    'amount_gross'     => $amountGross,
    'amount_fee'       => $amountFee,
    'amount_net'       => $amountNet,
    'item_name'        => 'Cape Tennis Registration Test',
    'item_description' => '',
    'custom_str1'      => 'u/13 Girls',
    'custom_str2'      => 'Jean Joubert',
    'custom_str3'      => 'Cape Tennis Registration Test',
    'custom_str4'      => '',
    'custom_str5'      => '',
    'custom_int1'      => (string) $ceId,
    'custom_int2'      => (string) $playerId,
    'custom_int3'      => (string) $eventId,
    'custom_int4'      => (string) $userId,
    'custom_int5'      => (string) $orderId,
    'name_first'       => 'Jean',
    'name_last'        => 'Joubert',
    'email_address'    => 'test@capetennis.co.za',
    'payment_method'   => 'cc',
];

// Compute signature exactly as PayFast does:
// md5(http_build_query($payload) + &passphrase=<encoded>)
$sigBase  = http_build_query($payload);
if (!empty($passphrase)) {
    $sigBase .= '&passphrase=' . urlencode(trim($passphrase));
}
$signature = md5($sigBase);
$payload['signature'] = $signature;

echo "  Signature computed: {$signature}\n";
echo "  Payload custom_int5={$payload['custom_int5']} amount_gross={$payload['amount_gross']}\n\n";

// ============================================================
// STEP 3: Fire the actual notify() controller via HTTP to hit
//         the full middleware + validation stack, OR call it
//         directly if local HTTP is not available.
// ============================================================
echo "--- STEP 3: Fire ITN through RegisterController::notify() ---\n";

// Build a proper Laravel Request that will have both all() data AND raw body
// (so validatePayfastSignature uses http_build_query fallback correctly)
$request = Request::create(
    '/notify',
    'POST',
    $payload,          // parsed post params -> all()
    [],                // cookies
    [],                // files
    ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
    http_build_query($payload)  // raw body -> matches the signature we built
);
$request->setLaravelSession(app('session')->driver());

// Resolve the controller through the container so middleware-injected
// dependencies are satisfied.
$controller = app(\App\Http\Controllers\Frontend\RegisterController::class);

try {
    $response = $controller->notify($request);
    $body = method_exists($response, 'getContent') ? $response->getContent() : (string) $response;
    echo "  notify() returned HTTP {$response->getStatusCode()}: {$body}\n";
} catch (\Throwable $e) {
    echo "  notify() threw: " . $e->getMessage() . "\n";
    echo "  " . $e->getFile() . ':' . $e->getLine() . "\n";
    echo "  Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

// ============================================================
// STEP 4: Verify DB state after ITN
// ============================================================
echo "\n--- STEP 4: Verify results ---\n";

$order = DB::table('registration_orders')->where('id', $orderId)->first();
echo "  Order:\n";
echo "    pay_status       = {$order->pay_status}  (expect 1)\n";
echo "    payfast_paid     = {$order->payfast_paid}  (expect 1)\n";
echo "    pf_payment_id    = {$order->payfast_pf_payment_id}\n";

$cer = DB::table('category_event_registrations')
    ->where('registration_id', $registrationId)
    ->where('category_event_id', $ceId)
    ->whereNull('deleted_at')
    ->first();
echo "  CER:\n";
echo "    payment_status_id = {$cer->payment_status_id}  (expect 1)\n";
echo "    pf_transaction_id = {$cer->pf_transaction_id}\n";

$tx = DB::table('transactions_pf')->where('pf_payment_id', $pfPaymentId)->first();
if ($tx) {
    echo "  transactions_pf row found: id={$tx->id} gross={$tx->amount_gross} event={$tx->event_id} player={$tx->player_id}\n";
} else {
    echo "  transactions_pf row: NOT FOUND ❌\n";
}

// Overall verdict
$passed = $order->pay_status == 1
       && $order->payfast_paid == 1
       && $cer->payment_status_id == 1;

echo "\n" . ($passed ? "✅ ITN LOCAL TEST PASSED" : "❌ ITN LOCAL TEST FAILED — check output above") . "\n";
