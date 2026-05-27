<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Event;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;

$event = Event::findOrFail(222);
$feePerEntry = (float) SiteSetting::get('cape_tennis_fee_per_entry', 10);

echo "=== EVENT 222 ===\n";
echo "Name:          {$event->name}\n";
echo "fee_per_entry: R{$feePerEntry}\n\n";

$service = app(FinancialLedgerService::class);
$paymentRows = $service->buildPaymentRows($event, $feePerEntry);
$refundRows  = $service->buildRefundRows($event, $feePerEntry);
$payoutRows  = $service->buildPayoutRows($event);
$totals      = $service->buildTotals($paymentRows, $refundRows, $payoutRows);

echo "=== LEDGER TOTALS ===\n";
foreach ($totals as $k => $v) {
    if (is_numeric($v)) echo sprintf("  %-30s R%s\n", $k, number_format($v, 2));
}

echo "\n=== PAYMENT ROWS (" . $paymentRows->count() . " rows) ===\n";
$entryCount = 0;
$grossSum = 0;
$capeSum = 0;
foreach ($paymentRows as $r) {
    $entries = $r->items_in_order ?? 1;
    $entryCount += $entries;
    $grossSum   += $r->gross ?? 0;
    $capeSum    += $r->capeFee ?? 0;
    echo sprintf("  order=%-6s items=%-3d gross=R%-10s capeFee=R%-10s method=%s\n",
        $r->order_id ?? '?', $entries,
        number_format($r->gross ?? 0, 2),
        number_format($r->capeFee ?? 0, 2),
        $r->method ?? '?'
    );
}
echo "  ─────────────────────────────────────────────\n";
echo "  Total entries in payment rows: $entryCount\n";
echo "  Total gross:    R" . number_format($grossSum, 2) . "\n";
echo "  Total capeFees: R" . number_format($capeSum, 2) . "\n";

echo "\n=== ENTRY COUNT CHECK ===\n";
// Count active (non-withdrawn) CERs for this event
$activeCers = DB::table('category_event_registrations')
    ->join('category_events', 'category_event_registrations.category_event_id', '=', 'category_events.id')
    ->where('category_events.event_id', 222)
    ->where('category_event_registrations.status', '!=', 'withdrawn')
    ->count();
$allCers = DB::table('category_event_registrations')
    ->join('category_events', 'category_event_registrations.category_event_id', '=', 'category_events.id')
    ->where('category_events.event_id', 222)
    ->count();
echo "  Active (non-withdrawn) CERs: $activeCers\n";
echo "  All CERs:                    $allCers\n";
echo "  items_in_order sum:          $entryCount\n";
echo "  Expected Cape Fee (R{$feePerEntry} × {$entryCount}): R" . number_format($feePerEntry * $entryCount, 2) . "\n";
echo "  Actual Cape Fees in ledger:  R" . number_format($totals['cape_fees'], 2) . "\n";
$impliedFee = $entryCount > 0 ? round($totals['cape_fees'] / $entryCount, 4) : 0;
echo "  Implied fee per entry:       R{$impliedFee}\n";
