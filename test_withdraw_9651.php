<?php
/**
 * LOCAL withdrawal + refund test for CER 18155 (Jean Joubert, order 9651).
 *
 * This replicates exactly what the admin did on remote:
 *   1. Admin withdrew via backend (CategoryEventController::withdraw)
 *   2. Admin chose wallet refund via AdminRegistrationRefundController::storeRefund
 *
 * We run it locally to expose the exact failure — paymentInfo() returns []
 * because there is no transactions_pf row for this payment (the ITN test script's
 * insert failed due to pf_payment_id column length).
 *
 * Usage:  php test_withdraw_9651.php
 */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\CategoryEventRegistration;
use App\Models\User;
use App\Models\Wallet;

$cerId  = 18155;
$userId = DB::table('category_event_registrations')->where('id', $cerId)->value('user_id');

echo "=== LOCAL WITHDRAWAL + WALLET REFUND TEST: CER {$cerId} ===\n\n";

// ============================================================
// STEP 1: Reset to paid/active state
// ============================================================
echo "--- STEP 1: Reset CER to paid/active ---\n";
DB::table('category_event_registrations')->where('id', $cerId)->update([
    'status'           => 'active',
    'payment_status_id'=> 1,
    'pf_transaction_id'=> 'LOCAL_TEST_20260525121001',
    'refund_status'    => 'not_refunded',
    'refund_method'    => null,
    'refund_gross'     => null,
    'refund_net'       => null,
    'refund_fee'       => null,
    'refunded_at'      => null,
    'withdrawn_at'     => null,
    'updated_at'       => now(),
]);
DB::table('registration_orders')->where('id', 9651)->update([
    'pay_status'   => 1,
    'payfast_paid' => 1,
    'wallet_reserved' => 0,
    'updated_at'   => now(),
]);
echo "  Reset done.\n\n";

// ============================================================
// STEP 2: Load CER and inspect paymentInfo()
// ============================================================
echo "--- STEP 2: Inspect paymentInfo() ---\n";

$cer = CategoryEventRegistration::with([
    'players', 'categoryEvent.event', 'categoryEvent.category', 'user',
])->find($cerId);

echo "  is_paid:           " . ($cer->is_paid ? 'YES' : 'NO') . "\n";
echo "  pf_transaction_id: " . ($cer->pf_transaction_id ?? 'NULL') . "\n";
echo "  wallet_reserved:   " . (DB::table('registration_orders')->where('id', 9651)->value('wallet_reserved')) . "\n";

$payment = $cer->paymentInfo();
echo "  paymentInfo():     " . json_encode($payment) . "\n";

if (empty($payment)) {
    echo "\n  *** ROOT CAUSE: paymentInfo() returned [] ***\n";
    echo "  This happens because pf_transaction_id = '{$cer->pf_transaction_id}'\n";
    echo "  but no transactions_pf row exists with that pf_payment_id.\n";
    echo "  The payfastTransaction relation returns null => paymentInfo() returns [].\n";
    $tx = DB::table('transactions_pf')->where('pf_payment_id', $cer->pf_transaction_id)->first();
    echo "  transactions_pf lookup: " . ($tx ? json_encode($tx) : "NOT FOUND") . "\n";
} else {
    echo "  paymentInfo() gross={$payment['gross']} fee={$payment['fee']} net={$payment['net']}\n";
}

// ============================================================
// STEP 3: Check canWithdraw
// ============================================================
echo "\n--- STEP 3: canWithdraw() ---\n";
$user = User::find($userId);
$check = $cer->canWithdraw($user);
echo "  " . json_encode($check) . "\n";

// ============================================================
// STEP 4: Simulate admin withdrawal (same as CategoryEventController::withdraw)
// ============================================================
echo "\n--- STEP 4: Simulate admin withdrawal ---\n";
DB::transaction(function () use ($cer, $user) {
    $cer->markWithdrawn($user, 'admin');
});
$cer->refresh();
echo "  status:          {$cer->status}\n";
echo "  is_paid:         " . ($cer->is_paid ? 'YES' : 'NO') . "\n";
echo "  refund_status:   {$cer->refund_status}\n";
echo "  withdrawn_at:    {$cer->withdrawn_at}\n";

// ============================================================
// STEP 5: Attempt wallet refund (same as AdminRegistrationRefundController::storeRefund)
// ============================================================
echo "\n--- STEP 5: Attempt admin wallet refund ---\n";
$payment2 = $cer->fresh()->paymentInfo();
echo "  paymentInfo() after withdraw: " . json_encode($payment2) . "\n";

