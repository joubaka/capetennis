<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Payout audit: for each event that has payouts, compare sum of event_payouts
// against the canonical ledger total_paid_out.

$events = DB::table('event_payouts')
    ->join('events', 'event_payouts.event_id', '=', 'events.id')
    ->select('events.id', 'events.name', DB::raw('SUM(event_payouts.amount) as payout_sum'), DB::raw('COUNT(*) as payout_count'))
    ->groupBy('events.id', 'events.name')
    ->orderByDesc('payout_sum')
    ->limit(10)
    ->get();

echo "=== PAYOUT AUDIT (top 10 events by payout volume) ===\n\n";

$service = app(\App\Domain\Finance\Services\FinancialLedgerService::class);
$allMatch = true;

foreach ($events as $e) {
    $event = \App\Models\Event::find($e->id);
    if (!$event) continue;

    $feePerEntry = (float) \App\Models\SiteSetting::get('cape_tennis_fee_per_entry', 10);
    $paymentRows = $service->buildPaymentRows($event, $feePerEntry);
    $refundRows  = $service->buildRefundRows($event, $feePerEntry);
    $payoutRows  = $service->buildPayoutRows($event);
    $totals      = $service->buildTotals($paymentRows, $refundRows, $payoutRows);

    $ledgerPayout = (float) $totals['total_paid_out'];
    $rawPayout    = (float) $e->payout_sum;
    $match = abs($ledgerPayout - $rawPayout) < 0.01;
    if (!$match) $allMatch = false;

    printf("Event #%-4d %-42s payouts=%d  raw=R%-10s  ledger=R%-10s  %s\n",
        $e->id,
        mb_substr($e->name, 0, 42),
        $e->payout_count,
        number_format($rawPayout, 2),
        number_format($ledgerPayout, 2),
        $match ? '✅ MATCH' : '❌ MISMATCH'
    );

    // Detail rows
    foreach ($payoutRows as $p) {
        printf("   Payout  R%-10s  method=%-10s  ref=%s  paid_at=%s\n",
            number_format(abs($p->gross), 2),
            $p->method,
            $p->reference ?? '—',
            optional($p->model->paid_at)->format('Y-m-d') ?? '—'
        );
    }
    echo "\n";
}

echo "=== OVERALL: " . ($allMatch ? '✅ ALL PAYOUTS MATCH' : '❌ SOME PAYOUTS MISMATCH') . " ===\n";