if (empty($payment2)) {
    echo "\n  *** CONFIRMED FAILURE: paymentInfo() is empty after withdrawal too. ***\n";
    echo "  This means:\n";
    echo "   - The refund choose page shows 'No payment information found'\n";
    echo "   - AdminRegistrationRefundController redirects with 'No payment information found — no refund required'\n";
    echo "   - The player email only shows 'No refund has been issued' because refund_status stays 'not_refunded'\n";
    echo "   - The wallet receives NO credit\n\n";
    echo "  ROOT CAUSE: Admin 'mark paid' set payment_status_id=1 and pf_transaction_id\n";
    echo "  but did NOT insert a transactions_pf row. paymentInfo() needs the transactions_pf\n";
    echo "  row to determine gross/fee/net — without it, refund is impossible.\n\n";
    echo "  FIX: For admin-marked-paid registrations (no real PayFast transaction),\n";
    echo "  paymentInfo() must fall back to the order's payfast_amount_due when\n";
    echo "  pf_transaction_id is set but no transactions_pf row exists.\n";
} else {
    $gross = round(($payment2['gross'] ?? 0) + ($payment2['wallet_paid'] ?? 0), 2);
    $net   = round(($payment2['net'] ?? 0) + ($payment2['wallet_paid'] ?? 0), 2);
    echo "  gross={$gross} net={$net} — refund would proceed.\n";
}

// ============================================================
// STEP 6: Execute the real admin wallet refund via WalletService
// ============================================================
echo "\n--- STEP 6: Execute real wallet refund ---\n";

$freshCer = CategoryEventRegistration::find($cerId);
$payment3 = $freshCer->paymentInfo();
$gross3    = round(($payment3['gross'] ?? 0) + ($payment3['wallet_paid'] ?? 0), 2);
$net3      = round(($payment3['net'] ?? 0) + ($payment3['wallet_paid'] ?? 0), 2);
$fee3      = $payment3['fee'] ?? 0;

echo "  gross={$gross3} fee={$fee3} net={$net3}\n";

$payer = $freshCer->user ?? User::find($userId);
if (!$payer) {
    echo "  ERROR: No payer found.\n";
    exit(1);
}

$wallet = $payer->wallet
    ?? \App\Models\Wallet::create(['payable_type' => User::class, 'payable_id' => $payer->id]);

$walletBefore = (float) $wallet->balance;
echo "  Wallet balance before: R{$walletBefore}\n";

try {
    DB::transaction(function () use ($freshCer, $wallet, $gross3, $fee3, $net3, $payer) {
        app(\App\Services\Wallet\WalletService::class)->credit(
            $wallet,
            $gross3,
            'admin_refund',
            $freshCer->id,
            [
                'registration_id' => $freshCer->id,
                'event_id'        => $freshCer->categoryEvent->event_id,
                'gross'           => $gross3,
                'fee'             => 0,
                'method'          => 'wallet',
                'reference'       => optional($freshCer->categoryEvent?->event)->name,
                'initiated_by'    => 'admin',
            ]
        );

        $freshCer->update([
            'refund_method' => 'wallet',
            'refund_status' => \App\Models\CategoryEventRegistration::REFUND_COMPLETED,
            'refund_gross'  => $gross3,
            'refund_fee'    => 0,
            'refund_net'    => $gross3,
            'refunded_at'   => now(),
        ]);
    });

    $wallet->refresh();
    $walletAfter = (float) $wallet->balance;
    echo "  Wallet balance after:  R{$walletAfter}\n";
    echo "  Credited:              R" . round($walletAfter - $walletBefore, 2) . "\n";

    $freshCer->refresh();
    echo "  refund_status: {$freshCer->refund_status}\n";
    echo "  refund_method: {$freshCer->refund_method}\n";
    echo "  refund_gross:  {$freshCer->refund_gross}\n";
    echo "  refund_net:    {$freshCer->refund_net}\n";

    $passed = $freshCer->refund_status === 'completed'
           && $freshCer->refund_method === 'wallet'
           && $walletAfter > $walletBefore;

    echo "\n" . ($passed ? "✅ WITHDRAWAL + WALLET REFUND PASSED" : "❌ FAILED — check above") . "\n";

} catch (\Throwable $e) {
    echo "  ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
